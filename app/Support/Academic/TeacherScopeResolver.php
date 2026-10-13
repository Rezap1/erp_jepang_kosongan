<?php

namespace App\Support\Academic;

use App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassEnrollmentRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;

class TeacherScopeResolver
{
    public function __construct(
        private TeacherRepositoryInterface $teacherRepository,
        private ScheduleRepositoryInterface $scheduleRepository,
        private ClassRepositoryInterface $classRepository,
        private StudentRepositoryInterface $studentRepository,
        private ClassEnrollmentRepositoryInterface $classEnrollmentRepository,
        private AssessmentRepositoryInterface $assessmentRepository
    ) {}

    public function resolveForUser($user): array
    {
        $userId = trim((string) ($user->User_ID ?? ''));
        if ($userId === '') {
            return $this->emptyScope();
        }

        $teacher = collect($this->teacherRepository->fetchAll())
            ->firstWhere('User_ID', $userId);

        return $this->resolveForTeacherId(trim((string) ($teacher['Teacher_ID'] ?? '')));
    }

    public function resolveForTeacherId(string $teacherId): array
    {
        $teacherId = trim($teacherId);
        if ($teacherId === '') {
            return $this->emptyScope();
        }

        $scopeKey = 'teacher_scope_'.hash('sha256', $teacherId);
        if (function_exists('request') && request()->attributes->has($scopeKey)) {
            return request()->attributes->get($scopeKey);
        }

        $allSchedules = collect($this->scheduleRepository->fetchAll());
        $teacherSchedules = $allSchedules
            ->filter(fn ($row) => trim((string) ($row['Teacher_ID'] ?? '')) === $teacherId)
            ->filter(fn ($row) => ! $this->isInactive($row))
            ->values();

        $scheduleIds = $teacherSchedules->pluck('Schedule_ID')->map(fn ($id) => trim((string) $id))->filter()->unique()->values()->all();
        $teachingClassIds = $teacherSchedules->pluck('Class_ID')->map(fn ($id) => trim((string) $id))->filter()->unique()->values()->all();
        $subjectIds = $teacherSchedules->pluck('Subject_ID')->map(fn ($id) => trim((string) $id))->filter()->unique()->values()->all();

        $allClasses = collect($this->classRepository->fetchAll())
            ->filter(fn ($row) => ! $this->isInactive($row))
            ->values();
        $classIds = TeacherClassAssignmentResolver::managedClassIds($allClasses, $teacherSchedules, $teacherId);
        $classes = $allClasses
            ->filter(fn ($row) => in_array(trim((string) ($row['Class_ID'] ?? '')), $classIds, true))
            ->values();
        $rosterClassIds = array_values(array_unique(array_merge($classIds, $teachingClassIds)));

        $students = collect($this->studentRepository->fetchAll())
            ->filter(fn ($row) => ! $this->isInactive($row))
            ->values();
        $enrollments = collect($this->classEnrollmentRepository->fetchAll())
            ->filter(fn ($row) => ! $this->isInactive($row))
            ->values();

        $studentsByClass = [];
        $studentsWithCurrentClass = [];
        foreach ($students as $student) {
            $classId = trim((string) ($student['Class_ID'] ?? ''));
            $studentId = trim((string) ($student['Student_ID'] ?? ''));
            if ($classId !== '' && $studentId !== '') {
                $studentsWithCurrentClass[$studentId] = true;
            }
            if ($classId !== '' && $studentId !== '' && in_array($classId, $rosterClassIds, true)) {
                $studentsByClass[$classId][] = $studentId;
            }
        }

        // MASTER_STUDENT.Class_ID is the current placement source. Enrollment
        // rows are a legacy/history fallback only when the student has no
        // current class, preventing a moved student from appearing twice.
        foreach ($enrollments as $enrollment) {
            $classId = trim((string) ($enrollment['Class_ID'] ?? ''));
            $studentId = trim((string) ($enrollment['Student_ID'] ?? ''));
            if ($classId !== '' && $studentId !== ''
                && ! isset($studentsWithCurrentClass[$studentId])
                && in_array($classId, $rosterClassIds, true)) {
                $studentsByClass[$classId][] = $studentId;
            }
        }

        $studentsByClass = collect($studentsByClass)
            ->map(fn ($ids) => array_values(array_unique(array_filter(array_map('trim', $ids)))))
            ->all();
        $studentIds = array_values(array_unique(array_merge(...array_values($studentsByClass ?: [[]]))));

        $scheduleStudentIds = [];
        foreach ($teacherSchedules as $schedule) {
            $scheduleId = trim((string) ($schedule['Schedule_ID'] ?? ''));
            $classId = trim((string) ($schedule['Class_ID'] ?? ''));
            if ($scheduleId !== '') {
                $scheduleStudentIds[$scheduleId] = $studentsByClass[$classId] ?? [];
            }
        }

        $assessments = collect($this->assessmentRepository->getAll());
        $assessmentIds = $assessments
            ->filter(function ($row) use ($teacherId, $classIds, $teachingClassIds, $scheduleIds) {
                $rowTeacher = trim((string) ($row['Teacher_ID'] ?? ''));
                if ($rowTeacher !== '') {
                    return $rowTeacher === $teacherId;
                }

                $classId = trim((string) ($row['Class_ID'] ?? ''));
                $scheduleId = trim((string) ($row['Schedule_ID'] ?? ''));

                return ($classId !== '' && in_array($classId, array_values(array_unique(array_merge($classIds, $teachingClassIds))), true))
                    || ($scheduleId !== '' && in_array($scheduleId, $scheduleIds, true));
            })
            ->pluck('Assessment_ID')
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $scope = [
            'teacher_id' => $teacherId,
            'schedule_ids' => $scheduleIds,
            'class_ids' => $classIds,
            'managed_class_ids' => $classIds,
            'teaching_class_ids' => $teachingClassIds,
            'subject_ids' => $subjectIds,
            'student_ids' => $studentIds,
            'assessment_ids' => $assessmentIds,
            'students_by_class' => $studentsByClass,
            'schedule_student_ids' => $scheduleStudentIds,
            'schedules' => $teacherSchedules,
            'classes' => $classes,
            'students' => $students->whereIn('Student_ID', $studentIds)->values(),
        ];

        if (function_exists('request')) {
            request()->attributes->set($scopeKey, $scope);
        }

        return $scope;
    }

