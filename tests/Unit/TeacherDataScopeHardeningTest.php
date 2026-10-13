<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassEnrollmentRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Support\Academic\TeacherScopeResolver;
use App\Support\Reporting\HumanReadableResolver;
use Tests\TestCase;

class TeacherDataScopeHardeningTest extends TestCase
{
    public function test_teacher_scope_uses_homeroom_assignment_with_legacy_schedule_fallback(): void
    {
        $scope = $this->resolver()->resolveForTeacherId('TCH-A');

        $this->assertSame(['SCH-A-MON', 'SCH-B-TUE'], $scope['schedule_ids']);
        $this->assertSame(['CLS-A', 'CLS-B', 'CLS-HOMEROOM-ONLY'], $scope['class_ids']);
        $this->assertSame(['CLS-A', 'CLS-B'], $scope['teaching_class_ids']);
        $this->assertEqualsCanonicalizing(['STU-A', 'STU-B', 'STU-B2', 'STU-H'], $scope['student_ids']);
        $this->assertSame(['STU-A'], $scope['schedule_student_ids']['SCH-A-MON']);
        $this->assertEqualsCanonicalizing(['STU-B', 'STU-B2'], $scope['schedule_student_ids']['SCH-B-TUE']);

        $this->assertNotContains('CLS-C', $scope['class_ids']);
        $this->assertNotContains('STU-C', $scope['student_ids']);
        $this->assertNotContains('STU-INACTIVE', $scope['student_ids']);
        $this->assertNotContains('STU-H', $scope['students_by_class']['CLS-B']);
    }

    public function test_teacher_assessment_schedule_labels_are_unique_and_human_readable(): void
    {
        $scope = $this->resolver()->resolveForTeacherId('TCH-A');
        $classesById = collect($this->classes())->keyBy('Class_ID');
        $subjectsById = collect($this->subjects())->keyBy('Subject_ID');

        $labels = collect($scope['schedules'])->mapWithKeys(function ($schedule) use ($classesById, $subjectsById) {
            $scheduleId = $schedule['Schedule_ID'];

            return [
                $scheduleId => HumanReadableResolver::scheduleLabel(
                    $scheduleId,
                    collect([$scheduleId => $schedule]),
                    $classesById,
                    $subjectsById
                ),
            ];
        });

        foreach (['Bahasa', 'Kelas A', 'Senin', '07:30 - 09:00'] as $expectedContext) {
            $this->assertStringContainsString($expectedContext, $labels['SCH-A-MON']);
        }
        foreach (['Bahasa', 'Kelas B', 'Selasa', '07:30 - 09:00'] as $expectedContext) {
            $this->assertStringContainsString($expectedContext, $labels['SCH-B-TUE']);
        }
        $this->assertCount(2, $labels->unique());
    }

    public function test_teacher_score_form_starts_with_schedule_first_student_dropdown(): void
    {
        $html = view('academic.teacher.scores-create', [
            'classes' => collect([]),
            'students' => collect([
                ['Student_ID' => 'STU-A', 'Full_Name' => 'Andi', 'Class_ID' => 'CLS-A', 'Class_Name' => 'Kelas A'],
                ['Student_ID' => 'STU-B', 'Full_Name' => 'Budi', 'Class_ID' => 'CLS-B', 'Class_Name' => 'Kelas B'],
            ]),
            'schedules' => collect([
                ['Schedule_ID' => 'SCH-A-MON', 'Class_ID' => 'CLS-A', 'Subject_ID' => 'SUB-BAHASA', 'label' => 'Bahasa - Kelas A | Senin, 07:30 - 09:00'],
                ['Schedule_ID' => 'SCH-B-TUE', 'Class_ID' => 'CLS-B', 'Subject_ID' => 'SUB-BAHASA', 'label' => 'Bahasa - Kelas B | Selasa, 07:30 - 09:00'],
            ]),
            'studentsByClass' => ['CLS-A' => ['STU-A'], 'CLS-B' => ['STU-B']],
            'studentsBySchedule' => ['SCH-A-MON' => ['STU-A'], 'SCH-B-TUE' => ['STU-B']],
            'teacherId' => 'TCH-A',
            'assessmentConfigs' => [],
        ])->render();

        $this->assertStringContainsString('Pilih jadwal terlebih dahulu', $html);
        $this->assertStringContainsString('studentsBySchedule', $html);
        $this->assertStringContainsString('selectedStudent = \'\'', $html);
        $this->assertStringContainsString('Bahasa - Kelas A | Senin, 07:30 - 09:00', $html);
        $this->assertStringContainsString('Bahasa - Kelas B | Selasa, 07:30 - 09:00', $html);
        $this->assertStringNotContainsString('<option value="STU-A"', $html);
    }

