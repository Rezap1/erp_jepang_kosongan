<?php

namespace App\Services\Academic;

use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Core\RoleService;
use Illuminate\Support\Collection;

/** Single source of truth for Announcement V2 audience resolution. */
final class AnnouncementVisibilityService
{
    public function __construct(
        private RoleService $roles,
        private StudentRepositoryInterface $students,
        private BatchRepositoryInterface $batches,
        private ClassRepositoryInterface $classes,
    ) {}

    public function visibleTo(mixed $user, iterable $announcements): Collection
    {
        return collect($announcements)
            ->filter(fn ($announcement): bool => $this->canView($user, (array) $announcement))
            ->values();
    }

    public function canView(mixed $user, array $announcement): bool
    {
        $role = $this->roleName($user);
        if ($role === '') {
            return false;
        }

        $target = $this->target($announcement);
        if ($target['audience_type'] === 'ALL_ROLES') {
            return true;
        }
        if ($target['target_role'] === '' || ! hash_equals($target['target_role'], $role)) {
            return false;
        }
        if ($role !== 'STUDENT' || ($target['batch_id'] === '' && $target['class_id'] === '')) {
            return true;
        }

        // Batch/class targeting is deliberately fail-closed when the canonical
        // MASTER_STUDENT.User_ID mapping is missing or incomplete.
        $student = $this->studentFor($user);
        if (! $student) {
            return false;
        }
        $batchId = trim((string) ($student['Batch_ID'] ?? ''));
        $classId = trim((string) ($student['Class_ID'] ?? ''));
        if ($target['batch_id'] !== '' && ! hash_equals($target['batch_id'], $batchId)) {
            return false;
        }

        return $target['class_id'] === '' || ($classId !== '' && hash_equals($target['class_id'], $classId));
    }

    /** @return array{Audience_Type:string,Target_Role:?string,Target_Batch_ID:?string,Target_Class_ID:?string,Target_ID:?string} */
    public function canonicalize(array $data): array
    {
        $type = strtoupper(trim((string) ($data['Audience_Type'] ?? '')));
        // Backward compatibility for the existing ALL_STUDENTS / CLASS form.
        if (in_array($type, ['ALL_STUDENTS', 'CLASS'], true)) {
            $legacyClass = $type === 'CLASS'
                ? trim((string) ($data['Audience_ID'] ?? $data['Target_ID'] ?? $data['Class_ID'] ?? ''))
                : '';
            $type = 'ROLE';
            $data['Target_Role'] = 'STUDENT';
            $data['Target_Class_ID'] = $legacyClass;
            if ($legacyClass !== '') {
                $class = $this->activeClass($legacyClass);
                $data['Target_Batch_ID'] = trim((string) ($class['Batch_ID'] ?? ''));
            }
        }

        if ($type === 'ALL_ROLES') {
            return [
                'Audience_Type' => 'ALL_ROLES', 'Target_Role' => null,
                'Target_Batch_ID' => null, 'Target_Class_ID' => null, 'Target_ID' => null,
            ];
        }
        if ($type !== 'ROLE') {
            throw new \InvalidArgumentException('Tujuan pengumuman tidak valid.');
        }

        $role = strtoupper(trim((string) ($data['Target_Role'] ?? '')));
        if ($role === '' || ! in_array($role, $this->availableRoleNames(), true)) {
            throw new \InvalidArgumentException('Role penerima tidak valid atau tidak aktif.');
        }
        if ($role !== 'STUDENT') {
            return [
                'Audience_Type' => 'ROLE', 'Target_Role' => $role,
                'Target_Batch_ID' => null, 'Target_Class_ID' => null, 'Target_ID' => null,
            ];
        }

        $batchId = trim((string) ($data['Target_Batch_ID'] ?? ''));
        $classId = trim((string) ($data['Target_Class_ID'] ?? ''));
        if ($batchId === '' && $classId !== '') {
            throw new \InvalidArgumentException('Batch wajib dipilih sebelum kelas.');
        }
        if ($batchId !== '' && ! $this->activeBatch($batchId)) {
            throw new \InvalidArgumentException('Batch yang dipilih tidak tersedia.');
        }
        if ($classId !== '') {
            $class = $this->activeClass($classId);
            if (! $class || ! hash_equals($batchId, trim((string) ($class['Batch_ID'] ?? '')))) {
                throw new \InvalidArgumentException('Kelas yang dipilih bukan bagian dari batch tersebut.');
            }
        }

        return [
            'Audience_Type' => 'ROLE', 'Target_Role' => 'STUDENT',
            'Target_Batch_ID' => $batchId ?: null,
            'Target_Class_ID' => $classId ?: null,
            'Target_ID' => $classId ?: ($batchId ?: null),
        ];
    }

