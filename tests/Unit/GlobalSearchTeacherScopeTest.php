<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;
use App\Interfaces\GoogleSheets\AssignmentRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassEnrollmentRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\ScoreRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\SubjectRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Services\Core\GlobalSearchService;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class GlobalSearchTeacherScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->bindTeacherScopeRepositories();
        $this->actingAs(new GenericUser(['User_ID' => 'USR-TCH-A', 'id' => 'USR-TCH-A']));
    }

    public function test_teacher_search_does_not_leak_other_teacher_scores_or_assignments_in_same_class(): void
    {
        $results = (new GlobalSearchService)->search('OtherTeacherSecret', 'TEACHER', 'USR-TCH-A');

        $this->assertSame([], $results);
    }

    public function test_teacher_search_keeps_own_scores_and_assignments_visible(): void
    {
        $results = (new GlobalSearchService)->search('MineVisible', 'TEACHER', 'USR-TCH-A');

        $this->assertArrayHasKey('Nilai Kelas Saya', $results);
        $this->assertArrayHasKey('Tugas Kelas Saya', $results);
        $this->assertSame('SCR-A', $results['Nilai Kelas Saya'][0]['title']);
        $this->assertSame('MineVisible Assignment', $results['Tugas Kelas Saya'][0]['title']);
    }

    private function bindTeacherScopeRepositories(): void
    {
        $teacherRepository = Mockery::mock(TeacherRepositoryInterface::class);
        $teacherRepository->shouldReceive('fetchAll')->andReturn(collect([
            ['Teacher_ID' => 'TCH-A', 'User_ID' => 'USR-TCH-A'],
        ]));
        $this->app->instance(TeacherRepositoryInterface::class, $teacherRepository);

        $employeeRepository = Mockery::mock(EmployeeRepositoryInterface::class);
        $employeeRepository->shouldReceive('fetchAll')->andReturn(collect());
        $this->app->instance(EmployeeRepositoryInterface::class, $employeeRepository);

        $scheduleRepository = Mockery::mock(ScheduleRepositoryInterface::class);
        $scheduleRepository->shouldReceive('fetchAll')->andReturn(collect([
            ['Schedule_ID' => 'SCH-A', 'Teacher_ID' => 'TCH-A', 'Class_ID' => 'CLS-1', 'Subject_ID' => 'SUB-A', 'Is_Active' => 'TRUE'],
        ]));
        $this->app->instance(ScheduleRepositoryInterface::class, $scheduleRepository);

        $classRepository = Mockery::mock(ClassRepositoryInterface::class);
        $classRepository->shouldReceive('fetchAll')->andReturn(collect([
            ['Class_ID' => 'CLS-1', 'Class_Name' => 'Class One', 'Is_Active' => 'TRUE'],
        ]));
        $this->app->instance(ClassRepositoryInterface::class, $classRepository);

        $subjectRepository = Mockery::mock(SubjectRepositoryInterface::class);
        $subjectRepository->shouldReceive('fetchAll')->andReturn(collect([
            ['Subject_ID' => 'SUB-A', 'Subject_Name' => 'Subject A', 'Is_Active' => 'TRUE'],
        ]));
        $this->app->instance(SubjectRepositoryInterface::class, $subjectRepository);

        $studentRepository = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepository->shouldReceive('fetchAll')->andReturn(collect([
            ['Student_ID' => 'STU-1', 'User_ID' => 'USR-STU-1', 'Full_Name' => 'Student One', 'Class_ID' => 'CLS-1', 'Is_Active' => 'TRUE'],
        ]));
        $this->app->instance(StudentRepositoryInterface::class, $studentRepository);

        $enrollmentRepository = Mockery::mock(ClassEnrollmentRepositoryInterface::class);
        $enrollmentRepository->shouldReceive('fetchAll')->andReturn(collect());
        $this->app->instance(ClassEnrollmentRepositoryInterface::class, $enrollmentRepository);

        $assessmentRepository = Mockery::mock(AssessmentRepositoryInterface::class);
        $assessmentRepository->shouldReceive('getAll')->andReturn(collect([
            ['Assessment_ID' => 'ASM-A', 'Teacher_ID' => 'TCH-A'],
        ]));
        $this->app->instance(AssessmentRepositoryInterface::class, $assessmentRepository);

        $scoreRepository = Mockery::mock(ScoreRepositoryInterface::class);
        $scoreRepository->shouldReceive('fetchAll')->andReturn(collect([
            ['Score_ID' => 'SCR-A', 'Student_ID' => 'STU-1', 'Teacher_ID' => 'TCH-A', 'Assessment_Category' => 'MineVisible Score'],
            ['Score_ID' => 'SCR-B', 'Student_ID' => 'STU-1', 'Teacher_ID' => 'TCH-B', 'Assessment_Category' => 'OtherTeacherSecret Score'],
        ]));
        $this->app->instance(ScoreRepositoryInterface::class, $scoreRepository);

        $assignmentRepository = Mockery::mock(AssignmentRepositoryInterface::class);
        $assignmentRepository->shouldReceive('fetchAll')->andReturn(collect([
            ['Assignment_ID' => 'ASN-A', 'Teacher_ID' => 'TCH-A', 'Class_ID' => 'CLS-1', 'Title' => 'MineVisible Assignment'],
            ['Assignment_ID' => 'ASN-B', 'Teacher_ID' => 'TCH-B', 'Class_ID' => 'CLS-1', 'Title' => 'OtherTeacherSecret Assignment'],
        ]));
        $this->app->instance(AssignmentRepositoryInterface::class, $assignmentRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
