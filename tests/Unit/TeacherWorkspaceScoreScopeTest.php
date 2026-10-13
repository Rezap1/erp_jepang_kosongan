<?php

namespace Tests\Unit;

use App\Http\Controllers\Academic\TeacherWorkspaceController;
use App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;
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
use App\Support\Academic\TeacherScopeResolver;
use Illuminate\Auth\GenericUser;
use Mockery;
use Tests\TestCase;

class TeacherWorkspaceScoreScopeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_teacher_scores_only_include_owned_assessment_or_explicit_teacher_scores(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-A', 'User_ID' => 'USR-A', 'Role' => 'TEACHER']));

        $assessmentRepo = Mockery::mock(AssessmentRepositoryInterface::class);
        $assessmentRepo->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect([
            ['Assessment_ID' => 'ASM-A', 'Teacher_ID' => 'TCH-A'],
            ['Assessment_ID' => 'ASM-B', 'Teacher_ID' => 'TCH-B'],
        ]));
        $this->app->instance(AssessmentRepositoryInterface::class, $assessmentRepo);

        $scores = collect([
            ['Score_ID' => 'SCR-OWN-ASM', 'Assessment_ID' => 'ASM-A', 'Student_ID' => 'STU-1'],
            ['Score_ID' => 'SCR-OTHER-ASM', 'Assessment_ID' => 'ASM-B', 'Student_ID' => 'STU-2'],
            ['Score_ID' => 'SCR-OWN-DIRECT', 'Teacher_ID' => 'TCH-A', 'Student_ID' => 'STU-2'],
            ['Score_ID' => 'SCR-OTHER-DIRECT', 'Teacher_ID' => 'TCH-B', 'Student_ID' => 'STU-1'],
            ['Score_ID' => 'SCR-UNOWNED', 'Student_ID' => 'STU-1'],
        ]);

        $scoreService = $this->mockService(ScoreService::class, [
            'getAll' => $scores,
            'getTeacherScoreScope' => [
                'schedule_ids' => ['SCH-A'],
                'class_ids' => ['CLS-A'],
                'student_ids' => ['STU-1', 'STU-2'],
                'assessment_ids' => ['ASM-A'],
                'schedule_student_ids' => ['SCH-A' => ['STU-1', 'STU-2']],
            ],
        ]);
        $scoreService->shouldReceive('isScoreInTeacherScope')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (array $score, string $teacherId): bool {
                if (($score['Teacher_ID'] ?? null) !== null) {
                    return ($score['Teacher_ID'] ?? '') === $teacherId;
                }

                return ($score['Assessment_ID'] ?? null) === 'ASM-A';
            });

        $assessmentConfig = $this->mockService(AssessmentConfigService::class, [
            'getActiveCategories' => [],
        ]);
        $scope = [
            'teacher_id' => 'TCH-A',
            'schedule_ids' => ['SCH-A'],
            'managed_class_ids' => ['CLS-A'],
            'teaching_class_ids' => ['CLS-A'],
            'student_ids' => ['STU-1', 'STU-2'],
            'assessment_ids' => ['ASM-A'],
            'schedule_student_ids' => ['SCH-A' => ['STU-1', 'STU-2']],
        ];
        $scopeResolver = Mockery::mock(TeacherScopeResolver::class);
        $scopeResolver->shouldReceive('resolveForUser')->zeroOrMoreTimes()->andReturn($scope);
        $scopeResolver->shouldReceive('resolveForTeacherId')->zeroOrMoreTimes()->with('TCH-A')->andReturn($scope);

        $controller = new TeacherWorkspaceController(
            $this->mockService(TeacherService::class, ['getAllTeachers' => collect([
                ['Teacher_ID' => 'TCH-A', 'User_ID' => 'USR-A'],
            ])]),
            $this->mockService(ClassService::class, []),
            $this->mockService(ScheduleService::class, []),
            $this->mockService(StudentService::class, ['getAllStudents' => collect([
                ['Student_ID' => 'STU-1', 'Full_Name' => 'Student One'],
                ['Student_ID' => 'STU-2', 'Full_Name' => 'Student Two'],
            ])]),
            $this->mockService(AttendanceService::class, []),
            $this->mockService(AttendanceRequestService::class, []),
            $this->mockService(SubjectService::class, []),
            $this->mockService(BatchService::class, []),
            $this->mockService(AssignmentService::class, []),
            $scoreService,
            $assessmentConfig,
            $scopeResolver
        );

        $view = $controller->scores();
        $visibleScoreIds = $view->getData()['scores']->pluck('Score_ID')->all();

        $this->assertSame(['SCR-OWN-ASM', 'SCR-OWN-DIRECT'], $visibleScoreIds);
    }

    private function mockService(string $class, array $methodReturns)
    {
        $mock = Mockery::mock($class);
        foreach ($methodReturns as $method => $returnValue) {
            $mock->shouldReceive($method)->zeroOrMoreTimes()->andReturn($returnValue);
        }

        return $mock;
    }
}
