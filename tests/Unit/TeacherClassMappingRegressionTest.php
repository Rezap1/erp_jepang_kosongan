<?php

namespace Tests\Unit;

use App\Http\Controllers\Academic\TeacherWorkspaceController;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Services\Academic\AssessmentConfigService;
use App\Services\Academic\AttendanceService;
use App\Services\Academic\ScheduleService;
use App\Services\Academic\ScoreService;
use App\Services\Academic\SubjectService;
use App\Services\Attendance\AttendanceRequestService;
use App\Services\Core\AssignmentService;
use App\Services\Core\BatchService;
use App\Services\Core\ClassService;
use App\Services\Core\StudentService;
use App\Services\Core\TeacherService;
use App\Services\Quiz\QuizScopeService;
use App\Support\Academic\TeacherScopeResolver;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\ViewErrorBag;
use Mockery;
use Tests\TestCase;

class TeacherClassMappingRegressionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_score_input_uses_attendance_class_assignment_and_excludes_stale_schedule_class(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-A', 'User_ID' => 'USR-A', 'Role' => 'TEACHER']));

        $scope = [
            'teacher_id' => 'TCH-A',
            'schedule_ids' => ['SCH-C-STALE', 'SCH-A'],
            'managed_class_ids' => ['CLS-A'],
            'teaching_class_ids' => ['CLS-C', 'CLS-A'],
            'managed_student_ids' => ['STU-A'],
            'teaching_student_ids' => ['STU-C', 'STU-A'],
            'managed_students_by_class' => ['CLS-A' => ['STU-A']],
            'teaching_students_by_class' => ['CLS-C' => ['STU-C'], 'CLS-A' => ['STU-A']],
            'schedule_student_ids' => ['SCH-C-STALE' => ['STU-C'], 'SCH-A' => ['STU-A']],
        ];
        $scopeResolver = Mockery::mock(TeacherScopeResolver::class);
        $scopeResolver->shouldReceive('resolveForUser')->once()->andReturn($scope);
        $scopeResolver->shouldReceive('resolveForTeacherId')->once()->with('TCH-A')->andReturn($scope);

        $classes = Mockery::mock(ClassService::class);
        $classes->shouldReceive('getAllClasses')->once()->andReturn(collect([
            ['Class_ID' => 'CLS-A', 'Class_Name' => 'Kelas Alpha', 'Homeroom_Teacher_ID' => 'TCH-A', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-C', 'Class_Name' => 'Kelas C Stale', 'Homeroom_Teacher_ID' => 'TCH-OTHER', 'Is_Active' => 'TRUE'],
        ]));
        $schedules = Mockery::mock(ScheduleService::class);
        $schedules->shouldReceive('getAll')->once()->andReturn(collect([
            ['Schedule_ID' => 'SCH-C-STALE', 'Teacher_ID' => 'TCH-A', 'Class_ID' => 'CLS-C', 'Subject_ID' => 'SUB-C', 'Is_Active' => 'TRUE'],
            ['Schedule_ID' => 'SCH-A', 'Teacher_ID' => 'TCH-A', 'Class_ID' => 'CLS-A', 'Subject_ID' => 'SUB-A', 'Is_Active' => 'TRUE'],
        ]));
        $students = Mockery::mock(StudentService::class);
        $students->shouldReceive('getAllStudents')->once()->andReturn(collect([
            ['Student_ID' => 'STU-A', 'Full_Name' => 'Siswa Alpha', 'Class_ID' => 'CLS-A'],
            ['Student_ID' => 'STU-C', 'Full_Name' => 'Siswa C', 'Class_ID' => 'CLS-C'],
        ]));
        $subjects = Mockery::mock(SubjectService::class);
        $subjects->shouldReceive('getAll')->once()->andReturn(collect([
            ['Subject_ID' => 'SUB-A', 'Subject_Name' => 'Bahasa Jepang'],
            ['Subject_ID' => 'SUB-C', 'Subject_Name' => 'Stale Subject'],
        ]));
        $assessmentConfigs = Mockery::mock(AssessmentConfigService::class);
        $assessmentConfigs->shouldReceive('getActiveCategories')->once()->andReturn([]);

        $controller = new TeacherWorkspaceController(
            Mockery::mock(TeacherService::class),
            $classes,
            $schedules,
            $students,
            Mockery::mock(AttendanceService::class),
            Mockery::mock(AttendanceRequestService::class),
            $subjects,
            Mockery::mock(BatchService::class),
            Mockery::mock(AssignmentService::class),
            Mockery::mock(ScoreService::class),
            $assessmentConfigs,
            $scopeResolver,
        );

        $view = $controller->scoresCreate();

        $this->assertSame(['CLS-A'], $view->getData()['classes']->pluck('Class_ID')->all());
        $this->assertSame(['SCH-A'], $view->getData()['schedules']->pluck('Schedule_ID')->all());
        $this->assertSame(['STU-A'], $view->getData()['students']->pluck('Student_ID')->all());
        $html = $view->with('errors', new ViewErrorBag)->render();
        $this->assertStringContainsString('Kelas Alpha', $html);
        $this->assertStringNotContainsString('Kelas C Stale', $html);
        $this->assertStringContainsString('-- Pilih Kelas --', $html);
    }

    public function test_quiz_creation_uses_attendance_class_assignment_instead_of_stale_schedule(): void
    {
        $teachers = $this->repository(TeacherRepositoryInterface::class, [
            ['Teacher_ID' => 'TCH-A', 'User_ID' => 'USR-A', 'Is_Active' => 'TRUE'],
        ]);
        $students = $this->repository(StudentRepositoryInterface::class, []);
        $schedules = Mockery::mock(ScheduleRepositoryInterface::class);
        $schedules->shouldNotReceive('fetchAll');
        $classes = $this->repository(ClassRepositoryInterface::class, [
            ['Class_ID' => 'CLS-A', 'Class_Name' => 'Kelas Alpha', 'Homeroom_Teacher_ID' => 'TCH-A', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-C', 'Class_Name' => 'Kelas C Stale', 'Homeroom_Teacher_ID' => 'TCH-OTHER', 'Is_Active' => 'TRUE'],
        ]);
        $scope = new QuizScopeService($teachers, $students, $schedules, $classes);

        $teacher = $scope->teacherForUser(new GenericUser(['User_ID' => 'USR-A']));
        $assignedClasses = $scope->classesForTeacher($teacher['Teacher_ID']);
        $html = view('quiz.teacher.form', [
            'quiz' => null,
            'questions' => collect(),
            'classes' => $assignedClasses,
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertSame(['CLS-A'], $assignedClasses->pluck('Class_ID')->all());
        $this->assertStringContainsString('Kelas Alpha', $html);
        $this->assertStringNotContainsString('Kelas C Stale', $html);
        $this->assertStringContainsString('-- Pilih Kelas --', $html);
    }

    private function repository(string $interface, array $rows)
    {
        $repository = Mockery::mock($interface);
        $repository->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect($rows));

        return $repository;
    }
}
