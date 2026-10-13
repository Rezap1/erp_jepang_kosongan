<?php

namespace Tests\Unit;

use App\Services\Academic\AssessmentService;
use App\Services\Academic\AttendanceService as AcademicAttendanceService;
use App\Services\Academic\ScheduleService;
use App\Services\Academic\ScoreService;
use App\Services\Attendance\AttendanceRequestService;
use App\Services\Core\ActivityLogService;
use App\Services\Core\BatchService;
use App\Services\Core\ClassService;
use App\Services\Core\NotificationService;
use App\Services\Core\ProgramService;
use App\Services\Core\StudentService;
use App\Services\Core\TeacherService;
use App\Services\Dashboard\AcademicDashboardService;
use App\Services\Dashboard\TeacherDashboardService;
use App\Support\Academic\TeacherScopeResolver;
use Illuminate\Auth\GenericUser;
use Mockery;
use Tests\TestCase;

class DashboardScorePendingTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_academic_dashboard_counts_assessment_pending_until_all_class_students_scored(): void
    {
        $service = new AcademicDashboardService(
            $this->mockService(AssessmentService::class, ['getAll' => collect([
                ['Assessment_ID' => 'ASM-1', 'Class_ID' => 'CLS-1'],
            ])]),
            $this->mockService(ScoreService::class, ['getAll' => collect([
                ['Score_ID' => 'SCR-1', 'Assessment_ID' => 'ASM-1', 'Student_ID' => 'STU-1', 'Score' => 90, 'Status' => 'PASS'],
            ])]),
            $this->mockService(ProgramService::class, ['getAllPrograms' => collect()]),
            $this->mockService(BatchService::class, ['getAllBatches' => collect()]),
            $this->mockService(ClassService::class, ['getAllClasses' => collect([['Class_ID' => 'CLS-1', 'Is_Active' => 'TRUE']])]),
            $this->mockService(TeacherService::class, ['getAllTeachers' => collect()]),
            $this->mockService(StudentService::class, ['getAllStudents' => collect([
                ['Student_ID' => 'STU-1', 'Class_ID' => 'CLS-1', 'Is_Active' => 'TRUE'],
                ['Student_ID' => 'STU-2', 'Class_ID' => 'CLS-1', 'Is_Active' => 'TRUE'],
            ])]),
            $this->mockService(ScheduleService::class, ['getAll' => collect()]),
            $this->mockService(AcademicAttendanceService::class, ['getAll' => collect()]),
            $this->mockService(AttendanceRequestService::class, ['getAll' => collect()]),
            $this->mockService(ActivityLogService::class, ['getAllLogs' => collect()]),
            $this->mockService(NotificationService::class, [])
        );

        $data = $service->getDashboardData();

        $this->assertSame(1, $data['kpi']['score_pending']);
    }

    public function test_teacher_dashboard_uses_assessment_ids_for_score_activity_scope(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-TCH', 'User_ID' => 'USR-TCH', 'Role' => 'TEACHER']));
        $scopeResolver = Mockery::mock(TeacherScopeResolver::class);
        $scopeResolver->shouldReceive('resolveForUser')->once()->andReturn([
            'teacher_id' => 'TCH-1',
            'identity' => ['employee_id' => '', 'teacher' => ['Teacher_ID' => 'TCH-1']],
            'schedules' => collect([
                ['Schedule_ID' => 'SCH-1', 'Teacher_ID' => 'TCH-1', 'Class_ID' => 'CLS-1', 'Day' => date('l')],
            ]),
            'managed_class_ids' => ['CLS-1'],
            'managed_student_ids' => ['STU-1', 'STU-2'],
            'managed_classes' => collect([['Class_ID' => 'CLS-1']]),
        ]);

        $service = new TeacherDashboardService(
            $this->mockService(ScheduleService::class, ['getAll' => collect([
                ['Schedule_ID' => 'SCH-1', 'Teacher_ID' => 'TCH-1', 'Class_ID' => 'CLS-1', 'Day' => date('l')],
            ])]),
            $this->mockService(AcademicAttendanceService::class, ['getAll' => collect()]),
            $this->mockService(AssessmentService::class, ['getAll' => collect([
                ['Assessment_ID' => 'ASM-1', 'Teacher_ID' => 'TCH-1', 'Class_ID' => 'CLS-1'],
            ])]),
            $this->mockService(ScoreService::class, ['getAll' => collect([
                ['Score_ID' => 'SCR-1', 'Assessment_ID' => 'ASM-1', 'Student_ID' => 'STU-1'],
            ])]),
            $this->mockService(TeacherService::class, ['getAllTeachers' => collect([
                ['Teacher_ID' => 'TCH-1', 'User_ID' => 'USR-TCH', 'Is_Active' => 'TRUE'],
            ])]),
            $this->mockService(StudentService::class, ['getAllStudents' => collect([
                ['Student_ID' => 'STU-1', 'Class_ID' => 'CLS-1', 'Is_Active' => 'TRUE'],
                ['Student_ID' => 'STU-2', 'Class_ID' => 'CLS-1', 'Is_Active' => 'TRUE'],
            ])]),
            $this->mockService(ClassService::class, ['getAllClasses' => collect()]),
            $this->mockService(ActivityLogService::class, ['getAllLogs' => collect([
                ['Module' => 'SCORE', 'Action' => 'CREATE', 'Reference_ID' => 'SCR-1', 'Created_At' => now()->toDateTimeString()],
            ])]),
            $this->mockService(NotificationService::class, ['UnreadCount' => 0]),
            $scopeResolver
        );

        $data = $service->getDashboardData();

        $this->assertSame(1, $data['kpi']['assessment_pending']);
        $this->assertCount(1, $data['recentActivities']);
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
