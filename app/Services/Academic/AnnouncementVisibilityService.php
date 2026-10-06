<?php

namespace App\Services\Academic;

use App\Exceptions\AnnouncementAudienceValidationException;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Core\RoleService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Single source of truth for Announcement audience persistence and visibility. */
final class AnnouncementVisibilityService
{
    public const ALL_ROLES = 'ALL_ROLES';

    public const SELECTED_ROLES = 'SELECTED_ROLES';

    public const ALL_STUDENTS = 'ALL_STUDENTS';

    public const BATCH_ALL_CLASSES = 'BATCH_ALL_CLASSES';

    public const BATCH_SELECTED_CLASSES = 'BATCH_SELECTED_CLASSES';

    private ?bool $relationsReady = null;

    public function __construct(
        private RoleService $roles,
        private StudentRepositoryInterface $students,
        private BatchRepositoryInterface $batches,
        private ClassRepositoryInterface $classes,
    ) {}

    public function visibleTo(mixed $user, iterable $announcements): Collection
    {
        return $this->withAudienceRelations($announcements)
            ->filter(fn ($announcement): bool => $this->canView($user, (array) $announcement))
            ->values();
    }

    public function canView(mixed $user, array $announcement): bool
    {
        $roleName = $this->roleName($user);
        if ($roleName === '') {
            return false;
        }

        $target = $this->target($announcement);
        if ($target['audience_type'] === self::ALL_ROLES) {
            return true;
        }

        $roleId = trim((string) ($user->Role_ID ?? ''));
        $selected = ($roleId !== '' && in_array($roleId, $target['role_ids'], true))
            || in_array($roleName, $target['role_names'], true);
        if (! $selected) {
            return false;
        }
        if ($roleName !== 'STUDENT') {
            return true;
        }
        if ($target['student_scope'] === self::ALL_STUDENTS) {
            return true;
        }

        $student = $this->studentFor($user);
        if (! $student) {
            return false;
        }
        $studentBatch = trim((string) ($student['Batch_ID'] ?? ''));
        if ($target['batch_id'] === '' || ! hash_equals($target['batch_id'], $studentBatch)) {
            return false;
        }
        if ($target['student_scope'] === self::BATCH_ALL_CLASSES) {
            return true;
        }

        $studentClass = trim((string) ($student['Class_ID'] ?? ''));

        return $studentClass !== '' && in_array($studentClass, $target['class_ids'], true);
    }

