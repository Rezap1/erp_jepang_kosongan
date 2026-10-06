<?php

namespace App\Services\Academic;

use App\Interfaces\GoogleSheets\AnnouncementRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Support\ActorIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Shared scheduling, targeting and presentation rules for announcements. */
class AnnouncementService
{
    public const TIMEZONE = 'Asia/Jakarta';

    public const PRIORITIES = ['NORMAL', 'IMPORTANT', 'URGENT'];

    public const AUDIENCES = ['ALL_STUDENTS', 'CLASS'];

    public const STATUSES = ['DRAFT', 'PUBLISHED', 'ACTIVE', 'INACTIVE'];

    protected AnnouncementRepositoryInterface $repository;

    protected ?ClassRepositoryInterface $classRepository;

    protected ?AnnouncementVisibilityService $visibility;

    public function __construct(
        AnnouncementRepositoryInterface $repository,
        ?ClassRepositoryInterface $classRepository = null,
        ?AnnouncementVisibilityService $visibility = null,
    ) {
        $this->repository = $repository;
        $this->classRepository = $classRepository;
        $this->visibility = $visibility;
    }

    public function getAll(): Collection
    {
        $rows = $this->repository->fetchAll();
        $rows = $rows instanceof Collection ? $rows : collect($rows);
        if ($visibility = $this->visibilityService()) {
            return $visibility->withAudienceRelations($rows);
        }

        return $rows;
    }

    public function getById($id)
    {
        $row = $this->repository->findById((string) $id);
        if ($row && ($visibility = $this->visibilityService())) {
            return $visibility->withAudienceRelations([(array) $row])->first();
        }

        return $row;
    }

    public function generateId(): string
    {
        return $this->repository->generateNewId('ANC', 6);
    }

    /** Start is inclusive; expiry is exclusive and evaluated by the server clock. */
    public function getActiveAnnouncements(?string $role = null, ?string $classId = null, $now = null): Collection
    {
        $instant = $this->clock($now);
        $role = strtoupper(trim((string) $role));

        return $this->getAll()
            ->filter(function ($item) use ($role, $classId, $instant) {
                if (! $this->isActiveFlag($item['Is_Active'] ?? true)) {
                    return false;
                }
                $status = strtoupper(trim((string) ($item['Status'] ?? 'PUBLISHED')));
                if (! in_array($status, ['PUBLISHED', 'ACTIVE'], true)) {
                    return false;
                }

                $start = $this->dateFrom($item, ['Start_At', 'Publish_Date', 'Start_Date']);
                $expires = $this->dateFrom($item, ['Expires_At', 'Expired_Date', 'End_At', 'End_Date']);
                // New announcements require an expiry. Pre-V2 rows used the
                // historical optional Expired_Date contract and remain valid
                // indefinitely when that field was left empty.
                if ((! $expires && ! $this->isIndefiniteLegacy($item))
                    || ($start && $instant->lt($start))
                    || ($expires && $instant->gte($expires))) {
                    return false;
                }

                return $this->matchesAudience($item, $role, $classId);
            })
            ->sort(function ($a, $b) {
                $priority = $this->priorityRank($b) <=> $this->priorityRank($a);
                if ($priority !== 0) {
                    return $priority;
                }
                $aExpiry = $this->dateFrom($a, ['Expires_At', 'Expired_Date', 'End_At', 'End_Date']);
                $bExpiry = $this->dateFrom($b, ['Expires_At', 'Expired_Date', 'End_At', 'End_Date']);
                $expiryOrder = ($aExpiry?->getTimestamp() ?? PHP_INT_MAX) <=> ($bExpiry?->getTimestamp() ?? PHP_INT_MAX);
                if ($expiryOrder !== 0) {
                    return $expiryOrder;
                }
                $aStart = $this->dateFrom($a, ['Start_At', 'Publish_Date', 'Start_Date']);
                $bStart = $this->dateFrom($b, ['Start_At', 'Publish_Date', 'Start_Date']);
                $startOrder = ($bStart?->getTimestamp() ?? 0) <=> ($aStart?->getTimestamp() ?? 0);
                if ($startOrder !== 0) {
                    return $startOrder;
                }

                return strcmp((string) ($b['Created_At'] ?? ''), (string) ($a['Created_At'] ?? ''));
            })
            ->values();
    }