    public function audienceLabel(array $announcement): string
    {
        $target = $this->target($announcement);
        if ($target['audience_type'] === 'ALL_ROLES') {
            return 'Semua Role';
        }
        if ($target['target_role'] !== 'STUDENT') {
            return ucwords(strtolower($target['target_role']));
        }
        $parts = ['Student'];
        if ($target['batch_id'] === '') {
            $parts[] = 'Semua Siswa';
        } else {
            $batch = $this->batches->findById($target['batch_id']);
            $parts[] = $batch['Batch_Name'] ?? $batch['Batch_Code'] ?? $target['batch_id'];
            if ($target['class_id'] !== '') {
                $class = $this->classes->findById($target['class_id']);
                $parts[] = $class['Class_Name'] ?? $class['Class_Code'] ?? $target['class_id'];
            }
        }

        return implode(' • ', $parts);
    }

    /** @return list<array{value:string,label:string}> */
    public function availableRoles(): array
    {
        return collect($this->roles->getAllRoles())
            ->filter(fn ($row): bool => $this->active($row['Is_Active'] ?? true))
            ->map(function ($row): array {
                $name = strtoupper(trim((string) ($row['Role_Name'] ?? '')));

                return ['value' => $name, 'label' => ucwords(strtolower($name))];
            })
            ->filter(fn (array $role): bool => $role['value'] !== '' && $role['value'] !== 'MASTER')
            ->unique('value')->values()->all();
    }

    public function availableRoleNames(): array
    {
        return array_column($this->availableRoles(), 'value');
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

    /** @return array{audience_type:string,target_role:string,batch_id:string,class_id:string} */
    private function target(array $announcement): array
    {
        $type = strtoupper(trim((string) ($announcement['Audience_Type'] ?? '')));
        $role = strtoupper(trim((string) ($announcement['Target_Role'] ?? '')));
        $batch = trim((string) ($announcement['Target_Batch_ID'] ?? ''));
        $class = trim((string) ($announcement['Target_Class_ID'] ?? ''));

        if ($type === 'ALL_ROLES' || in_array($role, ['ALL', 'ALL_USERS'], true)) {
            return ['audience_type' => 'ALL_ROLES', 'target_role' => '', 'batch_id' => '', 'class_id' => ''];
        }
        if ($type === 'ALL_STUDENTS' || in_array($role, ['', 'ALL_STUDENTS'], true)) {
            return ['audience_type' => 'ROLE', 'target_role' => 'STUDENT', 'batch_id' => '', 'class_id' => ''];
        }
        if ($type === 'CLASS' || $role === 'CLASS') {
            return [
                'audience_type' => 'ROLE', 'target_role' => 'STUDENT', 'batch_id' => $batch,
                'class_id' => $class ?: trim((string) ($announcement['Audience_ID'] ?? $announcement['Target_ID'] ?? '')),
            ];
        }

        return ['audience_type' => 'ROLE', 'target_role' => $role, 'batch_id' => $batch, 'class_id' => $class];
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
}
