<?php

namespace App\Support\Academic;

use App\Helpers\SheetValue;
use App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;
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
        private AssessmentRepositoryInterface $assessmentRepository,
        private ?TeacherIdentityResolver $teacherIdentityResolver = null
    ) {}

    public function resolveForUser($user): array
    {
        $identity = $this->identityResolver()->resolveForUser($user);
        if ($identity === null) {
            return $this->emptyScope();
        }

        $identityTeacherKey = 'teacher_identity_'.hash('sha256', $identity['teacher_id']);
        if (function_exists('request')) {
            request()->attributes->set($identityTeacherKey, $identity['teacher']);
        }
        $scope = $this->resolveForTeacherId($identity['teacher_id']);
        $scope['identity'] = $identity;

        return $scope;
    }

    public function resolveForTeacherId(string $teacherId): array
    {
        $teacherId = trim($teacherId);
        $teacherKey = SheetValue::id($teacherId);
        $identityTeacherKey = 'teacher_identity_'.hash('sha256', $teacherId);
        $teacher = function_exists('request') && request()->attributes->has($identityTeacherKey)
            ? (array) request()->attributes->get($identityTeacherKey)
            : null;
        if ($teacher === null) {
            $teacherMatches = collect($this->teacherRepository->fetchAll())
                ->map(fn ($row) => (array) $row)
                ->filter(fn ($row) => SheetValue::id($row['Teacher_ID'] ?? '') === $teacherKey)
                ->filter(fn ($row) => ! $this->isInactive($row))
                ->values();
            $teacher = $teacherMatches->count() === 1 ? $teacherMatches->first() : null;
        }
        if ($teacher === null) {
            return $this->emptyScope();
        }
        $teacherId = trim((string) $teacher['Teacher_ID']);

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
        $classIds = TeacherClassAssignmentResolver::managedClassIds($allClasses, $teacherId);
        $rosterClassIds = array_values(array_unique(array_merge($classIds, $teachingClassIds)));
        $classes = $allClasses
            ->filter(fn ($row) => in_array(trim((string) ($row['Class_ID'] ?? '')), $rosterClassIds, true))
            ->values();

        $students = collect($this->studentRepository->fetchAll())
            ->filter(fn ($row) => ! $this->isInactive($row))
            ->values();
        $studentsByClass = [];
        foreach ($students as $student) {
            $classId = trim((string) ($student['Class_ID'] ?? ''));
            $studentId = trim((string) ($student['Student_ID'] ?? ''));
            if ($classId !== '' && $studentId !== '' && in_array($classId, $rosterClassIds, true)) {
                $studentsByClass[$classId][] = $studentId;
            }
        }

        $studentsByClass = collect($studentsByClass)
            ->map(fn ($ids) => array_values(array_unique(array_filter(array_map('trim', $ids)))))
            ->all();
        $studentIds = array_values(array_unique(array_merge(...array_values($studentsByClass ?: [[]]))));
        $managedStudentsByClass = array_intersect_key($studentsByClass, array_flip($classIds));
        $teachingStudentsByClass = array_intersect_key($studentsByClass, array_flip($teachingClassIds));
        $managedStudentIds = array_values(array_unique(array_merge(...array_values($managedStudentsByClass ?: [[]]))));
        $teachingStudentIds = array_values(array_unique(array_merge(...array_values($teachingStudentsByClass ?: [[]]))));

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
            ->filter(function ($row) use ($teacherId, $teachingClassIds, $scheduleIds) {
                $rowTeacher = trim((string) ($row['Teacher_ID'] ?? ''));
                if ($rowTeacher !== '') {
                    return $rowTeacher === $teacherId;
                }

                $classId = trim((string) ($row['Class_ID'] ?? ''));
                $scheduleId = trim((string) ($row['Schedule_ID'] ?? ''));

                return ($classId !== '' && in_array($classId, $teachingClassIds, true))
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
            'managed_student_ids' => $managedStudentIds,
            'teaching_student_ids' => $teachingStudentIds,
            'assessment_ids' => $assessmentIds,
            'students_by_class' => $studentsByClass,
            'managed_students_by_class' => $managedStudentsByClass,
            'teaching_students_by_class' => $teachingStudentsByClass,
            'schedule_student_ids' => $scheduleStudentIds,
            'schedules' => $teacherSchedules,
            'classes' => $classes,
            'managed_classes' => $classes->filter(fn ($row) => in_array(trim((string) ($row['Class_ID'] ?? '')), $classIds, true))->values(),
            'students' => $students->whereIn('Student_ID', $studentIds)->values(),
        ];

        if (function_exists('request')) {
            request()->attributes->set($scopeKey, $scope);
        }

        return $scope;
    }

    public function classAllowed(array $scope, string $classId): bool
    {
        return $this->managedClassAllowed($scope, $classId);
    }

    public function managedClassAllowed(array $scope, string $classId): bool
    {
        return in_array(trim($classId), $scope['managed_class_ids'] ?? [], true);
    }

    public function teachingClassAllowed(array $scope, string $classId): bool
    {
        return in_array(trim($classId), $scope['teaching_class_ids'] ?? [], true);
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
            'managed_student_ids' => [],
            'teaching_student_ids' => [],
            'assessment_ids' => [],
            'students_by_class' => [],
            'managed_students_by_class' => [],
            'teaching_students_by_class' => [],
            'schedule_student_ids' => [],
            'schedules' => collect(),
            'classes' => collect(),
            'managed_classes' => collect(),
            'students' => collect(),
        ];
    }

    private function isInactive($row): bool
    {
        return SheetValue::isInactive(is_array($row) ? $row : (array) $row);
    }

    private function identityResolver(): TeacherIdentityResolver
    {
        return $this->teacherIdentityResolver ?? app(TeacherIdentityResolver::class);
    }
}
