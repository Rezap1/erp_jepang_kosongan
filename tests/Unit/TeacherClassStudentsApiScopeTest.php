<?php

namespace Tests\Unit;

use App\Http\Controllers\Core\ClassController;
use App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassEnrollmentRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Services\Core\BatchService;
use App\Services\Core\ClassService;
use App\Services\Core\ProgramService;
use App\Services\Core\RoleService;
use App\Services\Core\StudentService;
use App\Services\Core\TeacherService;
use Illuminate\Auth\GenericUser;
use Mockery;
use Tests\TestCase;

class TeacherClassStudentsApiScopeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_teacher_role_name_is_checked_case_insensitively_for_class_students_api(): void
    {
        $this->actingAs(new GenericUser([
            'id' => 'USR-T',
            'User_ID' => 'USR-T',
            'Role_ID' => 'ROLE-T',
        ]));

        $roleService = Mockery::mock(RoleService::class);
        $roleService->shouldReceive('getAllRoles')->once()->andReturn(collect([
            ['Role_ID' => 'ROLE-T', 'Role_Name' => 'Teacher'],
        ]));
        $this->app->instance(RoleService::class, $roleService);

        $teacherRepo = Mockery::mock(TeacherRepositoryInterface::class);
        $teacherRepo->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Teacher_ID' => 'TCH-1', 'User_ID' => 'USR-T'],
        ]));
        $this->app->instance(TeacherRepositoryInterface::class, $teacherRepo);

        $scheduleRepo = Mockery::mock(ScheduleRepositoryInterface::class);
        $scheduleRepo->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Schedule_ID' => 'SCH-1', 'Teacher_ID' => 'TCH-1', 'Class_ID' => 'CLS-1'],
        ]));
        $this->app->instance(ScheduleRepositoryInterface::class, $scheduleRepo);

        $classRepo = Mockery::mock(ClassRepositoryInterface::class);
        $classRepo->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Class_ID' => 'CLS-1', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-2', 'Is_Active' => 'TRUE'],
        ]));
        $this->app->instance(ClassRepositoryInterface::class, $classRepo);

        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Student_ID' => 'STU-1', 'Class_ID' => 'CLS-1', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STU-2', 'Class_ID' => 'CLS-2', 'Is_Active' => 'TRUE'],
        ]));
        $this->app->instance(StudentRepositoryInterface::class, $studentRepo);

        $enrollmentRepo = Mockery::mock(ClassEnrollmentRepositoryInterface::class);
        $enrollmentRepo->shouldNotReceive('fetchAll');
        $this->app->instance(ClassEnrollmentRepositoryInterface::class, $enrollmentRepo);

        $assessmentRepo = Mockery::mock(AssessmentRepositoryInterface::class);
        $assessmentRepo->shouldReceive('getAll')->once()->andReturn(collect());
        $this->app->instance(AssessmentRepositoryInterface::class, $assessmentRepo);

        $studentService = Mockery::mock(StudentService::class);
        $studentService->shouldNotReceive('getAllStudents');

        $controller = new ClassController(
            $this->mockService(ClassService::class, []),
            $this->mockService(ProgramService::class, []),
            $this->mockService(BatchService::class, []),
            $this->mockService(TeacherService::class, [])
        );

        $response = $controller->getStudents('CLS-2', $studentService);

        $this->assertSame(403, $response->getStatusCode());
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