    /** Fail-closed detail authorization for students. */
    public function getVisibleForStudent(string $id, ?string $classId, $now = null): ?array
    {
        $row = $this->getById($id);
        if (! $row) {
            return null;
        }
        $visible = $this->getActiveAnnouncements('STUDENT', $classId, $now)
            ->contains(fn ($item) => (string) ($item['Announcement_ID'] ?? '') === (string) $id);

        return $visible ? (array) $row : null;
    }

    public function getActiveForUser(mixed $user, $now = null): Collection
    {
        $visibility = $this->visibilityService();
        if (! $visibility) {
            return collect();
        }

        return $visibility->visibleTo($user, $this->getActiveAnnouncements(null, null, $now));
    }

    public function getVisibleForUser(string $id, mixed $user, $now = null): ?array
    {
        $row = $this->getById($id);
        if (! $row || ! $this->isCurrentlyPublished((array) $row, $now)) {
            return null;
        }
        $visibility = $this->visibilityService();

        return $visibility && $visibility->canView($user, (array) $row) ? (array) $row : null;
    }

    public function presentationStatus(array $item, $now = null): string
    {
        if (! $this->isActiveFlag($item['Is_Active'] ?? true) || strtoupper(trim((string) ($item['Status'] ?? 'PUBLISHED'))) === 'INACTIVE') {
            return 'Nonaktif';
        }
        if (strtoupper(trim((string) ($item['Status'] ?? 'PUBLISHED'))) === 'DRAFT') {
            return 'Draf';
        }
        $instant = $this->clock($now);
        $start = $this->dateFrom($item, ['Start_At', 'Publish_Date', 'Start_Date']);
        $expires = $this->dateFrom($item, ['Expires_At', 'Expired_Date', 'End_At', 'End_Date']);
        if ($start && $instant->lt($start)) {
            return 'Belum Tayang';
        }
        if ($expires && $instant->gte($expires)) {
            return 'Berakhir';
        }

        return 'Aktif';
    }

    public function priorityLabel($priority): string
    {
        return match ($this->normalizePriority($priority)) {
            'URGENT' => 'Mendesak',
            'IMPORTANT' => 'Penting',
            default => 'Informasi',
        };
    }

    public function audienceLabel(array $item): string
    {
        if ($visibility = $this->visibilityService()) {
            return $visibility->audienceLabel($item);
        }

        return $this->audienceType($item) === 'CLASS' ? 'Kelas Tertentu' : 'Semua Siswa';
    }

    public function startAt(array $item): ?CarbonImmutable
    {
        return $this->dateFrom($item, ['Start_At', 'Publish_Date', 'Start_Date']);
    }

    public function expiresAt(array $item): ?CarbonImmutable
    {
        return $this->dateFrom($item, ['Expires_At', 'Expired_Date', 'End_At', 'End_Date']);
    }

    public function create(array $data)
    {
        $payload = $this->preparePayload($data);
        $canonical = $payload['__Audience_Canonical'] ?? null;
        unset($payload['__Audience_Canonical']);
        $payload['Announcement_ID'] ??= $this->generateId();
        $payload['Created_At'] ??= $this->clock()->format('Y-m-d H:i:s');
        $payload['Created_By'] = $this->actor();
        $payload['Updated_At'] ??= $payload['Created_At'];
        $payload['Updated_By'] = $payload['Created_By'];
        $write = function () use ($payload, $canonical) {
            $result = $this->repository->create($payload);
            if ($canonical && ($visibility = $this->visibilityService())) {
                $visibility->syncAudience((string) $payload['Announcement_ID'], $canonical);
            }

            return $result;
        };
        $visibility = $this->visibilityService();
        $result = $visibility?->relationsAvailable()
            ? DB::connection(config('wms_runtime.connection'))->transaction($write, 5)
            : $write();
        $this->clearRepositoryCache();

        return $result;
    }

    public function update($id, array $data)
    {
        $existing = $this->getById($id);
        if (! $existing) {
            throw new \InvalidArgumentException('Pengumuman tidak ditemukan.');
        }
        $payload = $this->preparePayload($data, (array) $existing);
        $canonical = $payload['__Audience_Canonical'] ?? null;
        unset($payload['__Audience_Canonical']);
        $payload['Created_By'] = $existing['Created_By'] ?? '';
        $payload['Created_At'] = $existing['Created_At'] ?? '';
        $payload['Updated_At'] = $this->clock()->format('Y-m-d H:i:s');
        $payload['Updated_By'] = $this->actor();
        $write = function () use ($id, $payload, $canonical) {
            $result = $this->repository->update((string) $id, $payload);
            if ($canonical && ($visibility = $this->visibilityService())) {
                $visibility->syncAudience((string) $id, $canonical);
            }

            return $result;
        };
        $visibility = $this->visibilityService();
        $result = $visibility?->relationsAvailable()
            ? DB::connection(config('wms_runtime.connection'))->transaction($write, 5)
            : $write();
        $this->clearRepositoryCache();
        if (method_exists($this->repository, 'findByIdFresh') && ! $this->repository->findByIdFresh((string) $id)) {
            throw new \RuntimeException('Perubahan pengumuman belum dapat dikonfirmasi.');
        }

        return $result;
    }

