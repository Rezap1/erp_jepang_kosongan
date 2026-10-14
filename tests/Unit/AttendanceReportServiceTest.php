<?php

namespace Tests\Unit;

use App\Http\Controllers\Academic\AttendanceReportController;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassEnrollmentRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Academic\AttendanceLegacyClassifier;
use App\Services\Academic\AttendanceReportService;
use App\Services\Core\RoleService;
use App\Support\Academic\TeacherScopeResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AttendanceReportServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_daily_report_uses_only_selected_class_period_and_canonical_rows(): void
    {
        $service = $this->service([
            $this->attendance('ATT-1', 'STU-A', 'CLS-A', '2026-09-21', 'PRESENT'),
            $this->attendance('ATT-2', 'STU-A', 'CLS-A', '2026-09-21', 'LATE'),
            $this->attendance('ATT-3', 'STU-B', 'CLS-B', '2026-09-21', 'ABSENT'),
            $this->attendance('ATT-4', 'STU-A', 'CLS-A', '2026-09-22', 'SICK'),
            ['Attendance_ID' => 'ATT-EMP', 'Employee_ID' => 'EMP-1', 'Attendance_Type' => 'EMPLOYEE', 'Attendance_Date' => '2026-09-21'],
            $this->attendance('ATT-BAD', 'STU-A', 'CLS-A', 'not-a-date', 'ABSENT'),
        ]);

        $report = $service->build($this->admin(), 'ADMINISTRATOR', [
            'class_id' => 'CLS-A', 'report_type' => 'harian', 'daily_date' => '2026-09-21',
        ]);

        $this->assertSame(2, $report['total_records']);
        $this->assertSame(1, $report['summary']['PRESENT']['count']);
        $this->assertSame(1, $report['summary']['LATE']['count']);
        $this->assertSame(0, $report['summary']['ABSENT']['count']);
        $this->assertSame(['ATT-1', 'ATT-2'], $report['rows']->pluck('attendance_id')->all());
        $this->assertTrue($report['student_recap']->isEmpty());
    }

    public function test_missing_attendance_is_not_invented_as_absent(): void
    {
        $report = $this->service([
            $this->attendance('ATT-1', 'STU-A', 'CLS-A', '2026-09-21', 'PRESENT'),
        ])->build($this->admin(), 'MASTER', [
            'class_id' => 'CLS-A', 'report_type' => 'mingguan', 'weekly_anchor' => '2026-09-23',
        ]);

        $this->assertSame(2, $report['student_count']);
        $this->assertSame(0, $report['summary']['ABSENT']['count']);
        $this->assertSame(0, $report['student_recap']->firstWhere('student_id', 'STU-C')['total']);
        $this->assertStringContainsString('tidak otomatis dianggap Alpa', $report['absence_note']);
    }

    public function test_teacher_class_options_are_deduplicated_and_server_scope_is_enforced(): void
    {
        $scope = $this->scope(['CLS-A'], ['CLS-A' => ['STU-A', 'STU-C']]);
        $service = $this->service([], $scope);

        $this->assertSame(['CLS-A'], $service->availableClasses($this->teacher(), 'TEACHER')->pluck('Class_ID')->all());

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Anda tidak memiliki akses ke kelas ini.');
        $service->build($this->teacher(), 'TEACHER', [
            'class_id' => 'CLS-B', 'report_type' => 'harian', 'daily_date' => '2026-09-21',
        ]);
    }

    public function test_student_selector_is_derived_from_the_selected_class_only(): void
    {
        $service = $this->service([]);
        $classes = $service->availableClasses($this->admin(), 'ADMINISTRATOR');
        $students = $service->availableStudentsByClass($this->admin(), 'ADMINISTRATOR', $classes);

        $this->assertSame(['STU-A', 'STU-C'], array_column($students['CLS-A'], 'id'));
        $this->assertSame(['Andi', 'Citra'], array_column($students['CLS-A'], 'name'));
        $this->assertSame(['NIS-1', 'NIS-3'], array_column($students['CLS-A'], 'number'));
        $this->assertSame(['STU-B'], array_column($students['CLS-B'], 'id'));
    }

    public function test_teacher_report_reuses_bulk_scope_and_repository_snapshots_without_n_plus_one_reads(): void
    {
        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')->once()->andReturn(collect([
            $this->attendance('ATT-A', 'STU-A', 'CLS-A', '2026-10-02', 'PRESENT'),
        ]));
        $classRepo = Mockery::mock(ClassRepositoryInterface::class);
        $classRepo->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Class_ID' => 'CLS-A', 'Class_Name' => 'Kelas A', 'Is_Active' => 'TRUE'],
        ]));
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldNotReceive('fetchAll');
        $enrollmentRepo = Mockery::mock(ClassEnrollmentRepositoryInterface::class);
        $enrollmentRepo->shouldNotReceive('fetchAll');
        $scheduleRepo = Mockery::mock(ScheduleRepositoryInterface::class);
        $scheduleRepo->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Schedule_ID' => 'SCH-A', 'Class_ID' => 'CLS-A', 'Teacher_ID' => 'T-1'],
        ]));
        $scope = $this->scope(['CLS-A'], ['CLS-A' => ['STU-A', 'STU-C']]);
        $scopeResolver = Mockery::mock(TeacherScopeResolver::class);
        $scopeResolver->shouldReceive('resolveForUser')->times(3)->andReturn($scope);
        $scopeResolver->shouldReceive('classAllowed')->once()->with($scope, 'CLS-A')->andReturnTrue();

        $service = new AttendanceReportService(
            $attendanceRepo,
            $classRepo,
            $studentRepo,
            $enrollmentRepo,
            $scheduleRepo,
            $scopeResolver,
            new AttendanceLegacyClassifier
        );
        $classes = $service->availableClasses($this->teacher(), 'TEACHER');
        $service->availableStudentsByClass($this->teacher(), 'TEACHER', $classes);
        $report = $service->build($this->teacher(), 'TEACHER', [
            'class_id' => 'CLS-A', 'student_id' => 'STU-A', 'report_type' => 'harian', 'daily_date' => '2026-10-02',
        ]);

        $this->assertSame(['ATT-A'], $report['rows']->pluck('attendance_id')->all());
    }

    public function test_teacher_can_select_each_student_in_authorized_class_without_cross_student_leakage(): void
    {
        $service = $this->service([
            $this->attendance('ATT-A', 'STU-A', 'CLS-A', '2026-10-02', 'PRESENT'),
            $this->attendance('ATT-C', 'STU-C', 'CLS-A', '2026-10-02', 'LATE'),
            $this->attendance('ATT-B', 'STU-B', 'CLS-B', '2026-10-02', 'SICK'),
        ]);

        $all = $service->build($this->teacher(), 'TEACHER', [
            'class_id' => 'CLS-A', 'report_type' => 'harian', 'daily_date' => '2026-10-02',
        ]);
        $andi = $service->build($this->teacher(), 'TEACHER', [
            'class_id' => 'CLS-A', 'student_id' => 'STU-A', 'report_type' => 'harian', 'daily_date' => '2026-10-02',
        ]);
        $citra = $service->build($this->teacher(), 'TEACHER', [
            'class_id' => 'CLS-A', 'student_id' => 'STU-C', 'report_type' => 'harian', 'daily_date' => '2026-10-02',
        ]);

        $this->assertFalse($all['is_individual']);
        $this->assertSame(['ATT-A', 'ATT-C'], $all['rows']->pluck('attendance_id')->all());
        $this->assertSame(['ATT-A'], $andi['rows']->pluck('attendance_id')->all());
        $this->assertSame('Andi', $andi['student']['name']);
        $this->assertSame(['ATT-C'], $citra['rows']->pluck('attendance_id')->all());
        $this->assertSame('Citra', $citra['student']['name']);
    }

    public function test_teacher_forged_student_from_another_class_is_forbidden(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Siswa berada di luar kelas yang dipilih.');

        $this->service([])->build($this->teacher(), 'TEACHER', [
            'class_id' => 'CLS-A', 'student_id' => 'STU-B', 'report_type' => 'harian', 'daily_date' => '2026-10-02',
        ]);
    }

    public function test_teacher_with_multiple_and_duplicate_assignments_sees_each_class_once(): void
    {
        $scope = $this->scope(
            ['CLS-A', 'CLS-A', 'CLS-B'],
            ['CLS-A' => ['STU-A', 'STU-C'], 'CLS-B' => ['STU-B']]
        );

        $this->assertSame(
            ['CLS-A', 'CLS-B'],
            $this->service([], $scope)->availableClasses($this->teacher(), 'TEACHER')->pluck('Class_ID')->all()
        );
    }

    public function test_unknown_class_fails_closed(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Kelas tidak ditemukan atau tidak aktif.');

        $this->service([])->build($this->admin(), 'ADMINISTRATOR', [
            'class_id' => 'CLS-UNKNOWN', 'report_type' => 'harian', 'daily_date' => '2026-09-21',
        ]);
    }

    public function test_administrator_and_master_receive_all_active_classes(): void
    {
        $service = $this->service([]);

        $this->assertSame(['CLS-A', 'CLS-B'], $service->availableClasses($this->admin(), 'ADMINISTRATOR')->pluck('Class_ID')->all());
        $this->assertSame(['CLS-A', 'CLS-B'], $service->availableClasses($this->admin(), 'MASTER')->pluck('Class_ID')->all());
    }

    public function test_weekly_boundary_is_monday_through_sunday_across_month(): void
    {
        $period = $this->service([])->resolvePeriod([
            'report_type' => 'mingguan', 'weekly_anchor' => '2026-10-01',
        ]);

        $this->assertSame('2026-09-28', $period['start_date']);
        $this->assertSame('2026-10-04', $period['end_date']);
    }

    #[DataProvider('monthlyPeriodProvider')]
    public function test_monthly_period_uses_real_calendar_end(int $year, int $month, string $end): void
    {
        $period = $this->service([])->resolvePeriod([
            'report_type' => 'bulanan', 'year' => $year, 'month' => $month,
        ]);

        $this->assertSame(sprintf('%04d-%02d-01', $year, $month), $period['start_date']);
        $this->assertSame($end, $period['end_date']);
    }

    public static function monthlyPeriodProvider(): array
    {
        return [
            '28 days' => [2026, 2, '2026-02-28'],
            'leap year' => [2028, 2, '2028-02-29'],
            '30 days' => [2026, 9, '2026-09-30'],
            '31 days' => [2026, 10, '2026-10-31'],
        ];
    }

    public function test_custom_same_day_and_multiple_days_share_detail_and_recap_dataset(): void
    {
        $service = $this->service([
            $this->attendance('ATT-1', 'STU-A', 'CLS-A', '2026-09-21', 'PRESENT'),
            $this->attendance('ATT-2', 'STU-A', 'CLS-A', '2026-09-22', 'PERMITTED'),
            $this->attendance('ATT-3', 'STU-C', 'CLS-A', '2026-09-23', 'SICK'),
        ]);
        $sameDay = $service->build($this->admin(), 'ADMINISTRATOR', [
            'class_id' => 'CLS-A', 'report_type' => 'rentang', 'date_start' => '2026-09-21', 'date_end' => '2026-09-21',
        ]);
        $multiple = $service->build($this->admin(), 'ADMINISTRATOR', [
            'class_id' => 'CLS-A', 'report_type' => 'rentang', 'date_start' => '2026-09-21', 'date_end' => '2026-09-23',
        ]);

        $this->assertSame(1, $sameDay['total_records']);
        $this->assertSame(3, $multiple['total_records']);
        $this->assertSame(
            $multiple['total_records'],
            collect($multiple['student_recap'])->sum(fn ($student) => $student['total'])
        );
        $this->assertSame($multiple['total_records'], collect($multiple['summary'])->sum('count'));
    }

    #[DataProvider('individualPeriodProvider')]
    public function test_individual_periods_filter_the_exact_canonical_dataset(array $filters, array $expectedIds): void
    {
        $report = $this->service([
            $this->attendance('SEP-27', 'STU-A', 'CLS-A', '2026-09-27', 'PRESENT'),
            $this->attendance('SEP-28', 'STU-A', 'CLS-A', '2026-09-28', 'LATE'),
            $this->attendance('OCT-02', 'STU-A', 'CLS-A', '2026-10-02', 'PERMITTED'),
            $this->attendance('OCT-04', 'STU-A', 'CLS-A', '2026-10-04', 'SICK'),
            $this->attendance('OCT-05', 'STU-A', 'CLS-A', '2026-10-05', 'PRESENT'),
            $this->attendance('OTHER', 'STU-C', 'CLS-A', '2026-10-02', 'PRESENT'),
        ])->build($this->admin(), 'MASTER', array_merge([
            'class_id' => 'CLS-A', 'student_id' => 'STU-A',
        ], $filters));

        $this->assertTrue($report['is_individual']);
        $this->assertSame($expectedIds, $report['rows']->pluck('attendance_id')->all());
        $this->assertNotContains('OTHER', $report['rows']->pluck('attendance_id')->all());
    }

    public static function individualPeriodProvider(): array
    {
        return [
            'daily exact date' => [['report_type' => 'harian', 'daily_date' => '2026-10-02'], ['OCT-02']],
            'weekly Monday-Sunday' => [['report_type' => 'mingguan', 'weekly_anchor' => '2026-10-01'], ['SEP-28', 'OCT-02', 'OCT-04']],
            'monthly calendar' => [['report_type' => 'bulanan', 'month' => 10, 'year' => 2026], ['OCT-02', 'OCT-04', 'OCT-05']],
            'custom inclusive' => [['report_type' => 'rentang', 'date_start' => '2026-09-28', 'date_end' => '2026-10-02'], ['SEP-28', 'OCT-02']],
        ];
    }

    public function test_individual_preview_and_pdf_share_one_dataset_and_show_historical_late(): void
    {
        $report = $this->service([
            $this->attendance('ATT-LATE', 'STU-A', 'CLS-A', '2026-10-02', 'LATE'),
            $this->attendance('ATT-OTHER', 'STU-C', 'CLS-A', '2026-10-02', 'PRESENT'),
        ])->build($this->admin(), 'ADMINISTRATOR', [
            'class_id' => 'CLS-A', 'student_id' => 'STU-A', 'report_type' => 'bulanan', 'month' => 10, 'year' => 2026,
        ]);

        $preview = view('academic.attendance-reports.index', [
            'classes' => collect(),
            'studentsByClass' => [],
            'report' => $report,
            'defaults' => [
                'report_type' => 'bulanan', 'daily_date' => '2026-10-02', 'weekly_anchor' => '2026-10-02',
                'month' => 10, 'year' => 2026, 'date_start' => '2026-10-01', 'date_end' => '2026-10-31',
                'student_id' => 'STU-A',
            ],
            'userRole' => 'ADMINISTRATOR',
        ])->render();
        $pdf = view('pdf.attendance_report', compact('report'))->render();

        foreach ([$preview, $pdf] as $html) {
            $this->assertStringContainsString('Andi', $html);
            $this->assertStringContainsString('NIS-1', $html);
            $this->assertStringContainsString('Terlambat', $html);
            $this->assertStringNotContainsString('Citra', $html);
            $this->assertStringNotContainsString('ATT-OTHER', $html);
        }
        $this->assertSame(0, $report['summary']['ABSENT']['count']);
    }

    public function test_pdf_view_contains_identity_summary_recap_detail_and_generates_bytes(): void
    {
        $report = $this->service([
            $this->attendance('ATT-1', 'STU-A', 'CLS-A', '2026-09-21', 'PRESENT'),
        ])->build($this->admin(), 'ADMINISTRATOR', [
            'class_id' => 'CLS-A', 'report_type' => 'bulanan', 'month' => 9, 'year' => 2026,
        ]);

        $html = view('pdf.attendance_report', compact('report'))->render();
        $this->assertStringContainsString(config('branding.application.short_name'), $html);
        $this->assertStringContainsString('Kelas A', $html);
        $this->assertStringContainsString('September 2026', $html);
        $this->assertStringContainsString('Rekap Per Siswa', $html);
        $this->assertStringContainsString('Detail Absensi', $html);
        $this->assertStringContainsString('Andi', $html);
        $this->assertStringContainsString('Hadir', $html);

        $bytes = Pdf::loadView('pdf.attendance_report', compact('report'))->setPaper('A4', 'landscape')->output();
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertGreaterThan(5000, strlen($bytes));
    }

    public function test_empty_period_has_explicit_preview_and_pdf_message(): void
    {
        $report = $this->service([])->build($this->admin(), 'ADMINISTRATOR', [
            'class_id' => 'CLS-A', 'report_type' => 'harian', 'daily_date' => '2026-09-21',
        ]);

        $preview = view('academic.attendance-reports.index', [
            'classes' => collect(),
            'report' => $report,
            'defaults' => [
                'report_type' => 'harian', 'daily_date' => '2026-09-21', 'weekly_anchor' => '2026-09-21',
                'month' => 9, 'year' => 2026, 'date_start' => '2026-09-21', 'date_end' => '2026-09-21',
            ],
            'userRole' => 'TEACHER',
        ])->render();
        $pdf = view('pdf.attendance_report', compact('report'))->render();

        $message = 'Tidak ada data absensi pada periode yang dipilih.';
        $this->assertStringContainsString($message, $preview);
        $this->assertStringContainsString($message, $pdf);
    }

    #[DataProvider('mobileViewportProvider')]
    public function test_report_page_has_mobile_safe_contract_at_required_widths(int $width): void
    {
        $view = file_get_contents(resource_path('views/academic/attendance-reports/index.blade.php'));

        $this->assertGreaterThanOrEqual(375, $width);
        $this->assertStringContainsString('grid-cols-1', $view);
        $this->assertStringContainsString('min-h-11', $view);
        $this->assertStringContainsString('overflow-x-auto', $view);
        $this->assertStringContainsString('pb-24 md:pb-8', $view);
    }

    public static function mobileViewportProvider(): array
    {
        return ['375px' => [375], '390px' => [390], '430px' => [430], '768px' => [768], '1024px' => [1024]];
    }

    public function test_report_page_has_bounded_desktop_layout(): void
    {
        $view = file_get_contents(resource_path('views/academic/attendance-reports/index.blade.php'));

        $this->assertStringContainsString('lg:grid-cols-3', $view);
        $this->assertStringContainsString('max-w-2xl', $view);
        $this->assertStringContainsString('xl:grid-cols-6', $view);
    }

    public function test_report_routes_require_auth_and_teacher_or_administrator_role(): void
    {
        foreach (['attendance.reports.index', 'attendance.reports.pdf'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $middleware = collect($route->gatherMiddleware());
            $this->assertTrue($middleware->contains('auth'));
            $this->assertTrue($middleware->contains(fn ($item) => str_contains($item, 'role:TEACHER,ADMINISTRATOR,MASTER')));
        }
    }

    #[DataProvider('deniedRoleProvider')]
    public function test_student_and_unrelated_roles_are_denied_by_actual_route(string $roleName): void
    {
        $roleService = Mockery::mock(RoleService::class);
        $roleService->shouldReceive('getRoleById')->twice()->with($roleName)->andReturn([
            'Role_ID' => $roleName, 'Role_Name' => $roleName, 'Is_Active' => 'TRUE',
        ]);
        $this->app->instance(RoleService::class, $roleService);
        $this->actingAs(new GenericUser(['id' => 'USR-X', 'User_ID' => 'USR-X', 'Role_ID' => $roleName]));

        $this->get(route('attendance.reports.index'))->assertForbidden();
    }

    public static function deniedRoleProvider(): array
    {
        return [['STUDENT'], ['ACADEMIC'], ['HR']];
    }

    public function test_controller_rejects_invalid_custom_range_and_month_year(): void
    {
        $reportService = Mockery::mock(AttendanceReportService::class);
        $roleService = Mockery::mock(RoleService::class);
        $controller = new AttendanceReportController($reportService, $roleService);

        foreach ([
            ['class_id' => 'CLS-A', 'report_type' => 'rentang', 'date_start' => '2026-09-22', 'date_end' => '2026-09-21'],
            ['class_id' => 'CLS-A', 'report_type' => 'bulanan', 'month' => 13, 'year' => 1999],
        ] as $input) {
            $request = Request::create('/attendance/reports/pdf', 'GET', $input);
            $request->setUserResolver(fn () => (object) ['Role_ID' => 'ADMINISTRATOR']);

            try {
                $controller->pdf($request);
                $this->fail('ValidationException was not thrown.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_pdf_response_has_pdf_content_type_and_safe_period_filename(): void
    {
        $service = $this->service([
            $this->attendance('ATT-1', 'STU-A', 'CLS-A', '2026-09-21', 'PRESENT'),
        ]);
        $roleService = Mockery::mock(RoleService::class);
        $roleService->shouldReceive('getRoleById')->once()->with('ADMINISTRATOR')->andReturn([
            'Role_ID' => 'ADMINISTRATOR', 'Role_Name' => 'ADMINISTRATOR', 'Is_Active' => 'TRUE',
        ]);
        $controller = new AttendanceReportController($service, $roleService);
        $request = Request::create('/attendance/reports/pdf', 'GET', [
            'class_id' => 'CLS-A',
            'report_type' => 'mingguan',
            'weekly_anchor' => '2026-09-23',
        ]);
        $request->setUserResolver(fn () => (object) [
            'User_ID' => 'USR-ADMIN', 'Role_ID' => 'ADMINISTRATOR', 'Full_Name' => 'Administrator',
        ]);

        $response = $controller->pdf($request);

        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString(
            'absensi-mingguan-kelas-a-21-09-2026-27-09-2026.pdf',
            (string) $response->headers->get('content-disposition')
        );
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_pdf_endpoint_independently_rejects_forged_cross_class_student(): void
    {
        $roleService = Mockery::mock(RoleService::class);
        $roleService->shouldReceive('getRoleById')->once()->with('TEACHER')->andReturn([
            'Role_ID' => 'TEACHER', 'Role_Name' => 'TEACHER', 'Is_Active' => 'TRUE',
        ]);
        $controller = new AttendanceReportController($this->service([]), $roleService);
        $request = Request::create('/attendance/reports/pdf', 'GET', [
            'class_id' => 'CLS-A',
            'student_id' => 'STU-B',
            'report_type' => 'harian',
            'daily_date' => '2026-10-02',
        ]);
        $request->setUserResolver(fn () => (object) [
            'User_ID' => 'USR-T1', 'Role_ID' => 'TEACHER', 'Full_Name' => 'Guru Satu',
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Siswa berada di luar kelas yang dipilih.');
        $controller->pdf($request);
    }

    private function service(array $attendances, ?array $scope = null): AttendanceReportService
    {
        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect($attendances));
        $classRepo = Mockery::mock(ClassRepositoryInterface::class);
        $classRepo->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect([
            ['Class_ID' => 'CLS-A', 'Class_Name' => 'Kelas A', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-B', 'Class_Name' => 'Kelas B', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-OFF', 'Class_Name' => 'Kelas Nonaktif', 'Is_Active' => 'FALSE'],
        ]));
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect([
            ['Student_ID' => 'STU-A', 'Student_Number' => 'NIS-1', 'Full_Name' => 'Andi', 'Class_ID' => 'CLS-A', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STU-C', 'Student_Number' => 'NIS-3', 'Full_Name' => 'Citra', 'Class_ID' => 'CLS-A', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STU-B', 'Student_Number' => 'NIS-2', 'Full_Name' => 'Budi', 'Class_ID' => 'CLS-B', 'Is_Active' => 'TRUE'],
        ]));
        $enrollmentRepo = Mockery::mock(ClassEnrollmentRepositoryInterface::class);
        $enrollmentRepo->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect([
            ['Enrollment_ID' => 'ENR-1', 'Student_ID' => 'STU-A', 'Class_ID' => 'CLS-A', 'Is_Active' => 'TRUE'],
            ['Enrollment_ID' => 'ENR-2', 'Student_ID' => 'STU-C', 'Class_ID' => 'CLS-A', 'Is_Active' => 'TRUE'],
            ['Enrollment_ID' => 'ENR-3', 'Student_ID' => 'STU-B', 'Class_ID' => 'CLS-B', 'Is_Active' => 'TRUE'],
        ]));
        $scheduleRepo = Mockery::mock(ScheduleRepositoryInterface::class);
        $scheduleRepo->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect([
            ['Schedule_ID' => 'SCH-A', 'Class_ID' => 'CLS-A', 'Teacher_ID' => 'T-1'],
            ['Schedule_ID' => 'SCH-B', 'Class_ID' => 'CLS-B', 'Teacher_ID' => 'T-2'],
        ]));
        $scopeResolver = Mockery::mock(TeacherScopeResolver::class);
        $scope ??= $this->scope(['CLS-A'], ['CLS-A' => ['STU-A', 'STU-C']]);
        $scopeResolver->shouldReceive('resolveForUser')->zeroOrMoreTimes()->andReturn($scope);
        $scopeResolver->shouldReceive('classAllowed')->zeroOrMoreTimes()->andReturnUsing(
            fn ($givenScope, $classId) => in_array($classId, $givenScope['class_ids'] ?? [], true)
        );

        return new AttendanceReportService(
            $attendanceRepo,
            $classRepo,
            $studentRepo,
            $enrollmentRepo,
            $scheduleRepo,
            $scopeResolver,
            new AttendanceLegacyClassifier
        );
    }

    private function attendance(string $id, string $student, string $class, string $date, string $status): array
    {
        return [
            'Attendance_ID' => $id,
            'Student_ID' => $student,
            'Class_ID' => $class,
            'Attendance_Type' => 'CLASS_QR',
            'Attendance_Date' => $date,
            'Check_In_Time' => '07:15:00',
            'Check_Out_Time' => '09:00:00',
            'Status' => $status,
            'Notes' => 'Data canonical',
            'Is_Active' => 'TRUE',
        ];
    }

    private function scope(array $classIds, array $studentsByClass): array
    {
        $students = collect([
            ['Student_ID' => 'STU-A', 'Student_Number' => 'NIS-1', 'Full_Name' => 'Andi', 'Class_ID' => 'CLS-A', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STU-C', 'Student_Number' => 'NIS-3', 'Full_Name' => 'Citra', 'Class_ID' => 'CLS-A', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STU-B', 'Student_Number' => 'NIS-2', 'Full_Name' => 'Budi', 'Class_ID' => 'CLS-B', 'Is_Active' => 'TRUE'],
        ])->whereIn('Student_ID', array_values(array_unique(array_merge(...array_values($studentsByClass ?: [[]])))))->values();

        return ['class_ids' => $classIds, 'students_by_class' => $studentsByClass, 'students' => $students];
    }

    private function teacher(): object
    {
        return (object) ['User_ID' => 'USR-T1', 'Full_Name' => 'Guru Satu'];
    }

    private function admin(): object
    {
        return (object) ['User_ID' => 'USR-ADMIN', 'Full_Name' => 'Administrator'];
    }
}
