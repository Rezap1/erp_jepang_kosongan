<?php

namespace App\Services\Academic;

use App\Services\Core\AvatarResolver;
use App\Services\Core\NotificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class AnnouncementCommentService
{
    public const PAGE_SIZE = 25;

    public const MAX_TEXT_LENGTH = 2000;

    public const MAX_MENTIONS = 5;

    private ?bool $socialSchemaAvailable = null;

    public function __construct(
        private AnnouncementService $announcements,
        private AnnouncementVisibilityService $visibility,
        private AvatarResolver $avatars,
        private NotificationService $notifications,
        private ExternalGifSearchService $externalGifs,
    ) {}

    public function paginate(string $announcementId, mixed $user): LengthAwarePaginator
    {
        $announcement = $this->authorizedAnnouncement($announcementId, $user);

        return $this->paginateRows((string) $announcement['Announcement_ID'], $user);
    }

    public function assertCanInteract(string $announcementId, mixed $user): void
    {
        $this->authorizedAnnouncement($announcementId, $user);
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
        $query = $this->connection()->table('ANNOUNCEMENT_COMMENT as comments')
            ->leftJoin('MASTER_USER as users', 'users.User_ID', '=', 'comments.User_ID')
            ->leftJoin('MASTER_ROLE as roles', 'roles.Role_ID', '=', 'users.Role_ID')
            ->leftJoin('MASTER_STICKER as stickers', 'stickers.Sticker_ID', '=', 'comments.Sticker_ID');
        $columns = [
            'comments.*', 'users.Full_Name as User_Name', 'users.Username', 'users.Role_ID',
            'roles.Role_Name', 'stickers.Name as Sticker_Name', 'stickers.File_Path as Sticker_Path',
            'stickers.Mime_Type as Sticker_Mime_Type',
        ];
        if ($this->socialSchemaReady()) {
            $query->leftJoin('ANNOUNCEMENT_COMMENT as parent', 'parent.Comment_ID', '=', 'comments.Parent_Comment_ID')
                ->leftJoin('MASTER_USER as parent_users', 'parent_users.User_ID', '=', 'parent.User_ID');
            $columns = [...$columns, 'parent.Comment_Text as Parent_Comment_Text', 'parent.Deleted_At as Parent_Deleted_At', 'parent_users.Full_Name as Parent_User_Name', 'parent_users.Username as Parent_Username'];
        }

        $paginator = $query
            ->where('comments.Announcement_ID', $announcementId)
            ->whereNull('comments.Deleted_At')
            ->orderBy('comments.Created_At')->orderBy('comments.Comment_ID')
            ->select($columns)
            ->paginate(self::PAGE_SIZE);
        $this->decorateRows($paginator->getCollection(), $user);

        return $paginator;
    }

    public function createText(
        string $announcementId,
        mixed $user,
        string $text,
        ?string $parentCommentId = null,
        array $mentionedUserIds = [],
    ): string {
        $announcement = $this->authorizedAnnouncement($announcementId, $user);
        $text = trim($text);
        if ($text === '') {
            throw new \InvalidArgumentException('Komentar tidak boleh kosong.');
        }
        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            throw new \InvalidArgumentException('Komentar maksimal '.self::MAX_TEXT_LENGTH.' karakter.');
        }

        $parent = $this->validatedParent($announcementId, $parentCommentId);
        $mentions = $this->validatedMentions($announcement, $user, $mentionedUserIds);

        return $this->insert($announcementId, $user, 'TEXT', $text, null, $parent, $mentions);
    }

    public function createSticker(string $announcementId, mixed $user, string $stickerId, ?string $parentCommentId = null): string
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

        $parent = $this->validatedParent($announcementId, $parentCommentId);

        return $this->insert($announcementId, $user, 'STICKER', null, $stickerId, $parent);
    }

    public function createExternalGif(string $announcementId, mixed $user, string $externalId, ?string $parentCommentId = null): string
    {
        $this->authorizedAnnouncement($announcementId, $user);
        $this->requireSocialSchema();
        $parent = $this->validatedParent($announcementId, $parentCommentId);
        $gif = $this->externalGifs->resolve($externalId);

        return $this->insert($announcementId, $user, 'GIF', null, null, $parent, [], $gif);
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
        return collect($this->discussionStats($announcementIds))
            ->mapWithKeys(fn (array $stats, string $id): array => [$id => $stats['total']])
            ->all();
    }

    /**
     * Total means every active row, including replies. Root and reply counts
     * make that contract explicit while preserving the legacy total API.
     *
     * @return array<string,array{total:int,roots:int,replies:int}>
     */
    public function discussionStats(iterable $announcementIds): array
    {
        $ids = collect($announcementIds)->map(fn ($id): string => trim((string) $id))->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $query = $this->connection()->table('ANNOUNCEMENT_COMMENT')
            ->whereIn('Announcement_ID', $ids)->whereNull('Deleted_At')
            ->groupBy('Announcement_ID')->select('Announcement_ID', DB::raw('COUNT(*) as Total_Count'));
        if ($this->socialSchemaReady()) {
            $query->selectRaw('SUM(CASE WHEN Parent_Comment_ID IS NULL THEN 1 ELSE 0 END) as Root_Count')
                ->selectRaw('SUM(CASE WHEN Parent_Comment_ID IS NOT NULL THEN 1 ELSE 0 END) as Reply_Count');
        }

        return $query->get()->mapWithKeys(function (object $row): array {
            $total = (int) $row->Total_Count;

            return [(string) $row->Announcement_ID => [
                'total' => $total,
                'roots' => isset($row->Root_Count) ? (int) $row->Root_Count : $total,
                'replies' => isset($row->Reply_Count) ? (int) $row->Reply_Count : 0,
            ]];
        })->all();
    }

    /** @return list<array{user_id:string,name:string,username:string,role:string,avatar_url:?string}> */
    public function searchMentionCandidates(string $announcementId, mixed $user, string $query, int $limit = 8): array
    {
        $announcement = $this->authorizedAnnouncement($announcementId, $user);
        $query = trim($query);
        if (mb_strlen($query) < 2 || mb_strlen($query) > 50) {
            return [];
        }

        $escaped = '%'.addcslashes($query, '%_\\').'%';
        $users = $this->connection()->table('MASTER_USER as users')
            ->leftJoin('MASTER_ROLE as roles', 'roles.Role_ID', '=', 'users.Role_ID')
            ->where(function ($builder) use ($escaped): void {
                $builder->where('users.Full_Name', 'like', $escaped)->orWhere('users.Username', 'like', $escaped);
            })
            ->when($this->hasUserActiveColumn(), fn ($builder) => $builder->where('users.Is_Active', true))
            ->orderBy('users.Full_Name')->limit(30)
            ->select('users.User_ID', 'users.Full_Name', 'users.Username', 'users.Role_ID', 'roles.Role_Name')
            ->get()
            ->filter(fn (object $candidate): bool => $this->candidateCanView($candidate, $announcement))
            ->take(max(1, min($limit, 10)))->values();
        $avatars = $this->avatars->resolveMany($users->map(fn (object $candidate): array => [
            'User_ID' => $candidate->User_ID,
            'Full_Name' => $candidate->Full_Name,
            'Role_ID' => $candidate->Role_ID,
        ]));

        return $users->map(fn (object $candidate): array => [
            'user_id' => (string) $candidate->User_ID,
            'name' => trim((string) ($candidate->Full_Name ?: $candidate->Username ?: 'Pengguna')),
            'username' => trim((string) ($candidate->Username ?? '')),
            'role' => ucwords(strtolower((string) ($candidate->Role_Name ?: 'Pengguna'))),
            'avatar_url' => $avatars[$candidate->User_ID] ?? null,
        ])->all();
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

        $query = $this->connection()->query()
            ->fromSub($ranked, 'comments')
            ->leftJoin('MASTER_USER as users', 'users.User_ID', '=', 'comments.User_ID')
            ->leftJoin('MASTER_ROLE as roles', 'roles.Role_ID', '=', 'users.Role_ID')
            ->leftJoin('MASTER_STICKER as stickers', 'stickers.Sticker_ID', '=', 'comments.Sticker_ID');
        $columns = [
            'comments.*', 'users.Full_Name as User_Name', 'users.Username', 'users.Role_ID',
            'roles.Role_Name', 'stickers.Name as Sticker_Name', 'stickers.File_Path as Sticker_Path',
            'stickers.Mime_Type as Sticker_Mime_Type',
        ];
        if ($this->socialSchemaReady()) {
            $query->leftJoin('ANNOUNCEMENT_COMMENT as parent', 'parent.Comment_ID', '=', 'comments.Parent_Comment_ID')
                ->leftJoin('MASTER_USER as parent_users', 'parent_users.User_ID', '=', 'parent.User_ID');
            $columns = [...$columns, 'parent.Comment_Text as Parent_Comment_Text', 'parent.Deleted_At as Parent_Deleted_At', 'parent_users.Full_Name as Parent_User_Name', 'parent_users.Username as Parent_Username'];
        }

        $rows = $query
            ->where('comments.Thread_Row', '<=', $limit)
            ->orderBy('comments.Announcement_ID')
            ->orderBy('comments.Created_At')
            ->orderBy('comments.Comment_ID')
            ->select($columns)->get();

        $this->decorateRows($rows, $user);

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

    private function insert(
        string $announcementId,
        mixed $user,
        string $type,
        ?string $text,
        ?string $stickerId,
        ?object $parent = null,
        array $mentionedUserIds = [],
        ?array $externalMedia = null,
    ): string {
        $id = 'CMT-'.Str::upper((string) Str::ulid());
        $now = now(AnnouncementService::TIMEZONE)->format('Y-m-d H:i:s.u');
        $payload = [
            'Comment_ID' => $id,
            'Announcement_ID' => $announcementId,
            'User_ID' => $this->userId($user),
            'Comment_Type' => $type,
            'Comment_Text' => $type === 'TEXT' ? $text : null,
            'Sticker_ID' => $type === 'STICKER' ? $stickerId : null,
            'Created_At' => $now,
            'Updated_At' => $now,
            'Deleted_At' => null,
        ];
        if ($this->socialSchemaReady()) {
            $payload = [...$payload,
                'Parent_Comment_ID' => $parent?->Comment_ID,
                'External_Media_Provider' => $externalMedia ? $this->externalGifsProviderName() : null,
                'External_Media_ID' => $externalMedia['id'] ?? null,
                'External_Media_URL' => $externalMedia['url'] ?? null,
                'External_Media_Preview_URL' => $externalMedia['preview_url'] ?? null,
                'External_Media_Alt' => $externalMedia['alt'] ?? null,
            ];
        }

        $this->connection()->transaction(function () use ($payload, $mentionedUserIds, $id, $now): void {
            $this->connection()->table('ANNOUNCEMENT_COMMENT')->insert($payload);
            if ($mentionedUserIds !== []) {
                $this->connection()->table('ANNOUNCEMENT_COMMENT_MENTION')->insert(
                    collect($mentionedUserIds)->map(fn (string $userId): array => [
                        'Comment_ID' => $id,
                        'Mentioned_User_ID' => $userId,
                        'Created_At' => $now,
                    ])->all()
                );
            }
        }, 5);

        $recipients = collect($mentionedUserIds);
        if ($parent && trim((string) $parent->User_ID) !== '') {
            $recipients->push(trim((string) $parent->User_ID));
        }
        $recipients = $recipients->filter(fn (string $recipient): bool => ! hash_equals($recipient, $this->userId($user)))
            ->unique()->values();
        $recipients = $this->notificationRecipients($announcementId, $recipients);
        $this->notifyInteraction($announcementId, $id, $user, $recipients, $parent !== null, $mentionedUserIds);

        return $id;
    }

    private function validatedParent(string $announcementId, ?string $parentCommentId): ?object
    {
        $parentCommentId = trim((string) $parentCommentId);
        if ($parentCommentId === '') {
            return null;
        }
        $this->requireSocialSchema();
        $parent = $this->connection()->table('ANNOUNCEMENT_COMMENT')
            ->where('Comment_ID', $parentCommentId)
            ->where('Announcement_ID', $announcementId)
            ->whereNull('Deleted_At')->first();
        if (! $parent) {
            throw new \InvalidArgumentException('Komentar yang dibalas tidak tersedia.');
        }
        if (trim((string) ($parent->Parent_Comment_ID ?? '')) !== '') {
            throw new \InvalidArgumentException('Balasan hanya dapat dibuat satu tingkat.');
        }

        return $parent;
    }

    /** @return list<string> */
    private function validatedMentions(array $announcement, mixed $user, array $mentionedUserIds): array
    {
        $ids = collect($mentionedUserIds)
            ->map(fn ($id): string => trim((string) $id))->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }
        $this->requireSocialSchema();
        if ($ids->count() > self::MAX_MENTIONS) {
            throw new \InvalidArgumentException('Maksimal '.self::MAX_MENTIONS.' mention per komentar.');
        }

        $rows = $this->connection()->table('MASTER_USER as users')
            ->leftJoin('MASTER_ROLE as roles', 'roles.Role_ID', '=', 'users.Role_ID')
            ->whereIn('users.User_ID', $ids)
            ->when($this->hasUserActiveColumn(), fn ($builder) => $builder->where('users.Is_Active', true))
            ->select('users.User_ID', 'users.Full_Name', 'users.Username', 'users.Role_ID', 'roles.Role_Name')->get();
        $allowed = $rows->filter(fn (object $candidate): bool => $this->candidateCanView($candidate, $announcement))
            ->pluck('User_ID')->map(fn ($id): string => (string) $id)->values();
        if ($allowed->count() !== $ids->count()
            || $ids->contains(fn (string $id): bool => ! $allowed->contains($id))) {
            throw new \InvalidArgumentException('Salah satu penerima mention tidak tersedia untuk pengumuman ini.');
        }

        return $ids->all();
    }

    private function candidateCanView(object $candidate, array $announcement): bool
    {
        return $this->visibility->canView(new GenericUser([
            'id' => $candidate->User_ID,
            'User_ID' => $candidate->User_ID,
            'Role_ID' => $candidate->Role_ID,
            'Role' => $candidate->Role_Name,
        ]), $announcement);
    }

    private function notifyInteraction(
        string $announcementId,
        string $commentId,
        mixed $actor,
        Collection $recipients,
        bool $isReply,
        array $mentionedUserIds,
    ): void {
        if ($recipients->isEmpty()) {
            return;
        }
        $actorId = $this->userId($actor);
        $actorName = (string) ($this->connection()->table('MASTER_USER')->where('User_ID', $actorId)->value('Full_Name') ?: 'Seorang pengguna');
        $mentionSet = collect($mentionedUserIds);
        foreach ($recipients as $recipientId) {
            $wasMentioned = $mentionSet->contains($recipientId);
            $title = $isReply && $wasMentioned ? 'Balasan dan mention baru'
                : ($isReply ? 'Balasan baru' : 'Mention baru');
            $message = $isReply && $wasMentioned
                ? $actorName.' membalas komentar dan menyebut Anda.'
                : ($isReply ? $actorName.' membalas komentar Anda.' : $actorName.' menyebut Anda dalam komentar.');
            try {
                $this->notifications->CreateNotification([
                    'Notification_ID' => 'NOTIF-ANN-'.strtoupper(substr(hash('sha256', $commentId.'|'.$recipientId), 0, 28)),
                    'User_ID' => $recipientId,
                    'Title' => $title,
                    'Message' => $message,
                    'Is_Read' => 'FALSE',
                    'Link' => route('announcement-feed.show', $announcementId, false).'#comment-'.$commentId,
                    'Reference_Type' => 'AnnouncementComment',
                    'Reference_ID' => $commentId,
                    'Created_By' => $actorId,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Announcement interaction notification failed', [
                    'comment_id' => $commentId,
                    'recipient_id' => $recipientId,
                    'exception' => get_class($e),
                ]);
            }
        }
    }

    private function notificationRecipients(string $announcementId, Collection $recipients): Collection
    {
        if ($recipients->isEmpty()) {
            return collect();
        }
        $announcement = $this->announcements->getById($announcementId);
        if (! $announcement) {
            return collect();
        }
        $rows = $this->connection()->table('MASTER_USER as users')
            ->leftJoin('MASTER_ROLE as roles', 'roles.Role_ID', '=', 'users.Role_ID')
            ->whereIn('users.User_ID', $recipients)
            ->when($this->hasUserActiveColumn(), fn ($builder) => $builder->where('users.Is_Active', true))
            ->select('users.User_ID', 'users.Role_ID', 'roles.Role_Name')->get();

        return $rows->filter(fn (object $candidate): bool => $this->candidateCanView($candidate, (array) $announcement))
            ->pluck('User_ID')->map(fn ($id): string => (string) $id)->values();
    }

    private function decorateRows(Collection $rows, mixed $user): void
    {
        if ($rows->isEmpty()) {
            return;
        }
        $isAdmin = $this->visibility->roleName($user) === 'ADMINISTRATOR';
        $userId = $this->userId($user);
        $avatarMap = $this->avatars->resolveMany($rows->map(fn ($row): array => [
            'User_ID' => $row->User_ID,
            'Full_Name' => $row->User_Name,
            'Role_ID' => $row->Role_ID,
        ]));
        $mentions = $this->mentionMap($rows->pluck('Comment_ID'));
        $rows->each(function ($row) use ($isAdmin, $userId, $avatarMap, $mentions): void {
            if ($row->Sticker_Path) {
                $row->Sticker_URL = Storage::disk('public')->url($row->Sticker_Path);
            }
            $row->Can_Delete = $isAdmin || hash_equals((string) $row->User_ID, $userId);
            $row->Avatar_URL = $avatarMap[$row->User_ID] ?? null;
            $row->Mentions = $mentions[$row->Comment_ID] ?? collect();
        });
    }

    /** @return array<string,Collection<int,object>> */
    private function mentionMap(iterable $commentIds): array
    {
        $ids = collect($commentIds)->filter()->unique()->values();
        if ($ids->isEmpty() || ! $this->socialSchemaReady()) {
            return [];
        }

        return $this->connection()->table('ANNOUNCEMENT_COMMENT_MENTION as mentions')
            ->leftJoin('MASTER_USER as users', 'users.User_ID', '=', 'mentions.Mentioned_User_ID')
            ->leftJoin('MASTER_ROLE as roles', 'roles.Role_ID', '=', 'users.Role_ID')
            ->whereIn('mentions.Comment_ID', $ids)
            ->select('mentions.Comment_ID', 'mentions.Mentioned_User_ID', 'users.Full_Name as User_Name', 'users.Username', 'roles.Role_Name')
            ->get()->groupBy('Comment_ID')->all();
    }

    private function socialSchemaReady(): bool
    {
        if ($this->socialSchemaAvailable !== null) {
            return $this->socialSchemaAvailable;
        }
        try {
            $schema = Schema::connection((string) config('wms_runtime.connection', config('database.default')));

            return $this->socialSchemaAvailable = $schema->hasTable('ANNOUNCEMENT_COMMENT_MENTION')
                && $schema->hasColumns('ANNOUNCEMENT_COMMENT', [
                    'Parent_Comment_ID', 'External_Media_Provider', 'External_Media_ID',
                    'External_Media_URL', 'External_Media_Preview_URL', 'External_Media_Alt',
                ]);
        } catch (\Throwable) {
            return $this->socialSchemaAvailable = false;
        }
    }

    private function requireSocialSchema(): void
    {
        if (! $this->socialSchemaReady()) {
            throw new \RuntimeException('Schema interaksi sosial announcement belum tersedia.');
        }
    }

    private function hasUserActiveColumn(): bool
    {
        return Schema::connection((string) config('wms_runtime.connection', config('database.default')))
            ->hasColumn('MASTER_USER', 'Is_Active');
    }

    private function externalGifsProviderName(): string
    {
        return $this->externalGifs->providerName();
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
