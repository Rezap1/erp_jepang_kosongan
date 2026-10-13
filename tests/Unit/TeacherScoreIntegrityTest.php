<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\AssessmentConfigRepositoryInterface;
use App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassEnrollmentRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\ScoreRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Services\Academic\AssessmentConfigService;
use App\Services\Academic\ScoreService;
use App\Services\Core\EnterpriseEventService;
use Illuminate\Auth\GenericUser;
use Mockery;
use Tests\TestCase;

class TeacherScoreIntegrityTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_assessment_config_rejects_unknown_partial_and_decimal_aspects(): void
    {
        $repository = Mockery::mock(AssessmentConfigRepositoryInterface::class);
        $repository->shouldReceive('getAll')->andReturn([
            ['Category_ID' => 'LANGUAGE', 'Category_Name' => 'Language', 'Is_Active' => 'TRUE', 'Aspects_JSON' => json_encode([
                ['id' => 'speaking', 'label' => 'Speaking'],
                ['id' => 'writing', 'label' => 'Writing'],
            ])],
        ]);
        $service = new AssessmentConfigService($repository);

        $this->assertFalse($service->validateAspectPayload('LANGUAGE', ['speaking' => '4']));
        $this->assertFalse($service->validateAspectPayload('LANGUAGE', ['speaking' => '4', 'writing' => '1.5']));
        $this->assertFalse($service->validateAspectPayload('LANGUAGE', ['speaking' => '4', 'writing' => '3', 'forged' => '5']));
        $this->assertTrue($service->validateAspectPayload('language', ['speaking' => '4', 'writing' => '3']));
    }

    public function test_teacher_score_scope_requires_owned_schedule_class_student_and_assessment(): void
    {
        $schedule = Mockery::mock(ScheduleRepositoryInterface::class);
        $schedule->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Schedule_ID' => 'SCH-A', 'Teacher_ID' => 'TCH-A', 'Class_ID' => 'CLS-A'],
            ['Schedule_ID' => 'SCH-B', 'Teacher_ID' => 'TCH-B', 'Class_ID' => 'CLS-B'],
        ]));
        $classes = Mockery::mock(ClassRepositoryInterface::class);
        $classes->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Class_ID' => 'CLS-A', 'Homeroom_Teacher_ID' => ''],
            ['Class_ID' => 'CLS-B', 'Homeroom_Teacher_ID' => 'TCH-B'],
        ]));
        $enrollment = Mockery::mock(ClassEnrollmentRepositoryInterface::class);
        $enrollment->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect([
            ['Class_ID' => 'CLS-A', 'Student_ID' => 'STU-A'],
            ['Class_ID' => 'CLS-B', 'Student_ID' => 'STU-B'],
        ]));
        $students = Mockery::mock(StudentRepositoryInterface::class);
        $students->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Student_ID' => 'STU-A', 'Class_ID' => 'CLS-A'],
            ['Student_ID' => 'STU-B', 'Class_ID' => 'CLS-B'],
        ]));
        $assessments = Mockery::mock(AssessmentRepositoryInterface::class);
        $assessments->shouldReceive('getAll')->once()->andReturn(collect([
            ['Assessment_ID' => 'ASM-A', 'Teacher_ID' => 'TCH-A', 'Class_ID' => 'CLS-A'],
            ['Assessment_ID' => 'ASM-B', 'Teacher_ID' => 'TCH-B', 'Class_ID' => 'CLS-B'],
        ]));

        $this->app->instance(ScheduleRepositoryInterface::class, $schedule);
        $this->app->instance(ClassRepositoryInterface::class, $classes);
        $this->app->instance(ClassEnrollmentRepositoryInterface::class, $enrollment);
        $teacher = Mockery::mock(TeacherRepositoryInterface::class);
        $teacher->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Teacher_ID' => 'TCH-A', 'User_ID' => 'USR-A', 'Is_Active' => 'TRUE'],
            ['Teacher_ID' => 'TCH-B', 'User_ID' => 'USR-B', 'Is_Active' => 'TRUE'],
        ]));
        $this->app->instance(TeacherRepositoryInterface::class, $teacher);

        $scoreService = new ScoreService(
            Mockery::mock(ScoreRepositoryInterface::class),
            Mockery::mock(EnterpriseEventService::class),
            $assessments,
            $students
        );
        $scope = $scoreService->getTeacherScoreScope('TCH-A');

        $this->assertTrue($scoreService->isScoreInTeacherScope(['Teacher_ID' => 'TCH-A', 'Student_ID' => 'STU-A'], 'TCH-A', $scope));
        $this->assertFalse($scoreService->isScoreInTeacherScope(['Teacher_ID' => 'TCH-A', 'Student_ID' => 'STU-B'], 'TCH-A', $scope));
        $this->assertFalse($scoreService->isScoreInTeacherScope(['Teacher_ID' => 'TCH-B', 'Student_ID' => 'STU-A'], 'TCH-A', $scope));
        $this->assertTrue($scoreService->isScoreInTeacherScope(['Assessment_ID' => 'ASM-A', 'Student_ID' => 'STU-A'], 'TCH-A', $scope));
        $this->assertFalse($scoreService->isScoreInTeacherScope(['Assessment_ID' => 'ASM-B', 'Student_ID' => 'STU-A'], 'TCH-A', $scope));
        $this->assertTrue($scoreService->isStudentInSchedule('STU-A', 'SCH-A', $scope));
        $this->assertFalse($scoreService->isStudentInSchedule('STU-B', 'SCH-A', $scope));
    }

    public function test_score_create_fails_closed_when_fresh_readback_is_missing(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-T', 'User_ID' => 'USR-T']));
        $repository = new class implements ScoreRepositoryInterface
        {
            public function fetchAll()
            {
                return collect();
            }

            public function findById(string $id)
            {
                return null;
            }

            public function findByIdFresh(string $id)
            {
                return null;
            }

            public function generateNewId(string $prefix, int $padding = 6): string
            {
                return 'SCR-TEST';
            }

            public function create(array $data)
            {
                return $data;
            }

            public function update(string $id, array $data)
            {
                return $data;
            }

            public function softDelete(string $id)
            {
                return true;
            }

            public function clearCache() {}
        };
        $student = Mockery::mock(StudentRepositoryInterface::class);
        $student->shouldReceive('findById')->once()->with('STU-A')->andReturn(['Student_ID' => 'STU-A']);
        $service = new ScoreService(
            $repository,
            Mockery::mock(EnterpriseEventService::class),
            Mockery::mock(AssessmentRepositoryInterface::class),
            $student
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('tidak dapat diverifikasi');
        $service->create(['Student_ID' => 'STU-A', 'Assessment_Category' => 'GENERAL', 'Score' => 80]);
    }

    public function test_repeated_score_submission_is_idempotent_by_durable_score_id(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-T', 'User_ID' => 'USR-T']));
        $rows = [];
        $repository = new class($rows) implements ScoreRepositoryInterface
        {
            public array $rows;

            public int $creates = 0;

            public function __construct(array &$rows)
            {
                $this->rows = &$rows;
            }

            public function fetchAll()
            {
                return collect(array_values($this->rows));
            }

            public function findById(string $id)
            {
                return $this->rows[$id] ?? null;
            }

            public function findByIdFresh(string $id)
            {
                return $this->rows[$id] ?? null;
            }

            public function generateNewId(string $prefix, int $padding = 6): string
            {
                return 'SCR-TEST';
            }

            public function create(array $data)
            {
                $this->creates++;
                $this->rows[$data['Score_ID']] = $data;

                return true;
            }

            public function update(string $id, array $data)
            {
                $this->rows[$id] = array_merge($this->rows[$id] ?? [], $data);

                return true;
            }

            public function softDelete(string $id)
            {
                return true;
            }

            public function clearCache() {}
        };
        $student = Mockery::mock(StudentRepositoryInterface::class);
        $student->shouldReceive('findById')->zeroOrMoreTimes()->with('STU-A')->andReturn(['Student_ID' => 'STU-A']);
        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldReceive('dispatch')->once();
        $service = new ScoreService($repository, $events, Mockery::mock(AssessmentRepositoryInterface::class), $student);

        $payload = ['Score_ID' => 'SCR-'.str_repeat('a', 8).'-'.str_repeat('b', 4).'-'.str_repeat('c', 4).'-'.str_repeat('d', 4).'-'.str_repeat('e', 12), 'Student_ID' => 'STU-A', 'Schedule_ID' => 'SCH-A', 'Assessment_Category' => 'GENERAL', 'Score' => 80];
        $first = $service->create($payload);
        $second = $service->create($payload);

        $this->assertSame(1, $repository->creates);
        $this->assertSame($first['Score_ID'], $second['Score_ID']);
    }
}
