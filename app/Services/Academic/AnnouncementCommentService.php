<?php

namespace App\Services\Academic;

use App\Services\Core\AvatarResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class AnnouncementCommentService
{
    public const PAGE_SIZE = 25;

    public const MAX_TEXT_LENGTH = 2000;

    public function __construct(
        private AnnouncementService $announcements,
        private AnnouncementVisibilityService $visibility,
        private AvatarResolver $avatars,
    ) {}

    public function paginate(string $announcementId, mixed $user): LengthAwarePaginator
    {
        $announcement = $this->authorizedAnnouncement($announcementId, $user);

        return $this->paginateRows((string) $announcement['Announcement_ID'], $user);
    }

    /**
     * Show the complete thread to an announcement manager without widening
     * the audience rules used by student-facing routes.
     */
    public function paginateForManager(string $announcementId, mixed $user): LengthAwarePaginator
    {
        $announcement = $this->announcements->getById($announcementId);
        if (! $announcement) {
            throw new \InvalidArgumentException('Pengumuman tidak ditemukan.');
        }

        $role = $this->visibility->roleName($user);
        $isGlobalManager = in_array($role, ['ADMINISTRATOR', 'ACADEMIC', 'MASTER'], true);
        $creatorId = trim((string) ($announcement['Created_By'] ?? ''));
        $isTeacherOwner = $role === 'TEACHER'
            && $creatorId !== ''
            && hash_equals($creatorId, $this->userId($user));
        if (! $isGlobalManager && ! $isTeacherOwner) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk mengelola pengumuman ini.');
        }

        return $this->paginateRows((string) $announcement['Announcement_ID'], $user);
    }

    private function paginateRows(string $announcementId, mixed $user): LengthAwarePaginator
    {
        $paginator = $this->connection()->table('ANNOUNCEMENT_COMMENT as comments')
            ->leftJoin('MASTER_USER as users', 'users.User_ID', '=', 'comments.User_ID')
            ->leftJoin('MASTER_ROLE as roles', 'roles.Role_ID', '=', 'users.Role_ID')
            ->leftJoin('MASTER_STICKER as stickers', 'stickers.Sticker_ID', '=', 'comments.Sticker_ID')
            ->where('comments.Announcement_ID', $announcementId)
            ->whereNull('comments.Deleted_At')
            ->orderBy('comments.Created_At')->orderBy('comments.Comment_ID')
            ->select([
                'comments.*', 'users.Full_Name as User_Name', 'users.Username', 'users.Role_ID',
                'roles.Role_Name', 'stickers.Name as Sticker_Name', 'stickers.File_Path as Sticker_Path',
            ])
            ->paginate(self::PAGE_SIZE)
            ->through(function ($row) use ($user): object {
                if ($row->Sticker_Path) {
                    $row->Sticker_URL = Storage::disk('public')->url($row->Sticker_Path);
                }
                $row->Can_Delete = $this->visibility->roleName($user) === 'ADMINISTRATOR'
                    || hash_equals((string) $row->User_ID, $this->userId($user));

                return $row;
            });

        $avatarMap = $this->avatars->resolveMany($paginator->getCollection()->map(fn ($row): array => [
            'User_ID' => $row->User_ID, 'Full_Name' => $row->User_Name, 'Role_ID' => $row->Role_ID,
        ]));
        $paginator->through(function ($row) use ($avatarMap): object {
            $row->Avatar_URL = $avatarMap[$row->User_ID] ?? null;

            return $row;
        });

        return $paginator;
    }

    public function createText(string $announcementId, mixed $user, string $text): string
    {
        $this->authorizedAnnouncement($announcementId, $user);
        $text = trim($text);
        if ($text === '') {
            throw new \InvalidArgumentException('Komentar tidak boleh kosong.');
        }
        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            throw new \InvalidArgumentException('Komentar maksimal '.self::MAX_TEXT_LENGTH.' karakter.');
        }

        return $this->insert($announcementId, $user, 'TEXT', $text, null);
    }

    public function createSticker(string $announcementId, mixed $user, string $stickerId): string
    {
        $this->authorizedAnnouncement($announcementId, $user);
        $sticker = $this->connection()->table('MASTER_STICKER as sticker')
            ->join('MASTER_STICKER_PACK as pack', 'pack.Sticker_Pack_ID', '=', 'sticker.Sticker_Pack_ID')
            ->where('sticker.Sticker_ID', $stickerId)
            ->where('sticker.Is_Active', true)->where('pack.Is_Active', true)
            ->select('sticker.Sticker_ID')->first();
        if (! $sticker) {
            throw new \InvalidArgumentException('Stiker tidak tersedia.');
        }

        return $this->insert($announcementId, $user, 'STICKER', null, $stickerId);
    }

    public function delete(string $announcementId, string $commentId, mixed $user): void
    {
        $isAdmin = $this->visibility->roleName($user) === 'ADMINISTRATOR';
        if ($isAdmin) {
            if (! $this->announcements->getById($announcementId)) {
                throw new \InvalidArgumentException('Pengumuman tidak ditemukan.');
            }
        } else {
            $this->authorizedAnnouncement($announcementId, $user);
        }
        $comment = $this->connection()->table('ANNOUNCEMENT_COMMENT')
            ->where('Comment_ID', $commentId)->where('Announcement_ID', $announcementId)
            ->whereNull('Deleted_At')->first();
        if (! $comment) {
            throw new \InvalidArgumentException('Komentar tidak ditemukan.');
        }
        $userId = $this->userId($user);
        if (! $isAdmin && ! hash_equals((string) $comment->User_ID, $userId)) {
            throw new AuthorizationException('Anda tidak dapat menghapus komentar ini.');
        }
        $this->connection()->table('ANNOUNCEMENT_COMMENT')->where('Comment_ID', $commentId)->update([
            'Deleted_At' => now(AnnouncementService::TIMEZONE)->format('Y-m-d H:i:s.u'),
            'Updated_At' => now(AnnouncementService::TIMEZONE)->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function counts(iterable $announcementIds): array
    {
        $ids = collect($announcementIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return $this->connection()->table('ANNOUNCEMENT_COMMENT')
            ->whereIn('Announcement_ID', $ids)->whereNull('Deleted_At')
            ->groupBy('Announcement_ID')->select('Announcement_ID', DB::raw('COUNT(*) as aggregate'))
            ->pluck('aggregate', 'Announcement_ID')->map(fn ($count): int => (int) $count)->all();
    }

    /**
     * Load a bounded preview for several already-authorized announcements.
     *
     * One windowed query obtains at most $limit rows per announcement, then
     * AvatarResolver resolves every commenter as a single batch. Callers must
     * only pass IDs obtained from AnnouncementService::getActiveForUser().
     *
     * @return array<string, Collection<int, object>>
     */
    public function latestForAnnouncements(iterable $announcementIds, mixed $user, int $limit = 3): array
    {
        $ids = collect($announcementIds)
            ->map(fn ($id): string => trim((string) $id))
            ->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $limit = max(1, min($limit, 10));
        $ranked = $this->connection()->table('ANNOUNCEMENT_COMMENT as source_comments')
            ->whereIn('source_comments.Announcement_ID', $ids)
            ->whereNull('source_comments.Deleted_At')
            ->select('source_comments.*')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY source_comments.Announcement_ID ORDER BY source_comments.Created_At DESC, source_comments.Comment_ID DESC) as Thread_Row');

        $rows = $this->connection()->query()
            ->fromSub($ranked, 'comments')
            ->leftJoin('MASTER_USER as users', 'users.User_ID', '=', 'comments.User_ID')
            ->leftJoin('MASTER_ROLE as roles', 'roles.Role_ID', '=', 'users.Role_ID')
            ->leftJoin('MASTER_STICKER as stickers', 'stickers.Sticker_ID', '=', 'comments.Sticker_ID')
            ->where('comments.Thread_Row', '<=', $limit)
            ->orderBy('comments.Announcement_ID')
            ->orderBy('comments.Created_At')
            ->orderBy('comments.Comment_ID')
            ->select([
                'comments.*', 'users.Full_Name as User_Name', 'users.Username', 'users.Role_ID',
                'roles.Role_Name', 'stickers.Name as Sticker_Name', 'stickers.File_Path as Sticker_Path',
            ])->get();

        $isAdmin = $this->visibility->roleName($user) === 'ADMINISTRATOR';
        $userId = $this->userId($user);
        $rows->each(function ($row) use ($isAdmin, $userId): void {
            if ($row->Sticker_Path) {
                $row->Sticker_URL = Storage::disk('public')->url($row->Sticker_Path);
            }
            $row->Can_Delete = $isAdmin || hash_equals((string) $row->User_ID, $userId);
        });

        if ($rows->isNotEmpty()) {
            $avatarMap = $this->avatars->resolveMany($rows->map(fn ($row): array => [
                'User_ID' => $row->User_ID,
                'Full_Name' => $row->User_Name,
                'Role_ID' => $row->Role_ID,
            ]));
            $rows->each(function ($row) use ($avatarMap): void {
                $row->Avatar_URL = $avatarMap[$row->User_ID] ?? null;
            });
        }

        return $rows->groupBy('Announcement_ID')->all();
    }

    /**
     * Resolve announcement authors without per-card user or avatar queries.
     *
     * @return array<string, object>
     */
    public function userProfiles(iterable $userIds): array
    {
        $ids = collect($userIds)
            ->map(fn ($id): string => trim((string) $id))
            ->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $users = $this->connection()->table('MASTER_USER as users')
            ->leftJoin('MASTER_ROLE as roles', 'roles.Role_ID', '=', 'users.Role_ID')
            ->whereIn('users.User_ID', $ids)
            ->select('users.User_ID', 'users.Full_Name as User_Name', 'users.Username', 'users.Role_ID', 'roles.Role_Name')
            ->get();
        $avatarMap = $this->avatars->resolveMany($users->map(fn ($row): array => [
            'User_ID' => $row->User_ID,
            'Full_Name' => $row->User_Name,
            'Role_ID' => $row->Role_ID,
        ]));
        $users->each(function ($row) use ($avatarMap): void {
            $row->Avatar_URL = $avatarMap[$row->User_ID] ?? null;
        });

        return $users->keyBy('User_ID')->all();
    }

    private function authorizedAnnouncement(string $id, mixed $user): array
    {
        $announcement = $this->announcements->getVisibleForUser($id, $user);
        if (! $announcement) {
            throw new AuthorizationException('Anda tidak memiliki akses ke pengumuman ini.');
        }

        return $announcement;
    }

    private function insert(string $announcementId, mixed $user, string $type, ?string $text, ?string $stickerId): string
    {
        $id = 'CMT-'.Str::upper((string) Str::ulid());
        $now = now(AnnouncementService::TIMEZONE)->format('Y-m-d H:i:s.u');
        $this->connection()->table('ANNOUNCEMENT_COMMENT')->insert([
            'Comment_ID' => $id,
            'Announcement_ID' => $announcementId,
            'User_ID' => $this->userId($user),
            'Comment_Type' => $type,
            'Comment_Text' => $type === 'TEXT' ? $text : null,
            'Sticker_ID' => $type === 'STICKER' ? $stickerId : null,
            'Created_At' => $now,
            'Updated_At' => $now,
            'Deleted_At' => null,
        ]);

        return $id;
    }

    private function userId(mixed $user): string
    {
        $id = trim((string) ($user->User_ID ?? (method_exists($user, 'getAuthIdentifier') ? $user->getAuthIdentifier() : '')));
        if ($id === '') {
            throw new AuthorizationException('Identitas pengguna tidak valid.');
        }

        return $id;
    }

    private function connection()
    {
        return DB::connection((string) config('wms_runtime.connection', config('database.default')));
    }
}
