<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Services\Quiz\QuizScopeService;
use Illuminate\Auth\GenericUser;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class H877QuizScopeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_teacher_scope_uses_explicit_class_assignment_and_excludes_inactive_or_foreign_classes(): void
    {
        $teachers = Mockery::mock(TeacherRepositoryInterface::class);
        $students = Mockery::mock(StudentRepositoryInterface::class);
        $schedules = Mockery::mock(ScheduleRepositoryInterface::class);
        $classes = Mockery::mock(ClassRepositoryInterface::class);
        $teachers->shouldReceive('fetchAll')->once()->andReturn(collect([['Teacher_ID' => 'T1', 'User_ID' => 'U1', 'Is_Active' => 'TRUE']]));
        $students->shouldNotReceive('fetchAll');
        $schedules->shouldNotReceive('fetchAll');
        $classes->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Class_ID' => 'C1', 'Class_Name' => 'Sakura', 'Homeroom_Teacher_ID' => 'T1', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'C2', 'Class_Name' => 'Inactive', 'Homeroom_Teacher_ID' => 'T1', 'Is_Active' => 'FALSE'],
            ['Class_ID' => 'C3', 'Class_Name' => 'Foreign', 'Homeroom_Teacher_ID' => 'T2', 'Is_Active' => 'TRUE'],
        ]));
        $scope = new QuizScopeService($teachers, $students, $schedules, $classes);
        $teacher = $scope->teacherForUser(new GenericUser(['User_ID' => 'U1']));
        $this->assertSame('T1', $teacher['Teacher_ID']);
        $this->assertSame(['C1'], $scope->classesForTeacher('T1')->pluck('Class_ID')->all());
    }

    public function test_missing_teacher_student_and_forged_class_fail_closed(): void
    {
        $teachers = Mockery::mock(TeacherRepositoryInterface::class);
        $students = Mockery::mock(StudentRepositoryInterface::class);
        $schedules = Mockery::mock(ScheduleRepositoryInterface::class);
        $classes = Mockery::mock(ClassRepositoryInterface::class);
        $teachers->shouldReceive('fetchAll')->andReturn(collect());
        $students->shouldReceive('fetchAll')->andReturn(collect([['Student_ID' => 'S1', 'User_ID' => 'US', 'Class_ID' => '', 'Is_Active' => 'TRUE']]));
        $schedules->shouldReceive('fetchAll')->andReturn(collect());
        $classes->shouldReceive('fetchAll')->andReturn(collect());
        $scope = new QuizScopeService($teachers, $students, $schedules, $classes);

        foreach ([fn () => $scope->teacherForUser(new GenericUser(['User_ID' => 'UT'])), fn () => $scope->studentForUser(new GenericUser(['User_ID' => 'US'])), fn () => $scope->assertTeacherClass('T1', 'FORGED')] as $operation) {
            try {
                $operation();
                $this->fail('Scope resolution must fail closed.');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
    }

    public function test_request_snapshots_reuse_master_reads_for_counts_roster_and_repeated_authorization(): void
    {
        $teachers = Mockery::mock(TeacherRepositoryInterface::class);
        $students = Mockery::mock(StudentRepositoryInterface::class);
        $schedules = Mockery::mock(ScheduleRepositoryInterface::class);
        $classes = Mockery::mock(ClassRepositoryInterface::class);
        $teachers->shouldReceive('fetchAll')->once()->andReturn(collect([['Teacher_ID' => 'T1', 'User_ID' => 'U1', 'Is_Active' => 'TRUE']]));
        $students->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Student_ID' => 'S1', 'Class_ID' => 'C1', 'Full_Name' => 'Aiko', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'S2', 'Class_ID' => 'C1', 'Full_Name' => 'Budi', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'S3', 'Class_ID' => 'C2', 'Full_Name' => 'Cici', 'Is_Active' => 'TRUE'],
        ]));
        $schedules->shouldNotReceive('fetchAll');
        $classes->shouldReceive('fetchAll')->once()->andReturn(collect([['Class_ID' => 'C1', 'Class_Name' => 'Kelas A', 'Homeroom_Teacher_ID' => 'T1', 'Is_Active' => 'TRUE']]));
        $scope = new QuizScopeService($teachers, $students, $schedules, $classes);

        $scope->teacherForUser(new GenericUser(['User_ID' => 'U1']));
        $this->assertSame(['C1'], $scope->classesForTeacher('T1')->pluck('Class_ID')->all());
        $this->assertSame(['C1'], $scope->classesForTeacher('T1')->pluck('Class_ID')->all());
        $this->assertSame(2, $scope->studentCountsForClasses(['C1'])->get('C1'));
        $this->assertSame(['S1', 'S2'], $scope->studentsForClass('C1')->pluck('Student_ID')->all());
    }
}