    private function resolver(): TeacherScopeResolver
    {
        $teachers = $this->mockRepo(TeacherRepositoryInterface::class, [
            ['Teacher_ID' => 'TCH-A', 'User_ID' => 'USR-TCH-A'],
            ['Teacher_ID' => 'TCH-B', 'User_ID' => 'USR-TCH-B'],
        ]);
        $schedules = $this->mockRepo(ScheduleRepositoryInterface::class, $this->schedules());
        $classes = $this->mockRepo(ClassRepositoryInterface::class, $this->classes());
        $students = $this->mockRepo(StudentRepositoryInterface::class, $this->students());
        $enrollments = $this->mockRepo(ClassEnrollmentRepositoryInterface::class, $this->enrollments());

        $assessments = \Mockery::mock(AssessmentRepositoryInterface::class);
        $assessments->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect([
            ['Assessment_ID' => 'ASM-OWN', 'Teacher_ID' => 'TCH-A'],
            ['Assessment_ID' => 'ASM-CLASS', 'Class_ID' => 'CLS-A'],
            ['Assessment_ID' => 'ASM-SCHEDULE', 'Schedule_ID' => 'SCH-B-TUE'],
            ['Assessment_ID' => 'ASM-FOREIGN', 'Teacher_ID' => 'TCH-B'],
            ['Assessment_ID' => 'ASM-FOREIGN-SCHEDULE', 'Schedule_ID' => 'SCH-C-MON'],
        ]));

        return new TeacherScopeResolver($teachers, $schedules, $classes, $students, $enrollments, $assessments);
    }

    private function mockRepo(string $interface, array $rows)
    {
        $repo = \Mockery::mock($interface);
        $repo->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect($rows));

        return $repo;
    }

    private function schedules(): array
    {
        return [
            ['Schedule_ID' => 'SCH-A-MON', 'Teacher_ID' => 'TCH-A', 'Class_ID' => 'CLS-A', 'Subject_ID' => 'SUB-BAHASA', 'Day_Of_Week' => 'Senin', 'Start_Time' => '07:30', 'End_Time' => '09:00', 'Is_Active' => 'TRUE'],
            ['Schedule_ID' => 'SCH-B-TUE', 'Teacher_ID' => 'TCH-A', 'Class_ID' => 'CLS-B', 'Subject_ID' => 'SUB-BAHASA', 'Day_Of_Week' => 'Selasa', 'Start_Time' => '07:30', 'End_Time' => '09:00', 'Is_Active' => 'TRUE'],
            ['Schedule_ID' => 'SCH-C-MON', 'Teacher_ID' => 'TCH-B', 'Class_ID' => 'CLS-C', 'Subject_ID' => 'SUB-BAHASA', 'Day_Of_Week' => 'Senin', 'Start_Time' => '10:00', 'End_Time' => '11:00', 'Is_Active' => 'TRUE'],
            ['Schedule_ID' => 'SCH-INACTIVE', 'Teacher_ID' => 'TCH-A', 'Class_ID' => 'CLS-C', 'Subject_ID' => 'SUB-BAHASA', 'Is_Active' => 'FALSE'],
        ];
    }

    private function classes(): array
    {
        return [
            ['Class_ID' => 'CLS-A', 'Class_Name' => 'Kelas A', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-B', 'Class_Name' => 'Kelas B', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-C', 'Class_Name' => 'Kelas C', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-HOMEROOM-ONLY', 'Class_Name' => 'Kelas Homeroom', 'Homeroom_Teacher_ID' => 'TCH-A', 'Is_Active' => 'TRUE'],
        ];
    }

    private function subjects(): array
    {
        return [
            ['Subject_ID' => 'SUB-BAHASA', 'Subject_Name' => 'Bahasa'],
        ];
    }

    private function students(): array
    {
        return [
            ['Student_ID' => 'STU-A', 'Full_Name' => 'Andi', 'Class_ID' => 'CLS-A', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STU-B', 'Full_Name' => 'Budi', 'Class_ID' => 'CLS-B', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STU-B2', 'Full_Name' => 'Bela', 'Class_ID' => '', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STU-C', 'Full_Name' => 'Citra', 'Class_ID' => 'CLS-C', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STU-H', 'Full_Name' => 'Hana', 'Class_ID' => 'CLS-HOMEROOM-ONLY', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STU-INACTIVE', 'Full_Name' => 'Inactive', 'Class_ID' => 'CLS-A', 'Is_Active' => 'FALSE'],
        ];
    }

    private function enrollments(): array
    {
        return [
            ['Enrollment_ID' => 'ENR-B2', 'Student_ID' => 'STU-B2', 'Class_ID' => 'CLS-B', 'Is_Active' => 'TRUE'],
            ['Enrollment_ID' => 'ENR-C', 'Student_ID' => 'STU-C', 'Class_ID' => 'CLS-C', 'Is_Active' => 'TRUE'],
            ['Enrollment_ID' => 'ENR-H-OLD', 'Student_ID' => 'STU-H', 'Class_ID' => 'CLS-B', 'Is_Active' => 'TRUE'],
            ['Enrollment_ID' => 'ENR-INACTIVE', 'Student_ID' => 'STU-INACTIVE', 'Class_ID' => 'CLS-A', 'Is_Active' => 'FALSE'],
        ];
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
