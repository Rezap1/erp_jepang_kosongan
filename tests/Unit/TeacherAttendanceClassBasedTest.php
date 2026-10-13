<?php

namespace Tests\Unit;

use App\Http\Controllers\Academic\TeacherWorkspaceController;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Services\Academic\AssessmentConfigService;
use App\Services\Academic\AssessmentService;
use App\Services\Academic\AttendanceService;
use App\Services\Academic\ScheduleService;
use App\Services\Academic\ScoreService;
use App\Services\Academic\SubjectService;
use App\Services\Attendance\AttendanceRequestService;
use App\Services\Core\ActivityLogService;
use App\Services\Core\AssignmentService;
use App\Services\Core\BatchService;
use App\Services\Core\ClassService;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\NotificationService;
use App\Services\Core\StudentService;
use App\Services\Core\TeacherService;
use App\Services\Dashboard\TeacherDashboardService;
use App\Support\Academic\TeacherScopeResolver;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TeacherAttendanceClassBasedTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_teacher_sees_class_qr_attendance_for_owned_class(): void
    {
        $groups = $this->teacherGroups([
            $this->attendance('STU-A', 'CLASS-A', 'CLASS_QR', 'PRESENT'),
        ]);

        $student = $groups[0]['Students']->firstWhere('Student_ID', 'STU-A');
        $this->assertSame('PRESENT', $student['Status_Key']);
        $this->assertSame('ATT-STU-A', $student['Attendance_ID']);
    }

    public function test_teacher_sees_students_without_class_qr_as_belum_absen(): void
    {
        $groups = $this->teacherGroups([]);

        $student = $groups[0]['Students']->firstWhere('Student_ID', 'STU-A');
        $this->assertSame('Belum Absen', $student['Display_Status']);
        $this->assertNull($student['Attendance']);
    }

    public function test_teacher_class_grouping_does_not_mix_classes(): void
    {
        $groups = $this->teacherGroups([
            $this->attendance('STU-A', 'CLASS-A', 'CLASS_QR', 'PRESENT'),
        ]);

        $this->assertSame(['CLASS-A'], $groups->pluck('Class_ID')->all());
        $this->assertNotContains('STU-B', $groups[0]['Students']->pluck('Student_ID')->all());
    }

    public function test_teacher_cannot_see_class_qr_attendance_from_unowned_class(): void
    {
        $groups = $this->teacherGroups([
            $this->attendance('STU-B', 'CLASS-B', 'CLASS_QR', 'PRESENT'),
        ]);

        $this->assertCount(1, $groups);
        $this->assertNull($groups[0]['Students']->firstWhere('Student_ID', 'STU-A')['Attendance']);
    }

    public function test_teacher_class_filter_cannot_escape_owned_classes(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Anda tidak memiliki akses ke kelas ini.');

        $this->teacherGroups([
            $this->attendance('STU-A', 'CLASS-A', 'CLASS_QR', 'PRESENT'),
        ], 'CLASS-B');
    }

    public function test_schedule_based_attendance_still_uses_teacher_schedule_scope(): void
    {
        $groups = $this->teacherGroups([
            [
                'Attendance_ID' => 'ATT-SCHEDULE-A', 'Student_ID' => 'STU-A',
                'Schedule_ID' => 'SCHEDULE-A', 'Attendance_Date' => '2026-08-30',
                'Attendance_Type' => 'SCHEDULE', 'Status' => 'PRESENT', 'Is_Active' => 'TRUE',
            ],
            [
                'Attendance_ID' => 'ATT-SCHEDULE-B', 'Student_ID' => 'STU-B',
                'Schedule_ID' => 'SCHEDULE-B', 'Attendance_Date' => '2026-08-30',
                'Attendance_Type' => 'SCHEDULE', 'Status' => 'PRESENT', 'Is_Active' => 'TRUE',
            ],
        ]);

        $this->assertSame('ATT-SCHEDULE-A', $groups[0]['Students']->firstWhere('Student_ID', 'STU-A')['Attendance_ID']);
        $this->assertNotContains('CLASS-B', $groups->pluck('Class_ID')->all());
    }

    public function test_unowned_schedule_cannot_leak_through_persisted_class_id(): void
    {
        $groups = $this->teacherGroups([
            [
                'Attendance_ID' => 'ATT-UNOWNED-SCHEDULE', 'Student_ID' => 'STU-A',
                'Class_ID' => 'CLASS-A', 'Schedule_ID' => 'SCHEDULE-B',
                'Attendance_Date' => '2026-08-30', 'Attendance_Type' => 'SCHEDULE',
                'Status' => 'PRESENT', 'Is_Active' => 'TRUE',
            ],
        ]);

        $this->assertNull($groups[0]['Students']->firstWhere('Student_ID', 'STU-A')['Attendance']);
    }

    public function test_legacy_row_with_unowned_schedule_cannot_leak_through_owned_class(): void
    {
        $groups = $this->teacherGroups([
            [
                'Attendance_ID' => 'ATT-LEGACY-UNOWNED', 'Student_ID' => 'STU-A',
                'Class_ID' => 'CLASS-A', 'Schedule_ID' => 'SCHEDULE-B',
                'Attendance_Date' => '2026-08-30', 'Attendance_Type' => '',
                'Status' => 'PRESENT', 'Is_Active' => 'TRUE',
            ],
        ]);

        $this->assertNull($groups[0]['Students']->firstWhere('Student_ID', 'STU-A')['Attendance']);
    }

    public function test_legacy_class_candidate_is_visible_in_owned_class(): void
    {
        $groups = $this->teacherGroups([[
            'Attendance_ID' => 'ATT-LEGACY-CLASS', 'Student_ID' => 'STU-A',
            'Schedule_ID' => 'CLASS-A', 'Attendance_Date' => '2026-08-30',
            'Attendance_Type' => '', 'Status' => 'PRESENT', 'Is_Active' => 'TRUE',
        ]]);

        $this->assertSame('ATT-LEGACY-CLASS', $groups[0]['Students']->firstWhere('Student_ID', 'STU-A')['Attendance_ID']);
        $this->assertNull($groups[0]['Students']->firstWhere('Student_ID', 'STU-A')['Attendance']['Resolved_Schedule_ID']);
    }

    public function test_unowned_schedule_in_owned_class_is_excluded(): void
    {
        $groups = $this->teacherGroups([[
            'Attendance_ID' => 'ATT-UNOWNED-SCHEDULE-SAME-CLASS', 'Student_ID' => 'STU-A',
            'Class_ID' => 'CLASS-A', 'Schedule_ID' => 'SCHEDULE-B',
            'Attendance_Date' => '2026-08-30', 'Attendance_Type' => 'SCHEDULE',
            'Status' => 'PRESENT', 'Is_Active' => 'TRUE',
        ]]);

        $this->assertNull($groups[0]['Students']->firstWhere('Student_ID', 'STU-A')['Attendance']);
    }

    public function test_class_qr_does_not_require_schedule_id(): void
    {
        $groups = $this->teacherGroups([
            $this->attendance('STU-A', 'CLASS-A', 'CLASS_QR', 'PRESENT', ''),
        ]);

        $this->assertSame('Hadir', $groups[0]['Students']->firstWhere('Student_ID', 'STU-A')['Display_Status']);
    }

    public function test_class_manual_attendance_can_be_grouped_by_class(): void
    {
        $groups = $this->teacherGroups([
            $this->attendance('STU-A', 'CLASS-A', 'CLASS_MANUAL', 'LATE'),
        ]);

        $this->assertSame('Terlambat', $groups[0]['Students']->firstWhere('Student_ID', 'STU-A')['Display_Status']);
    }

    public function test_employee_attendance_is_not_in_teacher_student_attendance(): void
    {
        $groups = $this->teacherGroups([
            $this->attendance('STU-A', 'CLASS-A', 'CLASS_QR', 'PRESENT'),
            [
                'Attendance_ID' => 'ATT-EMPLOYEE', 'Employee_ID' => 'EMP-1',
                'Attendance_Type' => 'EMPLOYEE', 'Attendance_Date' => '2026-08-30',
                'Status' => 'PRESENT', 'Is_Active' => 'TRUE',
            ],
        ]);

        $this->assertNotContains('ATT-EMPLOYEE', $groups[0]['Students']->pluck('Attendance_ID')->all());
    }

    public function test_teacher_summary_uses_roster_count(): void
    {
        $groups = $this->teacherGroups([
            $this->attendance('STU-A', 'CLASS-A', 'CLASS_QR', 'PRESENT'),
        ]);

        $this->assertSame(2, $groups[0]['Total']);
        $this->assertSame(1, $groups[0]['Hadir']);
        $this->assertSame(1, $groups[0]['Belum_Absen']);
    }

    public function test_class_based_pending_logic_does_not_require_schedule_id(): void
    {
        $data = $this->teacherDashboard([
            'Attendance_ID' => 'ATT-CLASS-QR', 'Student_ID' => 'STU-A',
            'Class_ID' => 'CLASS-A', 'Schedule_ID' => '',
            'Attendance_Date' => date('Y-m-d'), 'Attendance_Type' => 'CLASS_QR',
            'Status' => 'PRESENT', 'Is_Active' => 'TRUE',
        ]);

        $this->assertSame(0, $data['kpi']['attendance_pending']);
    }

    public function test_teacher_dashboard_supports_class_based_attendance(): void
    {
        $data = $this->teacherDashboard([
            'Attendance_ID' => 'ATT-CLASS-MANUAL', 'Student_ID' => 'STU-A',
            'Class_ID' => 'CLASS-A', 'Schedule_ID' => '',
            'Attendance_Date' => date('Y-m-d'), 'Attendance_Type' => 'CLASS_MANUAL',
            'Status' => 'LATE', 'Is_Active' => 'TRUE',
        ]);

        $this->assertSame(1, $data['kpi']['attendance_today']);
        $this->assertSame(1, $data['attendanceStats']['hadir']);
    }

    public function test_teacher_dashboard_does_not_double_count_class_and_schedule_attendance(): void
    {
        $data = $this->teacherDashboard([
            [
                'Attendance_ID' => 'ATT-CLASS-QR', 'Student_ID' => 'STU-A',
                'Class_ID' => 'CLASS-A', 'Schedule_ID' => '',
                'Attendance_Date' => date('Y-m-d'), 'Attendance_Type' => 'CLASS_QR',
                'Status' => 'PRESENT', 'Is_Active' => 'TRUE',
            ],
            [
                'Attendance_ID' => 'ATT-SCHEDULE', 'Student_ID' => 'STU-A',
                'Class_ID' => 'CLASS-A', 'Schedule_ID' => 'SCHEDULE-A',
                'Attendance_Date' => date('Y-m-d'), 'Attendance_Type' => 'SCHEDULE',
                'Status' => 'PRESENT', 'Is_Active' => 'TRUE',
            ],
        ]);

        $this->assertSame(1, $data['kpi']['attendance_today']);
    }

    public function test_teacher_attendance_export_includes_class_qr_without_schedule_id(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-T1', 'User_ID' => 'USR-T1', 'Role' => 'TEACHER']));

        $controller = $this->makeController([
            $this->attendance('STU-A', 'CLASS-A', 'CLASS_QR', 'PRESENT', ''),
            $this->attendance('STU-B', 'CLASS-B', 'CLASS_QR', 'PRESENT', ''),
        ]);

        $response = $controller->exportAttendancesCsv();
        $csv = $response->getContent();

        $this->assertStringContainsString('Andi', $csv);
        $this->assertStringContainsString('Kelas A', $csv);
        $this->assertStringContainsString('Hadir', $csv);
        $this->assertStringNotContainsString('Budi', $csv);
    }

    private function teacherGroups(array $attendanceRows, ?string $classFilter = null)
    {
        $this->actingAs(new GenericUser(['id' => 'USR-T1', 'User_ID' => 'USR-T1', 'Role' => 'TEACHER']));
        $request = Request::create('/teacher/attendances', 'GET', [
            'date' => '2026-08-30',
            'class_id' => $classFilter,
        ]);
        $this->app->instance('request', $request);

        $controller = $this->makeController($attendanceRows);
        $view = $controller->attendances();

        return collect($view->getData()['attendanceGroups']);
    }

    private function makeController(array $attendanceRows): TeacherWorkspaceController
    {
        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect($attendanceRows));
        $attendanceService = new AttendanceService($attendanceRepo, Mockery::mock(EnterpriseEventService::class));

        return new TeacherWorkspaceController(
            Mockery::mock(TeacherService::class)->shouldReceive('getAllTeachers')->zeroOrMoreTimes()->andReturn(collect([
                ['Teacher_ID' => 'T1', 'User_ID' => 'USR-T1'],
            ]))->getMock(),
            Mockery::mock(ClassService::class)->shouldReceive('getAllClasses')->zeroOrMoreTimes()->andReturn(collect($this->classes()))->getMock(),
            Mockery::mock(ScheduleService::class)->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect($this->schedules()))->getMock(),
            Mockery::mock(StudentService::class)->shouldReceive('getAllStudents')->zeroOrMoreTimes()->andReturn(collect($this->students()))->getMock(),
            $attendanceService,
            Mockery::mock(AttendanceRequestService::class),
            Mockery::mock(SubjectService::class)->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect())->getMock(),
            Mockery::mock(BatchService::class),
            Mockery::mock(AssignmentService::class),
            Mockery::mock(ScoreService::class),
            Mockery::mock(AssessmentConfigService::class),
            $this->workspaceScopeResolver()
        );
    }

    private function teacherDashboard(array $attendance): array
    {
        $this->actingAs(new GenericUser(['id' => 'USR-T1', 'User_ID' => 'USR-T1', 'Role' => 'TEACHER']));
        $day = date('l');

        $attendanceRows = array_is_list($attendance) ? $attendance : [$attendance];
        $scopeResolver = Mockery::mock(TeacherScopeResolver::class);
        $scopeResolver->shouldReceive('resolveForUser')->once()->andReturn([
            'teacher_id' => 'T1',
            'identity' => ['employee_id' => '', 'teacher' => ['Teacher_ID' => 'T1']],
            'schedules' => collect([
                ['Schedule_ID' => 'SCHEDULE-A', 'Teacher_ID' => 'T1', 'Class_ID' => 'CLASS-A', 'Day' => $day],
            ]),
            'managed_class_ids' => ['CLASS-A'],
            'managed_student_ids' => ['STU-A'],
            'managed_classes' => collect([['Class_ID' => 'CLASS-A', 'Class_Name' => 'Kelas A']]),
        ]);

        $service = new TeacherDashboardService(
            Mockery::mock(ScheduleService::class)->shouldReceive('getAll')->andReturn(collect([
                ['Schedule_ID' => 'SCHEDULE-A', 'Teacher_ID' => 'T1', 'Class_ID' => 'CLASS-A', 'Day' => $day],
            ]))->getMock(),
            Mockery::mock(AttendanceService::class)->shouldReceive('getAll')->andReturn(collect($attendanceRows))->getMock(),
            Mockery::mock(AssessmentService::class)->shouldReceive('getAll')->andReturn(collect())->getMock(),
            Mockery::mock(ScoreService::class)->shouldReceive('getAll')->andReturn(collect())->getMock(),
            Mockery::mock(TeacherService::class)->shouldReceive('getAllTeachers')->andReturn(collect([
                ['Teacher_ID' => 'T1', 'User_ID' => 'USR-T1'],
            ]))->getMock(),
            Mockery::mock(StudentService::class)->shouldReceive('getAllStudents')->andReturn(collect([
                ['Student_ID' => 'STU-A', 'Class_ID' => 'CLASS-A', 'Is_Active' => 'TRUE'],
            ]))->getMock(),
            Mockery::mock(ClassService::class)->shouldReceive('getAllClasses')->andReturn(collect())->getMock(),
            Mockery::mock(ActivityLogService::class)->shouldReceive('getAllLogs')->andReturn(collect())->getMock(),
            Mockery::mock(NotificationService::class)->shouldReceive('UnreadCount')->andReturn(0)->getMock(),
            $scopeResolver
        );

        return $service->getDashboardData();
    }

    private function attendance(string $studentId, string $classId, string $type, string $status, string $scheduleId = ''): array
    {
        return [
            'Attendance_ID' => 'ATT-'.$studentId,
            'Student_ID' => $studentId,
            'Class_ID' => $classId,
            'Schedule_ID' => $scheduleId,
            'Attendance_Date' => '2026-08-30',
            'Attendance_Type' => $type,
            'Status' => $status,
            'Is_Active' => 'TRUE',
        ];
    }

    private function classes(): array
    {
        return [
            ['Class_ID' => 'CLASS-A', 'Class_Name' => 'Kelas A', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLASS-B', 'Class_Name' => 'Kelas B', 'Is_Active' => 'TRUE'],
        ];
    }

    private function schedules(): array
    {
        return [
            ['Schedule_ID' => 'SCHEDULE-A', 'Teacher_ID' => 'T1', 'Class_ID' => 'CLASS-A'],
            ['Schedule_ID' => 'SCHEDULE-B', 'Teacher_ID' => 'T2', 'Class_ID' => 'CLASS-B'],
        ];
    }

    private function students(): array
    {
        return [
            ['Student_ID' => 'STU-A', 'Full_Name' => 'Andi', 'Class_ID' => 'CLASS-A', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STU-C', 'Full_Name' => 'Citra', 'Class_ID' => 'CLASS-A', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STU-B', 'Full_Name' => 'Budi', 'Class_ID' => 'CLASS-B', 'Is_Active' => 'TRUE'],
        ];
    }

    private function workspaceScopeResolver(): TeacherScopeResolver
    {
        $scope = [
            'teacher_id' => 'T1',
            'schedule_ids' => ['SCHEDULE-A'],
            'class_ids' => ['CLASS-A'],
            'managed_class_ids' => ['CLASS-A'],
            'teaching_class_ids' => ['CLASS-A'],
            'subject_ids' => [],
            'student_ids' => ['STU-A', 'STU-C'],
            'managed_student_ids' => ['STU-A', 'STU-C'],
            'teaching_student_ids' => ['STU-A', 'STU-C'],
            'assessment_ids' => [],
            'students_by_class' => ['CLASS-A' => ['STU-A', 'STU-C']],
            'managed_students_by_class' => ['CLASS-A' => ['STU-A', 'STU-C']],
            'teaching_students_by_class' => ['CLASS-A' => ['STU-A', 'STU-C']],
            'schedule_student_ids' => ['SCHEDULE-A' => ['STU-A', 'STU-C']],
            'schedules' => collect([$this->schedules()[0]]),
            'classes' => collect([$this->classes()[0]]),
            'managed_classes' => collect([$this->classes()[0]]),
            'students' => collect([$this->students()[0], $this->students()[1]]),
        ];
        $resolver = Mockery::mock(TeacherScopeResolver::class);
        $resolver->shouldReceive('resolveForUser')->zeroOrMoreTimes()->andReturn($scope);
        $resolver->shouldReceive('resolveForTeacherId')->zeroOrMoreTimes()->with('T1')->andReturn($scope);

        return $resolver;
    }
}
