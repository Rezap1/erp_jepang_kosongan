<?php

namespace App\Services\Quiz;

use App\Helpers\SheetValue;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Support\Academic\TeacherClassAssignmentResolver;
use Illuminate\Support\Collection;

class QuizScopeService
{
    private ?Collection $teacherSnapshot = null;

    private ?Collection $studentSnapshot = null;

    private ?Collection $scheduleSnapshot = null;

    private ?Collection $classSnapshot = null;

    private array $teacherClassSnapshots = [];

    public function __construct(
        private TeacherRepositoryInterface $teachers,
        private StudentRepositoryInterface $students,
        private ScheduleRepositoryInterface $schedules,
        private ClassRepositoryInterface $classes,
    ) {}

    public function teacherForUser(object $user): array
    {
        $userId = trim((string) ($user->User_ID ?? ''));
        $teacher = $this->teacherRows()->first(
            fn ($row) => trim((string) ($row['User_ID'] ?? '')) === $userId && $this->active((array) $row)
        );
        abort_unless($teacher && trim((string) ($teacher['Teacher_ID'] ?? '')) !== '', 403, 'Profil pengajar aktif tidak ditemukan.');

        return (array) $teacher;
    }

    public function studentForUser(object $user): array
    {
        $userId = trim((string) ($user->User_ID ?? ''));
        $student = $this->studentRows()->first(
            fn ($row) => trim((string) ($row['User_ID'] ?? '')) === $userId && $this->active((array) $row)
        );
        abort_unless($student && trim((string) ($student['Student_ID'] ?? '')) !== '' && trim((string) ($student['Class_ID'] ?? '')) !== '', 403, 'Profil siswa aktif atau kelas siswa tidak ditemukan.');

        return (array) $student;
    }

    public function classesForTeacher(string $teacherId)
    {
        $teacherId = trim($teacherId);
        if (array_key_exists($teacherId, $this->teacherClassSnapshots)) {
            return collect($this->teacherClassSnapshots[$teacherId]);
        }

        // Keep quiz class ownership aligned with the attendance workspace.
        // MASTER_CLASS.Homeroom_Teacher_ID is the explicit class assignment;
        // schedule rows may be stale and must not grant or relabel class access.
        $classIds = collect(TeacherClassAssignmentResolver::managedClassIds(
            $this->classRows(),
            $teacherId,
        ));

        $classes = $this->classRows()
            ->filter(fn ($row) => $classIds->contains(trim((string) ($row['Class_ID'] ?? ''))) && $this->active((array) $row))
            ->sortBy(fn ($row) => mb_strtolower(trim((string) ($row['Class_Name'] ?? $row['Class_ID'] ?? ''))))
            ->values();
        $this->teacherClassSnapshots[$teacherId] = $classes->all();

        return $classes;
    }

    public function studentsForClass(string $classId)
    {
        return $this->studentRows()
            ->filter(fn ($row) => trim((string) ($row['Class_ID'] ?? '')) === trim($classId))
            ->filter(fn ($row) => SheetValue::isOperationalStudent((array) $row))
            ->sortBy(fn ($row) => mb_strtolower(trim((string) ($row['Full_Name'] ?? $row['Student_Name'] ?? $row['Student_ID'] ?? ''))))
            ->values();
    }

    public function studentCountsForClasses(iterable $classIds): Collection
    {
        $classIds = collect($classIds)->map(fn ($id) => trim((string) $id))->filter()->unique();

        return $this->studentRows()
            ->filter(fn ($row) => $classIds->contains(trim((string) ($row['Class_ID'] ?? ''))))
            ->filter(fn ($row) => SheetValue::isOperationalStudent((array) $row))
            ->countBy(fn ($row) => trim((string) ($row['Class_ID'] ?? '')));
    }

    public function assertTeacherClass(string $teacherId, string $classId): void
    {
        abort_unless($this->classesForTeacher($teacherId)->contains(
            fn ($row) => trim((string) ($row['Class_ID'] ?? '')) === trim($classId)
        ), 403, 'Kelas berada di luar penugasan kelas Anda.');
    }

    private function active(array $row): bool
    {
        foreach (['Is_Active', 'Status'] as $field) {
            if (in_array(strtoupper(trim((string) ($row[$field] ?? ''))), ['FALSE', 'INACTIVE', 'NONACTIVE', 'NON_ACTIVE', 'ARCHIVED', 'DROPPED', 'CANCELLED'], true)) {
                return false;
            }
        }

        return true;
    }

    private function teacherRows(): Collection
    {
        return $this->teacherSnapshot ??= collect($this->teachers->fetchAll());
    }

    private function studentRows(): Collection
    {
        return $this->studentSnapshot ??= collect($this->students->fetchAll());
    }

    private function scheduleRows(): Collection
    {
        return $this->scheduleSnapshot ??= collect($this->schedules->fetchAll());
    }

    private function classRows(): Collection
    {
        return $this->classSnapshot ??= collect($this->classes->fetchAll());
    }
}