    /**
     * @return array{
     *   Audience_Type:string,Target_Role:?string,Target_Batch_ID:?string,
     *   Target_Class_ID:?string,Target_ID:?string,Student_Scope:?string,
     *   Audience_Role_IDs:list<string>,Audience_Role_Names:list<string>,Audience_Class_IDs:list<string>
     * }
     */
    public function canonicalize(array $data): array
    {
        $type = strtoupper(trim((string) ($data['Audience_Type'] ?? '')));

        if (in_array($type, ['ALL_STUDENTS', 'CLASS'], true)) {
            $legacyClass = $type === 'CLASS'
                ? trim((string) ($data['Audience_ID'] ?? $data['Target_ID'] ?? $data['Class_ID'] ?? ''))
                : '';
            $data['Target_Role'] = 'STUDENT';
            $data['Target_Class_ID'] = $legacyClass;
            $type = self::SELECTED_ROLES;
        } elseif ($type === 'ROLE') {
            $type = self::SELECTED_ROLES;
        }

        if ($type === self::ALL_ROLES) {
            return $this->emptyCanonical(self::ALL_ROLES);
        }
        if ($type !== self::SELECTED_ROLES) {
            throw new AnnouncementAudienceValidationException('Audience_Type', 'Tujuan pengumuman tidak valid.');
        }

        $available = collect($this->availableRoles());
        $requested = $data['Target_Roles'] ?? null;
        if (! is_array($requested)) {
            $legacy = trim((string) ($data['Target_Role'] ?? ''));
            $requested = $legacy === '' ? [] : [$legacy];
        }
        $requested = array_values(array_unique(array_filter(array_map(
            fn ($value): string => is_scalar($value) ? trim((string) $value) : '',
            $requested
        ))));
        if ($requested === []) {
            throw new AnnouncementAudienceValidationException('Target_Roles', 'Pilih minimal satu role penerima.');
        }

        $selectedRoles = [];
        foreach ($requested as $identifier) {
            $role = $available->first(fn (array $candidate): bool => strcasecmp($candidate['id'], $identifier) === 0
                || strcasecmp($candidate['name'], $identifier) === 0
            );
            if (! $role) {
                throw new AnnouncementAudienceValidationException('Target_Roles', 'Role penerima tidak valid atau tidak aktif.');
            }
            $selectedRoles[$role['id']] = $role;
        }
        $selectedRoles = array_values($selectedRoles);
        $roleIds = array_column($selectedRoles, 'id');
        $roleNames = array_column($selectedRoles, 'name');
        if (! in_array('STUDENT', $roleNames, true)) {
            return $this->selectedCanonical($roleIds, $roleNames, null, null, []);
        }

        $batchId = trim((string) ($data['Target_Batch_ID'] ?? ''));
        $classIds = $data['Target_Class_IDs'] ?? null;
        if (! is_array($classIds)) {
            $legacyClass = trim((string) ($data['Target_Class_ID'] ?? $data['Audience_ID'] ?? ''));
            $classIds = $legacyClass === '' ? [] : [$legacyClass];
        }
        $classIds = array_values(array_unique(array_filter(array_map(
            fn ($value): string => is_scalar($value) ? trim((string) $value) : '',
            $classIds
        ))));

        $scope = strtoupper(trim((string) ($data['Student_Scope'] ?? '')));
        if ($scope === '') {
            $scope = $classIds !== [] ? self::BATCH_SELECTED_CLASSES
                : ($batchId !== '' ? self::BATCH_ALL_CLASSES : self::ALL_STUDENTS);
        }
        if (! in_array($scope, [self::ALL_STUDENTS, self::BATCH_ALL_CLASSES, self::BATCH_SELECTED_CLASSES], true)) {
            throw new AnnouncementAudienceValidationException('Student_Scope', 'Target siswa tidak valid.');
        }
        if ($scope === self::ALL_STUDENTS) {
            return $this->selectedCanonical($roleIds, $roleNames, self::ALL_STUDENTS, null, []);
        }
        if ($batchId === '') {
            throw new AnnouncementAudienceValidationException('Target_Batch_ID', 'Pilih batch tujuan siswa.');
        }
        if (! $this->activeBatch($batchId)) {
            throw new AnnouncementAudienceValidationException('Target_Batch_ID', 'Batch yang dipilih tidak tersedia.');
        }
        if ($scope === self::BATCH_ALL_CLASSES) {
            return $this->selectedCanonical($roleIds, $roleNames, self::BATCH_ALL_CLASSES, $batchId, []);
        }
        if ($classIds === []) {
            throw new AnnouncementAudienceValidationException('Target_Class_IDs', 'Pilih minimal satu kelas.');
        }
        foreach ($classIds as $classId) {
            $class = $this->activeClass($classId);
            if (! $class) {
                throw new AnnouncementAudienceValidationException('Target_Class_IDs', 'Kelas yang dipilih tidak tersedia.');
            }
            if (! hash_equals($batchId, trim((string) ($class['Batch_ID'] ?? '')))) {
                throw new AnnouncementAudienceValidationException('Target_Class_IDs', 'Kelas yang dipilih tidak termasuk dalam batch tersebut.');
            }
        }

        return $this->selectedCanonical($roleIds, $roleNames, self::BATCH_SELECTED_CLASSES, $batchId, $classIds);
    }

    public function formState(array $announcement): array
    {
        $row = $this->withAudienceRelations([$announcement])->first() ?? $announcement;
        $target = $this->target((array) $row);

        return [
            'audience' => $target['audience_type'],
            'roles' => $target['role_ids'],
            'student_scope' => $target['student_scope'],
            'batch' => $target['batch_id'],
            'classes' => $target['class_ids'],
        ];
    }

    /** @return list<array{id:string,name:string,value:string,label:string}> */
    public function availableRoles(): array
    {
        return collect($this->roles->getAllRoles())
            ->filter(fn ($row): bool => $this->active($row['Is_Active'] ?? true))
            ->map(function ($row): array {
                $id = trim((string) ($row['Role_ID'] ?? ''));
                $name = strtoupper(trim((string) ($row['Role_Name'] ?? '')));

                return ['id' => $id, 'name' => $name, 'value' => $id, 'label' => ucwords(strtolower($name))];
            })
            ->filter(fn (array $role): bool => $role['id'] !== '' && $role['name'] !== '' && $role['name'] !== 'MASTER')
            ->unique('id')->values()->all();
    }

