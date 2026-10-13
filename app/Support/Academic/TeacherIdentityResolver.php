<?php

namespace App\Support\Academic;

use App\Helpers\SheetValue;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;

final class TeacherIdentityResolver
{
    public function __construct(
        private TeacherRepositoryInterface $teacherRepository,
        private EmployeeRepositoryInterface $employeeRepository
    ) {}

    /**
     * Resolve an authenticated account to one active teacher record.
     *
     * The canonical path is MASTER_USER -> MASTER_EMPLOYEE -> MASTER_TEACHER.
     * A tightly constrained direct User_ID link remains available only for
     * legacy rows where neither the user nor teacher claims an Employee_ID and
     * no employee row claims the account. Ambiguous or contradictory links
     * always fail closed.
     */
    public function resolveForUser($user): ?array
    {
        $userRow = is_array($user) ? $user : [
            'User_ID' => $user->User_ID ?? null,
            'id' => $user->id ?? null,
            'Employee_ID' => $user->Employee_ID ?? null,
            'Is_Active' => $user->Is_Active ?? null,
            'Status' => $user->Status ?? null,
        ];
        $userId = trim((string) ($userRow['User_ID'] ?? $userRow['id'] ?? ''));
        if ($userId === '' || SheetValue::isInactive($userRow)) {
            return null;
        }

        $userKey = SheetValue::id($userId);
        $claimedEmployeeId = trim((string) ($userRow['Employee_ID'] ?? ''));
        $claimedEmployeeKey = SheetValue::id($claimedEmployeeId);
        $employees = collect($this->employeeRepository->fetchAll())->map(fn ($row) => (array) $row);
        $teachers = collect($this->teacherRepository->fetchAll())->map(fn ($row) => (array) $row);

        $employeeClaims = $employees->filter(
            fn (array $row): bool => SheetValue::id($row['User_ID'] ?? '') === $userKey
        )->values();
        $employee = null;

        if ($claimedEmployeeKey !== '') {
            $matches = $employees->filter(
                fn (array $row): bool => SheetValue::id($row['Employee_ID'] ?? '') === $claimedEmployeeKey
            )->values();
            if ($matches->count() !== 1 || SheetValue::isInactive($matches->first())) {
                return null;
            }

            $employee = $matches->first();
            $employeeUserKey = SheetValue::id($employee['User_ID'] ?? '');
            if ($employeeUserKey !== '' && $employeeUserKey !== $userKey) {
                return null;
            }
            if ($employeeClaims->contains(
                fn (array $row): bool => SheetValue::id($row['Employee_ID'] ?? '') !== $claimedEmployeeKey
            )) {
                return null;
            }
        } elseif ($employeeClaims->isNotEmpty()) {
            if ($employeeClaims->count() !== 1 || SheetValue::isInactive($employeeClaims->first())) {
                return null;
            }
            $employee = $employeeClaims->first();
        }

        if ($employee !== null) {
            $employeeId = trim((string) ($employee['Employee_ID'] ?? ''));
            $employeeKey = SheetValue::id($employeeId);
            if ($employeeKey === '') {
                return null;
            }

            $teacherMatches = $teachers->filter(
                fn (array $row): bool => ! SheetValue::isInactive($row)
                    && SheetValue::id($row['Employee_ID'] ?? '') === $employeeKey
            )->values();
            if ($teacherMatches->count() !== 1) {
                return null;
            }

            $teacher = $teacherMatches->first();
            $teacherUserKey = SheetValue::id($teacher['User_ID'] ?? '');
            if ($teacherUserKey !== '' && $teacherUserKey !== $userKey) {
                return null;
            }

            $conflictingUserTeacher = $teachers->contains(function (array $row) use ($userKey, $employeeKey, $teacher): bool {
                return ! SheetValue::isInactive($row)
                    && SheetValue::id($row['User_ID'] ?? '') === $userKey
                    && (SheetValue::id($row['Teacher_ID'] ?? '') !== SheetValue::id($teacher['Teacher_ID'] ?? '')
                        || SheetValue::id($row['Employee_ID'] ?? '') !== $employeeKey);
            });
            if ($conflictingUserTeacher) {
                return null;
            }

            return $this->result($teacher, $employee, 'employee');
        }

        // Safe legacy compatibility: a unique active direct link is accepted
        // only while the account has no employee relation on either side.
        if ($claimedEmployeeKey !== '' || $employeeClaims->isNotEmpty()) {
            return null;
        }
        $legacyMatches = $teachers->filter(
            fn (array $row): bool => ! SheetValue::isInactive($row)
                && SheetValue::id($row['User_ID'] ?? '') === $userKey
                && SheetValue::id($row['Employee_ID'] ?? '') === ''
        )->values();
        if ($legacyMatches->count() !== 1) {
            return null;
        }

        return $this->result($legacyMatches->first(), null, 'legacy_user');
    }

    public function activeTeacher(string $teacherId): ?array
    {
        $teacherKey = SheetValue::id($teacherId);
        if ($teacherKey === '') {
            return null;
        }

        $matches = collect($this->teacherRepository->fetchAll())
            ->map(fn ($row) => (array) $row)
            ->filter(fn (array $row): bool => SheetValue::id($row['Teacher_ID'] ?? '') === $teacherKey)
            ->filter(fn (array $row): bool => ! SheetValue::isInactive($row))
            ->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function result(array $teacher, ?array $employee, string $mappingMode): array
    {
        return [
            'teacher_id' => trim((string) ($teacher['Teacher_ID'] ?? '')),
            'employee_id' => trim((string) ($employee['Employee_ID'] ?? '')),
            'mapping_mode' => $mappingMode,
            'teacher' => $teacher,
            'employee' => $employee,
        ];
    }
}