    public function delete($id)
    {
        if (! $this->getById($id)) {
            throw new \InvalidArgumentException('Pengumuman tidak ditemukan.');
        }
        $result = $this->repository->update((string) $id, [
            'Is_Active' => 'FALSE',
            'Status' => 'INACTIVE',
            'Updated_At' => $this->clock()->format('Y-m-d H:i:s'),
            'Updated_By' => $this->actor(),
        ]);
        $this->clearRepositoryCache();

        return $result;
    }

    public function normalizePriority($priority): string
    {
        return match (strtoupper(trim((string) $priority))) {
            'URGENT', 'HIGH', 'TINGGI', 'MENDESAK' => 'URGENT',
            'IMPORTANT', 'MEDIUM', 'PENTING' => 'IMPORTANT',
            default => 'NORMAL',
        };
    }

    public function normalizeAudience($audience): string
    {
        return match (strtoupper(trim((string) $audience))) {
            'CLASS', 'KELAS', 'CLASSROOM' => 'CLASS',
            default => 'ALL_STUDENTS',
        };
    }

    /** Validate canonical and legacy payloads before they reach the sheet. */
    public function validatePayload(array $data): array
    {
        $title = trim((string) ($data['Title'] ?? ''));
        $message = trim((string) ($data['Message'] ?? $data['Content'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('Judul pengumuman wajib diisi.');
        }
        if ($message === '') {
            throw new \InvalidArgumentException('Isi pengumuman wajib diisi.');
        }

        $priorityRaw = strtoupper(trim((string) ($data['Priority'] ?? 'NORMAL')));
        if (! in_array($priorityRaw, ['NORMAL', 'IMPORTANT', 'URGENT', 'LOW', 'MEDIUM', 'HIGH', 'PENTING', 'MENDESAK'], true)) {
            throw new \InvalidArgumentException('Prioritas pengumuman tidak valid.');
        }
        $priority = $this->normalizePriority($priorityRaw);
        $audienceRaw = $data['Audience_Type'] ?? $data['Target_Audience'] ?? $data['Target_Role'] ?? (! empty(trim((string) ($data['Class_ID'] ?? ''))) ? 'CLASS' : 'ALL_STUDENTS');
        $audienceRawUpper = strtoupper(trim((string) $audienceRaw));
        $legacyAudienceAllowed = ['ALL', 'ALL_USERS', 'STUDENT', 'TEACHER', 'ALL_STUDENTS', 'CLASS', 'KELAS', 'CLASSROOM', 'ALL_ROLES', 'ROLE', 'SELECTED_ROLES'];
        if (! in_array($audienceRawUpper, array_merge(self::AUDIENCES, $legacyAudienceAllowed), true)) {
            throw new \InvalidArgumentException('Target audiens tidak valid.');
        }
        $audience = $this->normalizeAudience($audienceRaw);
        $classId = trim((string) ($data['Audience_ID'] ?? $data['Class_ID'] ?? $data['Target_ID'] ?? ''));
        if ($audience === 'CLASS') {
            if ($classId === '') {
                throw new \InvalidArgumentException('Kelas wajib dipilih untuk target kelas tertentu.');
            }
            if (! $this->classExists($classId)) {
                throw new \InvalidArgumentException('Kelas yang dipilih tidak tersedia.');
            }
        }

        $startRaw = $data['Start_At'] ?? $data['Publish_Date'] ?? $data['Start_Date'] ?? null;
        $expiryRaw = $data['Expires_At'] ?? $data['Expired_Date'] ?? $data['End_At'] ?? $data['End_Date'] ?? null;
        $start = $this->blankDate($startRaw) ? $this->clock() : $this->parseDate($startRaw);
        if ($this->blankDate($expiryRaw)) {
            throw new \InvalidArgumentException('Tanggal dan waktu berakhir wajib diisi.');
        }
        $expires = $this->parseDate($expiryRaw);
        if ($expires->lessThanOrEqualTo($start)) {
            throw new \InvalidArgumentException('Waktu berakhir harus setelah waktu mulai.');
        }

        $status = strtoupper(trim((string) ($data['Status'] ?? 'PUBLISHED')));
        if (! in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Status pengumuman tidak valid.');
        }

        $canonical = null;
        if (in_array($audienceRawUpper, ['ALL_ROLES', 'ROLE', 'SELECTED_ROLES'], true)) {
            $visibility = $this->visibilityService();
            if (! $visibility) {
                throw new \InvalidArgumentException('Target audiens tidak dapat diverifikasi.');
            }
            $canonical = $visibility->canonicalize($data);
        }

        return compact('title', 'message', 'priority', 'audience', 'classId', 'start', 'expires', 'status', 'canonical');
    }

    protected function preparePayload(array $data, ?array $existing = null): array
    {
        $n = $this->validatePayload($data);
        $payload = $data;
        unset($payload['Target_Roles'], $payload['Target_Class_IDs']);
        $payload['Title'] = $n['title'];
        $payload['Content'] = $n['message'];
        $payload['Message'] = $n['message'];
        $payload['Priority'] = $n['priority'];
        if ($n['canonical']) {
            $canonicalColumns = array_filter(
                $n['canonical'],
                fn (string $key): bool => ! str_starts_with($key, 'Audience_Role_') && $key !== 'Audience_Class_IDs',
                ARRAY_FILTER_USE_KEY
            );
            $payload = array_merge($payload, $canonicalColumns);
            $payload['__Audience_Canonical'] = $n['canonical'];
            $payload['Audience_ID'] = '';
            $payload['Target_Audience'] = $n['canonical']['Audience_Type'];
        } else {
            $payload['Audience_Type'] = $n['audience'];
            $payload['Audience_ID'] = $n['audience'] === 'CLASS' ? $n['classId'] : '';
            $payload['Target_Audience'] = $n['audience'];
            $payload['Target_Role'] = $n['audience'] === 'CLASS' ? 'CLASS' : 'ALL_STUDENTS';
            $payload['Target_ID'] = $n['audience'] === 'CLASS' ? $n['classId'] : '';
            $payload['Target_Batch_ID'] = null;
            $payload['Target_Class_ID'] = null;
        }
        $payload['Start_At'] = $n['start']->format('Y-m-d H:i:s');
        $payload['Expires_At'] = $n['expires']->format('Y-m-d H:i:s');
        $payload['Publish_Date'] = $payload['Start_At'];
        $payload['Expired_Date'] = $payload['Expires_At'];
        $payload['Status'] = $n['status'];
        $payload['Is_Active'] = $data['Is_Active']
            ?? ($n['status'] === 'INACTIVE'
                ? 'FALSE'
                : (($existing && strtoupper((string) ($existing['Status'] ?? '')) === 'INACTIVE' && array_key_exists('Status', $data))
                    ? 'TRUE'
                    : ($existing['Is_Active'] ?? 'TRUE')));

        return $payload;
    }

    protected function matchesAudience(array $item, string $role, ?string $classId): bool
    {
        $v2Type = strtoupper(trim((string) ($item['Audience_Type'] ?? '')));
        if ($v2Type === 'ALL_ROLES') {
            return true;
        }
        if ($v2Type === 'ROLE') {
            $targetRole = strtoupper(trim((string) ($item['Target_Role'] ?? '')));
            if ($role === '' || $role !== $targetRole) {
                return $role === '';
            }
            $targetClass = trim((string) ($item['Target_Class_ID'] ?? ''));

            return $targetClass === '' || ($classId !== null && $classId !== '' && hash_equals($targetClass, $classId));
        }

        $audience = $this->audienceType($item);
        $legacyRole = strtoupper(trim((string) ($item['Target_Role'] ?? '')));
        if ($role === '') {
            return true;
        }
        if ($role === 'STUDENT') {
            if ($audience === 'CLASS') {
                $targetClass = trim((string) ($item['Audience_ID'] ?? $item['Class_ID'] ?? $item['Target_ID'] ?? ''));

                return $classId !== null && $classId !== '' && hash_equals($targetClass, (string) $classId);
            }

            return in_array($legacyRole, ['', 'ALL', 'ALL_USERS', 'STUDENT', 'ALL_STUDENTS'], true);
        }
        if ($legacyRole === '' || in_array($legacyRole, ['ALL', 'ALL_USERS'], true)) {
            return true;
        }

        return $legacyRole === $role;
    }

    protected function audienceType(array $item): string
    {
        return $this->normalizeAudience($item['Audience_Type'] ?? $item['Target_Audience'] ?? $item['Target_Role'] ?? (! empty(trim((string) ($item['Class_ID'] ?? ''))) ? 'CLASS' : 'ALL_STUDENTS'));
    }

    protected function priorityRank(array $item): int
    {
        return match ($this->normalizePriority($item['Priority'] ?? 'NORMAL')) {
            'URGENT' => 3,
            'IMPORTANT' => 2,
            default => 1,
        };
    }

    protected function dateFrom(array $item, array $keys): ?CarbonImmutable
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $item) || $item[$key] === null) {
                continue;
            }
            if ($item[$key] instanceof \DateTimeInterface) {
                return CarbonImmutable::instance($item[$key])->setTimezone(self::TIMEZONE);
            }
            if (! (is_scalar($item[$key]) || $item[$key] instanceof \Stringable)) {
                return null;
            }
            if (trim((string) $item[$key]) === '') {
                continue;
            }
            try {
                return $this->parseDate($item[$key]);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    protected function parseDate($value): CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone(self::TIMEZONE);
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            throw new \InvalidArgumentException('Tanggal dan waktu tidak boleh kosong.');
        }
        try {
            return CarbonImmutable::parse($raw, self::TIMEZONE)->setTimezone(self::TIMEZONE);
        } catch (\Throwable) {
            throw new \InvalidArgumentException('Format tanggal dan waktu tidak valid. Gunakan waktu Indonesia (WIB).');
        }
    }

    protected function clock($now = null): CarbonImmutable
    {
        return $now instanceof \DateTimeInterface
            ? CarbonImmutable::instance($now)->setTimezone(self::TIMEZONE)
            : CarbonImmutable::now(self::TIMEZONE);
    }

    protected function blankDate($value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if ($value instanceof \DateTimeInterface) {
            return false;
        }
        if ($value instanceof \Stringable || is_scalar($value)) {
            return trim((string) $value) === '';
        }

        return false;
    }

    protected function isActiveFlag($value): bool
    {
        return $value === true || in_array(strtoupper(trim((string) $value)), ['TRUE', '1', 'YES', 'Y', 'ON'], true);
    }

    protected function isCurrentlyPublished(array $item, $now = null): bool
    {
        if (! $this->isActiveFlag($item['Is_Active'] ?? true)) {
            return false;
        }
        if (! in_array(strtoupper(trim((string) ($item['Status'] ?? 'PUBLISHED'))), ['PUBLISHED', 'ACTIVE'], true)) {
            return false;
        }
        $instant = $this->clock($now);
        $start = $this->dateFrom($item, ['Start_At', 'Publish_Date', 'Start_Date']);
        $expires = $this->dateFrom($item, ['Expires_At', 'Expired_Date', 'End_At', 'End_Date']);

        if (! $expires && ! $this->isIndefiniteLegacy($item)) {
            return false;
        }

        return (! $start || ! $instant->lt($start)) && (! $expires || $instant->lt($expires));
    }

    protected function isIndefiniteLegacy(array $item): bool
    {
        return $this->visibilityService()?->isPreV2Legacy($item) === true;
    }

    protected function visibilityService(): ?AnnouncementVisibilityService
    {
        if ($this->visibility) {
            return $this->visibility;
        }
        try {
            return app()->bound(AnnouncementVisibilityService::class)
                ? $this->visibility = app(AnnouncementVisibilityService::class)
                : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function classExists(string $classId): bool
    {
        try {
            if ($this->classRepository) {
                $class = $this->classRepository->findById($classId);

                return (bool) $class && $this->isActiveFlag($class['Is_Active'] ?? true);
            }
            if (function_exists('app') && app()->bound(ClassRepositoryInterface::class)) {
                $class = app(ClassRepositoryInterface::class)->findById($classId);

                return (bool) $class && $this->isActiveFlag($class['Is_Active'] ?? true);
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    protected function actor(): string
    {
        return Auth::check() ? ActorIdentity::required() : 'SYSTEM';
    }

    protected function clearRepositoryCache(): void
    {
        if (method_exists($this->repository, 'clearCache')) {
            $this->repository->clearCache();
        }
    }
}