    public function availableRoleNames(): array
    {
        return array_column($this->availableRoles(), 'name');
    }

    /** @return list<array{Class_ID:string,Class_Name:string,Batch_ID:string}> */
    public function classesForBatch(string $batchId): array
    {
        $batchId = trim($batchId);
        if ($batchId === '' || ! $this->activeBatch($batchId)) {
            return [];
        }

        return collect($this->classes->fetchAll())
            ->map(fn ($row): array => (array) $row)
            ->filter(fn (array $row): bool => $this->active($row['Is_Active'] ?? true)
                && hash_equals($batchId, trim((string) ($row['Batch_ID'] ?? '')))
            )
            ->map(fn (array $row): array => [
                'Class_ID' => trim((string) ($row['Class_ID'] ?? '')),
                'Class_Name' => trim((string) ($row['Class_Name'] ?? $row['Class_Code'] ?? $row['Class_ID'] ?? '')),
                'Batch_ID' => $batchId,
            ])
            ->filter(fn (array $row): bool => $row['Class_ID'] !== '')
            ->sortBy('Class_Name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    public function studentFor(mixed $user): ?array
    {
        $userId = $this->userId($user);
        if ($userId === '') {
            return null;
        }

        $student = collect($this->students->fetchAll())->first(
            fn ($row): bool => strcasecmp(trim((string) ($row['User_ID'] ?? '')), $userId) === 0
                && $this->active($row['Is_Active'] ?? true)
        );

        return $student ? (array) $student : null;
    }

    public function roleName(mixed $user): string
    {
        $fallback = strtoupper(trim((string) ($user->Role ?? $user->Role_Name ?? '')));
        $roleId = trim((string) ($user->Role_ID ?? ''));
        if ($roleId === '') {
            return $fallback;
        }
        try {
            $role = $this->roles->getRoleById($roleId);
            if (! is_array($role)) {
                return '';
            }

            return $this->active($role['Is_Active'] ?? true)
                ? strtoupper(trim((string) ($role['Role_Name'] ?? $fallback)))
                : '';
        } catch (\Throwable) {
            return '';
        }
    }

    public function audienceLabel(array $announcement): string
    {
        $target = $this->target($announcement);
        if ($target['audience_type'] === self::ALL_ROLES) {
            return 'Semua Role';
        }

        $labels = array_map(fn (string $role): string => ucwords(strtolower($role)), $target['role_names']);
        if (in_array('STUDENT', $target['role_names'], true)) {
            $studentLabel = 'Student • Semua Siswa';
            if ($target['student_scope'] !== self::ALL_STUDENTS) {
                $batchLabel = trim((string) ($announcement['_Audience_Batch_Name'] ?? ''));
                if ($batchLabel === '') {
                    $batch = $this->batches->findById($target['batch_id']);
                    $batchLabel = (string) ($batch['Batch_Name'] ?? $batch['Batch_Code'] ?? $target['batch_id']);
                }
                $studentLabel = 'Student • '.$batchLabel;
                $studentLabel .= $target['student_scope'] === self::BATCH_SELECTED_CLASSES
                    ? ' • '.count($target['class_ids']).' Kelas'
                    : ' • Semua Kelas';
            }
            $labels = array_values(array_filter($labels, fn (string $label): bool => strtoupper($label) !== 'STUDENT'));
            $labels[] = $studentLabel;
        }

        return implode(', ', $labels);
    }

    public function relationsAvailable(): bool
    {
        if ($this->relationsReady !== null) {
            return $this->relationsReady;
        }
        try {
            $schema = $this->connection()->getSchemaBuilder();

            return $this->relationsReady = $schema->hasTable('ANNOUNCEMENT_AUDIENCE_ROLE')
                && $schema->hasTable('ANNOUNCEMENT_AUDIENCE_CLASS');
        } catch (\Throwable) {
            return $this->relationsReady = false;
        }
    }

    public function syncAudience(string $announcementId, array $canonical): void
    {
        if (! $this->relationsAvailable()) {
            return;
        }
        $connection = $this->connection();
        $connection->table('ANNOUNCEMENT_AUDIENCE_CLASS')->where('Announcement_ID', $announcementId)->delete();
        $connection->table('ANNOUNCEMENT_AUDIENCE_ROLE')->where('Announcement_ID', $announcementId)->delete();
        if (($canonical['Audience_Type'] ?? '') === self::ALL_ROLES) {
            return;
        }

        $createdAt = now()->format('Y-m-d H:i:s.u');
        $roleRows = collect($canonical['Audience_Role_IDs'] ?? [])->map(fn (string $roleId): array => [
            'Announcement_ID' => $announcementId,
            'Role_ID' => $roleId,
            'Created_At' => $createdAt,
        ])->all();
        if ($roleRows !== []) {
            $connection->table('ANNOUNCEMENT_AUDIENCE_ROLE')->insert($roleRows);
        }
        $classRows = collect($canonical['Audience_Class_IDs'] ?? [])->map(fn (string $classId): array => [
            'Announcement_ID' => $announcementId,
            'Batch_ID' => $canonical['Target_Batch_ID'],
            'Class_ID' => $classId,
            'Created_At' => $createdAt,
        ])->all();
        if ($classRows !== []) {
            $connection->table('ANNOUNCEMENT_AUDIENCE_CLASS')->insert($classRows);
        }
    }

    public function withAudienceRelations(iterable $announcements): Collection
    {
        $items = collect($announcements)->map(fn ($row): array => (array) $row)->values();
        if ($items->isEmpty() || ! $this->relationsAvailable()) {
            return $items;
        }
        if ($items->every(fn (array $row): bool => array_key_exists('_Audience_Roles', $row) && array_key_exists('_Audience_Classes', $row))) {
            return $items;
        }
        $ids = $items->pluck('Announcement_ID')->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return $items;
        }

        $roles = $this->connection()->table('ANNOUNCEMENT_AUDIENCE_ROLE as audience')
            ->join('MASTER_ROLE as roles', 'roles.Role_ID', '=', 'audience.Role_ID')
            ->whereIn('audience.Announcement_ID', $ids)
            ->select('audience.Announcement_ID', 'audience.Role_ID', 'roles.Role_Name')
            ->get()->groupBy('Announcement_ID');
        $classes = $this->connection()->table('ANNOUNCEMENT_AUDIENCE_CLASS')
            ->whereIn('Announcement_ID', $ids)->get()->groupBy('Announcement_ID');
        $batchIds = $items->pluck('Target_Batch_ID')->filter()->unique()->values();
        $batchNames = $batchIds->isEmpty() ? collect() : $this->connection()->table('MASTER_BATCH')
            ->whereIn('Batch_ID', $batchIds)->get()->mapWithKeys(fn ($row): array => [
                $row->Batch_ID => $row->Batch_Name ?? $row->Batch_Code ?? $row->Batch_ID,
            ]);

        return $items->map(function (array $row) use ($roles, $classes, $batchNames): array {
            $id = $row['Announcement_ID'] ?? '';
            $row['_Audience_Roles'] = collect($roles->get($id, collect()))->map(fn ($item): array => (array) $item)->all();
            $row['_Audience_Classes'] = collect($classes->get($id, collect()))->map(fn ($item): array => (array) $item)->all();
            $row['_Audience_Batch_Name'] = $batchNames->get($row['Target_Batch_ID'] ?? '');

            return $row;
        });
    }

    private function target(array $announcement): array
    {
        $type = strtoupper(trim((string) ($announcement['Audience_Type'] ?? '')));
        $legacyRole = strtoupper(trim((string) ($announcement['Target_Role'] ?? '')));
        if ($type === self::ALL_ROLES || in_array($legacyRole, ['ALL', 'ALL_USERS'], true)) {
            return $this->emptyTarget(self::ALL_ROLES);
        }

        $relations = $announcement['_Audience_Roles'] ?? null;
        if ($relations === null && $type === self::SELECTED_ROLES && isset($announcement['Announcement_ID']) && $this->relationsAvailable()) {
            $enriched = $this->withAudienceRelations([$announcement])->first();
            if ($enriched && array_key_exists('_Audience_Roles', $enriched)) {
                return $this->target($enriched);
            }
        }
        if (is_array($relations) && ($relations !== [] || $type === self::SELECTED_ROLES)) {
            $roleIds = collect($relations)->pluck('Role_ID')->map(fn ($id): string => trim((string) $id))->filter()->values()->all();
            $roleNames = collect($relations)->pluck('Role_Name')->map(fn ($name): string => strtoupper(trim((string) $name)))->filter()->values()->all();
        } elseif ($type !== self::SELECTED_ROLES) {
            if ($type === 'ALL_STUDENTS' || in_array($legacyRole, ['', 'ALL_STUDENTS'], true)) {
                $legacyRole = 'STUDENT';
            }
            if ($type === 'CLASS' || $legacyRole === 'CLASS') {
                $legacyRole = 'STUDENT';
            }
            $roleNames = $legacyRole === '' ? [] : [$legacyRole];
            $roleIds = collect($this->availableRoles())->whereIn('name', $roleNames)->pluck('id')->values()->all();
        } else {
            $roleIds = [];
            $roleNames = [];
        }

        $batchId = trim((string) ($announcement['Target_Batch_ID'] ?? ''));
        $classRelations = $announcement['_Audience_Classes'] ?? null;
        if (is_array($classRelations)) {
            $classIds = collect($classRelations)->pluck('Class_ID')->map(fn ($id): string => trim((string) $id))->filter()->values()->all();
        } else {
            $legacyClass = trim((string) ($announcement['Target_Class_ID'] ?? $announcement['Audience_ID'] ?? $announcement['Target_ID'] ?? ''));
            $classIds = $legacyClass === '' ? [] : [$legacyClass];
        }
        if ($batchId === '' && $classIds !== []) {
            $legacyClass = $this->activeClass($classIds[0]);
            $batchId = trim((string) ($legacyClass['Batch_ID'] ?? ''));
        }
        $scope = strtoupper(trim((string) ($announcement['Student_Scope'] ?? '')));
        if ($scope === '') {
            $scope = $classIds !== [] ? self::BATCH_SELECTED_CLASSES
                : ($batchId !== '' ? self::BATCH_ALL_CLASSES : self::ALL_STUDENTS);
        }

        return [
            'audience_type' => self::SELECTED_ROLES,
            'role_ids' => $roleIds,
            'role_names' => $roleNames,
            'student_scope' => $scope,
            'batch_id' => $batchId,
            'class_ids' => $classIds,
        ];
    }

    private function emptyTarget(string $type): array
    {
        return [
            'audience_type' => $type,
            'role_ids' => [],
            'role_names' => [],
            'student_scope' => '',
            'batch_id' => '',
            'class_ids' => [],
        ];
    }

    private function emptyCanonical(string $type): array
    {
        return [
            'Audience_Type' => $type,
            'Target_Role' => null,
            'Target_Batch_ID' => null,
            'Target_Class_ID' => null,
            'Target_ID' => null,
            'Student_Scope' => null,
            'Audience_Role_IDs' => [],
            'Audience_Role_Names' => [],
            'Audience_Class_IDs' => [],
        ];
    }

    private function selectedCanonical(array $roleIds, array $roleNames, ?string $scope, ?string $batchId, array $classIds): array
    {
        return [
            'Audience_Type' => self::SELECTED_ROLES,
            'Target_Role' => $roleNames[0] ?? null,
            'Target_Batch_ID' => $batchId,
            'Target_Class_ID' => $classIds[0] ?? null,
            'Target_ID' => $classIds[0] ?? $batchId,
            'Student_Scope' => $scope,
            'Audience_Role_IDs' => array_values($roleIds),
            'Audience_Role_Names' => array_values($roleNames),
            'Audience_Class_IDs' => array_values($classIds),
        ];
    }

    private function activeBatch(string $id): ?array
    {
        $row = $this->batches->findById($id);

        return $row && $this->active($row['Is_Active'] ?? true) ? (array) $row : null;
    }

    private function activeClass(string $id): ?array
    {
        $row = $this->classes->findById($id);

        return $row && $this->active($row['Is_Active'] ?? true) ? (array) $row : null;
    }

    private function active(mixed $value): bool
    {
        return $value === true || in_array(strtoupper(trim((string) $value)), ['TRUE', '1', 'YES', 'ACTIVE', 'ON'], true);
    }

    private function userId(mixed $user): string
    {
        return trim((string) ($user->User_ID ?? (method_exists($user, 'getAuthIdentifier') ? $user->getAuthIdentifier() : '')));
    }

    private function connection(): ConnectionInterface
    {
        return DB::connection(config('wms_runtime.connection'));
    }
}