    public function classAllowed(array $scope, string $classId): bool
    {
        return in_array(trim($classId), $scope['class_ids'] ?? [], true);
    }

    public function scheduleAllowed(array $scope, string $scheduleId): bool
    {
        return in_array(trim($scheduleId), $scope['schedule_ids'] ?? [], true);
    }

    public function studentAllowedForClass(array $scope, string $studentId, string $classId): bool
    {
        return in_array(trim($studentId), $scope['students_by_class'][trim($classId)] ?? [], true);
    }

    public function studentAllowedForSchedule(array $scope, string $studentId, string $scheduleId): bool
    {
        return in_array(trim($studentId), $scope['schedule_student_ids'][trim($scheduleId)] ?? [], true);
    }

    private function emptyScope(): array
    {
        return [
            'teacher_id' => '',
            'schedule_ids' => [],
            'class_ids' => [],
            'managed_class_ids' => [],
            'teaching_class_ids' => [],
            'subject_ids' => [],
            'student_ids' => [],
            'assessment_ids' => [],
            'students_by_class' => [],
            'schedule_student_ids' => [],
            'schedules' => collect(),
            'classes' => collect(),
            'students' => collect(),
        ];
    }

    private function isInactive($row): bool
    {
        $row = is_array($row) ? $row : (array) $row;
        foreach (['Is_Active', 'Status', 'Enrollment_Status'] as $field) {
            $value = strtoupper(trim((string) ($row[$field] ?? '')));
            if ($field === 'Enrollment_Status' && $value === 'ALUMNI') {
                return true;
            }
            if (in_array($value, ['FALSE', 'INACTIVE', 'NONACTIVE', 'NON_ACTIVE', 'CANCELLED', 'DROPPED', 'ARCHIVED'], true)) {
                return true;
            }
        }

        return false;
    }
}
