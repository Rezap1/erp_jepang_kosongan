<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\PermanentQrRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Academic\StudentQRAttendanceService;
use App\Services\Core\ActivityLogService;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\PermanentQrService;
use App\Services\Core\SystemSettingService;
use App\Services\HR\QRAttendanceService;
use Carbon\Carbon;
use Exception;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class AttendanceQrFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->configureEmployeeSettings(60);
    }

    public function test_permanent_qr_respects_activation_window(): void
    {
        $service = new PermanentQrService(
            Mockery::mock(PermanentQrRepositoryInterface::class),
            Mockery::mock(ActivityLogService::class)
        );

        $qr = [
            'STATUS' => 'ACTIVE',
            'ACTIVE_FROM' => '2026-08-23 08:00:00',
            'ACTIVE_UNTIL' => '2026-08-23 17:00:00',
        ];

        Carbon::setTestNow('2026-08-23 07:59:59');
        $this->assertSame('UPCOMING', $service->getAvailabilityStatus($qr)['state']);

        Carbon::setTestNow('2026-08-23 08:00:00');
        $this->assertTrue($service->isQrCurrentlyUsable($qr));

        Carbon::setTestNow('2026-08-23 08:30:00');
        $this->assertTrue($service->isQrCurrentlyUsable($qr));

        Carbon::setTestNow('2026-08-23 17:00:00');
        $this->assertTrue($service->isQrCurrentlyUsable($qr));

        Carbon::setTestNow('2026-08-23 17:00:01');
        $this->assertSame('EXPIRED', $service->getAvailabilityStatus($qr)['state']);
    }

    public function test_employee_dynamic_qr_token_only_generated_inside_session_window(): void
    {
        Cache::flush();

        $service = new QRAttendanceService(
            Mockery::mock(AttendanceRepositoryInterface::class),
            Mockery::mock(EmployeeRepositoryInterface::class),
            Mockery::mock(EnterpriseEventService::class)
        );

        $session = [
            'Session_ID' => 'QRS-TEST',
            'Title' => 'Presensi Pegawai',
            'Date' => '2026-08-23',
            'Start_Time' => '08:00',
            'End_Time' => '17:00',
            'Grace_Period' => 30,
            'Status' => 'ACTIVE',
            'Created_At' => '2026-08-23 07:00:00',
        ];

        Cache::forever('qr_session_QRS-TEST', $session);

        Carbon::setTestNow('2026-08-23 07:30:00');
        $this->expectException(Exception::class);
        $service->generateDynamicToken('QRS-TEST');
    }

    public function test_employee_dynamic_qr_token_is_generated_inside_session_window(): void
    {
        Cache::flush();
        $this->configureEmployeeSettings(25);

        $service = new QRAttendanceService(
            Mockery::mock(AttendanceRepositoryInterface::class),
            Mockery::mock(EmployeeRepositoryInterface::class),
            Mockery::mock(EnterpriseEventService::class)
        );

        $session = [
            'Session_ID' => 'QRS-TEST',
            'Title' => 'Presensi Pegawai',
            'Date' => '2026-08-23',
            'Start_Time' => '08:00',
            'End_Time' => '17:00',
            'Grace_Period' => 30,
            'Status' => 'ACTIVE',
            'Created_At' => '2026-08-23 07:00:00',
        ];

        Cache::forever('qr_session_QRS-TEST', $session);

        Carbon::setTestNow('2026-08-23 08:30:00');
        $token = $service->generateDynamicToken('QRS-TEST');

        $this->assertArrayHasKey('token', $token);
        $this->assertSame(25, $token['expires_in']);
        $this->assertSame($session, $token['session']);

        $payload = json_decode(base64_decode($token['token']), true);
        $this->assertSame(25, $payload['expires_at'] - Carbon::now()->timestamp);
    }

    public function test_employee_qr_session_defaults_to_thirty_minute_grace_period(): void
    {
        Cache::flush();

        $enterpriseEvent = Mockery::mock(EnterpriseEventService::class);
        $enterpriseEvent->shouldReceive('dispatch')->once();

        $service = new QRAttendanceService(
            Mockery::mock(AttendanceRepositoryInterface::class),
            Mockery::mock(EmployeeRepositoryInterface::class),
            $enterpriseEvent
        );

        $this->actingAs(new GenericUser([
            'id' => 'USR-HR',
            'User_ID' => 'USR-HR',
            'Full_Name' => 'HR Auth User',
        ]));

        Carbon::setTestNow('2026-08-23 08:00:00');
        $session = $service->createSession([
            'Title' => 'Presensi Pegawai',
            'Start_Time' => '08:00',
            'End_Time' => '17:00',
            'Grace_Period' => 120,
        ]);

        $this->assertSame(30, $session['Grace_Period']);
        $this->assertSame('HR Auth User', $session['Created_By']);
    }

    public function test_employee_qr_duplicate_scan_is_denied(): void
    {
        Cache::flush();

        $this->disableEmployeeGeofence();

        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')->once()->andReturn([
            [
                'Attendance_ID' => 'ATT-EXISTING',
                'Employee_ID' => 'EMP-1',
                'Session_ID' => 'QRS-TEST',
                'Check_In_Time' => '08:10:00',
                'Is_Active' => 'TRUE',
            ],
        ]);
        $attendanceRepo->shouldNotReceive('create');
        $attendanceRepo->shouldNotReceive('update');

        $employeeRepo = Mockery::mock(EmployeeRepositoryInterface::class);
        $employeeRepo->shouldReceive('fetchAll')->once()->andReturn([
            [
                'Employee_ID' => 'EMP-1',
                'User_ID' => 'USR-1',
                'Full_Name' => 'Pegawai Test',
                'Is_Active' => 'TRUE',
            ],
        ]);

        $service = new QRAttendanceService(
            $attendanceRepo,
            $employeeRepo,
            Mockery::mock(EnterpriseEventService::class)
        );

        $session = [
            'Session_ID' => 'QRS-TEST',
            'Title' => 'Presensi Pegawai',
            'Date' => '2026-08-23',
            'Start_Time' => '08:00',
            'End_Time' => '17:00',
            'Grace_Period' => 30,
            'Status' => 'ACTIVE',
            'Created_At' => '2026-08-23 07:00:00',
        ];

        Cache::forever('qr_session_QRS-TEST', $session);

        Carbon::setTestNow('2026-08-23 08:20:00');
        $token = $service->generateDynamicToken('QRS-TEST');

        $this->actingAs(new GenericUser([
            'id' => 'USR-1',
            'User_ID' => 'USR-1',
        ]));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('sudah melakukan presensi');

        $service->processScan($token['token'], 'Unit Test Device', -6.81234, 107.19451);
    }

    public function test_employee_qr_same_token_allows_different_employees(): void
    {
        Cache::flush();
        $this->disableEmployeeGeofence();

        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')
            ->twice()
            ->andReturn(
                [],
                [[
                    'Attendance_ID' => 'ATT-A',
                    'Employee_ID' => 'EMP-A',
                    'Session_ID' => 'QRS-TEST',
                    'Is_Active' => 'TRUE',
                ]]
            );
        $attendanceRepo->shouldReceive('create')->twice()->andReturn(true);

        $employeeRepo = Mockery::mock(EmployeeRepositoryInterface::class);
        $employeeRepo->shouldReceive('fetchAll')->twice()->andReturn([
            [
                'Employee_ID' => 'EMP-A',
                'User_ID' => 'USR-A',
                'Full_Name' => 'Employee A',
                'Is_Active' => 'TRUE',
            ],
            [
                'Employee_ID' => 'EMP-B',
                'User_ID' => 'USR-B',
                'Full_Name' => 'Employee B',
                'Is_Active' => 'TRUE',
            ],
        ]);

        $enterpriseEvent = Mockery::mock(EnterpriseEventService::class);
        $enterpriseEvent->shouldReceive('dispatch')->twice();

        $service = new QRAttendanceService($attendanceRepo, $employeeRepo, $enterpriseEvent);
        $this->putOpenEmployeeSession();

        Carbon::setTestNow('2026-08-23 08:10:00');
        $token = $service->generateDynamicToken('QRS-TEST');

        $this->actingAs(new GenericUser(['id' => 'USR-A', 'User_ID' => 'USR-A']));
        $first = $service->processScan($token['token'], 'Device A', -6.81234, 107.19451);

        $this->actingAs(new GenericUser(['id' => 'USR-B', 'User_ID' => 'USR-B']));
        $second = $service->processScan($token['token'], 'Device B', -6.81234, 107.19451);

        $this->assertSame('EMP-A', $first['employee']['id']);
        $this->assertSame('EMP-B', $second['employee']['id']);
        $this->assertSame('PRESENT', $first['status']);
        $this->assertSame('PRESENT', $second['status']);
    }

    public function test_employee_qr_time_classification_boundaries(): void
    {
        Cache::flush();
        $this->disableEmployeeGeofence();

        $created = [];
        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')->twice()->andReturn([], []);
        $attendanceRepo->shouldReceive('create')
            ->twice()
            ->with(Mockery::on(function ($record) use (&$created) {
                $created[] = $record;

                return true;
            }))
            ->andReturn(true);

        $employeeRepo = Mockery::mock(EmployeeRepositoryInterface::class);
        $employeeRepo->shouldReceive('fetchAll')->twice()->andReturn([
            ['Employee_ID' => 'EMP-A', 'User_ID' => 'USR-A', 'Full_Name' => 'Employee A', 'Is_Active' => 'TRUE'],
            ['Employee_ID' => 'EMP-B', 'User_ID' => 'USR-B', 'Full_Name' => 'Employee B', 'Is_Active' => 'TRUE'],
        ]);

        $enterpriseEvent = Mockery::mock(EnterpriseEventService::class);
        $enterpriseEvent->shouldReceive('dispatch')->twice();

        $service = new QRAttendanceService($attendanceRepo, $employeeRepo, $enterpriseEvent);
        $this->putOpenEmployeeSession();

        Carbon::setTestNow('2026-08-23 08:00:00');
        $token = $service->generateDynamicToken('QRS-TEST');

        Carbon::setTestNow('2026-08-23 08:30:00');
        $this->actingAs(new GenericUser(['id' => 'USR-A', 'User_ID' => 'USR-A']));
        $onTime = $service->processScan($token['token'], 'Device A', -6.81234, 107.19451);

        Carbon::setTestNow('2026-08-23 08:31:00');
        $this->actingAs(new GenericUser(['id' => 'USR-B', 'User_ID' => 'USR-B']));
        $late = $service->processScan($token['token'], 'Device B', -6.81234, 107.19451);

        $this->assertSame('PRESENT', $onTime['status']);
        $this->assertSame(0, $onTime['late_minutes']);
        $this->assertSame('LATE', $late['status']);
        $this->assertSame(31, $late['late_minutes']);
        $this->assertSame('PRESENT', $created[0]['Status']);
        $this->assertSame('LATE', $created[1]['Status']);
    }

    public function test_employee_qr_token_after_sixty_minutes_is_expired(): void
    {
        Cache::flush();

        $service = new QRAttendanceService(
            Mockery::mock(AttendanceRepositoryInterface::class),
            Mockery::mock(EmployeeRepositoryInterface::class),
            Mockery::mock(EnterpriseEventService::class)
        );
        $this->putOpenEmployeeSession();

        Carbon::setTestNow('2026-08-23 08:00:00');
        $token = $service->generateDynamicToken('QRS-TEST');

        Carbon::setTestNow('2026-08-23 09:01:00');
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('kadaluarsa');

        $service->processScan($token['token'], 'Expired Device');
    }

    public function test_employee_qr_scan_without_gps_is_denied(): void
    {
        Cache::flush();

        $service = new QRAttendanceService(
            Mockery::mock(AttendanceRepositoryInterface::class),
            Mockery::mock(EmployeeRepositoryInterface::class),
            Mockery::mock(EnterpriseEventService::class)
        );
        $this->putOpenEmployeeSession();

        Carbon::setTestNow('2026-08-23 08:10:00');
        $token = $service->generateDynamicToken('QRS-TEST');
        $this->actingAs(new GenericUser(['id' => 'USR-A', 'User_ID' => 'USR-A']));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('GPS wajib aktif');

        $service->processScan($token['token'], 'Device A');
    }

    public function test_student_qr_duplicate_scan_is_denied(): void
    {
        Cache::flush();
        $this->putOpenStudentSession();

        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')->once()->andReturn([
            [
                'Attendance_ID' => 'ATT-STU-EXISTING',
                'Student_ID' => 'STU-A',
                'Class_ID' => 'CLASS-1',
                'Attendance_Type' => 'CLASS_QR',
                'Attendance_Date' => '2026-08-23',
                'Check_In_Time' => '08:05:00',
                'Is_Active' => 'TRUE',
            ],
        ]);
        $attendanceRepo->shouldNotReceive('create');
        $attendanceRepo->shouldNotReceive('update');

        $service = new StudentQRAttendanceService(
            $attendanceRepo,
            $this->studentRepositoryForQr(),
            $this->studentQrSettings(),
            Mockery::mock(EnterpriseEventService::class)
        );

        Carbon::setTestNow('2026-08-23 08:10:00');
        $this->actingAs(new GenericUser(['id' => 'USR-STU', 'User_ID' => 'USR-STU', 'Role' => 'STUDENT']));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('sudah melakukan presensi');

        $service->processStudentScan($this->studentToken(), -6.81234, 107.19451, 'Student Device');
    }

    public function test_student_qr_persists_class_identity_server_side_and_ignores_client_values(): void
    {
        Cache::flush();
        $this->putOpenStudentSession();

        $created = null;
        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')->once()->andReturn([]);
        $attendanceRepo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($record) use (&$created) {
                $created = $record;

                return true;
            }))
            ->andReturn(true);

        $enterpriseEvent = Mockery::mock(EnterpriseEventService::class);
        $enterpriseEvent->shouldReceive('dispatch')->once();

        $service = new StudentQRAttendanceService(
            $attendanceRepo,
            $this->studentRepositoryForQr(),
            $this->studentQrSettings(),
            $enterpriseEvent
        );

        Carbon::setTestNow('2026-08-23 08:10:00');
        $this->actingAs(new GenericUser(['id' => 'USR-STU', 'User_ID' => 'USR-STU', 'Role' => 'STUDENT']));
        request()->merge([
            'Student_ID' => 'ATTACKER-STUDENT',
            'Class_ID' => 'ATTACKER-CLASS',
            'Attendance_Type' => 'SCHEDULE',
        ]);

        $service->processStudentScan($this->studentToken(), -6.81234, 107.19451, 'Student Device');

        $this->assertSame('USR-STU', $created['User_ID']);
        $this->assertSame('STU-A', $created['Student_ID']);
        $this->assertSame('CLASS-1', $created['Class_ID']);
        $this->assertSame('', $created['Schedule_ID']);
        $this->assertSame('CLASS_QR', $created['Attendance_Type']);
    }

    public function test_student_qr_allows_distinct_students_and_keeps_classes_separate(): void
    {
        Cache::flush();
        $this->putOpenStudentSession();

        $students = [
            [
                'Student_ID' => 'STU-A', 'User_ID' => 'USR-STU-A', 'Full_Name' => 'Student A',
                'Batch_ID' => 'BATCH-1', 'Class_ID' => 'CLASS-1', 'Enrollment_Status' => 'Aktif',
                'Graduation_Status' => '', 'Is_Active' => 'TRUE',
            ],
            [
                'Student_ID' => 'STU-B', 'User_ID' => 'USR-STU-B', 'Full_Name' => 'Student B',
                'Batch_ID' => 'BATCH-1', 'Class_ID' => 'CLASS-1', 'Enrollment_Status' => 'Aktif',
                'Graduation_Status' => '', 'Is_Active' => 'TRUE',
            ],
            [
                'Student_ID' => 'STU-C', 'User_ID' => 'USR-STU-C', 'Full_Name' => 'Student C',
                'Batch_ID' => 'BATCH-1', 'Class_ID' => 'CLASS-2', 'Enrollment_Status' => 'Aktif',
                'Graduation_Status' => '', 'Is_Active' => 'TRUE',
            ],
        ];

        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->times(3)->andReturn(
            [$students[0]], [$students[1]], [$students[2]]
        );

        $created = [];
        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')->times(3)->andReturn([], [], []);
        $attendanceRepo->shouldReceive('create')
            ->times(3)
            ->with(Mockery::on(function ($record) use (&$created) {
                $created[] = $record;

                return true;
            }))
            ->andReturn(true);

        $enterpriseEvent = Mockery::mock(EnterpriseEventService::class);
        $enterpriseEvent->shouldReceive('dispatch')->times(3);

        $service = new StudentQRAttendanceService(
            $attendanceRepo,
            $studentRepo,
            $this->studentQrSettings(),
            $enterpriseEvent
        );

        Carbon::setTestNow('2026-08-23 08:10:00');
        foreach ($students as $student) {
            $this->actingAs(new GenericUser([
                'id' => $student['User_ID'],
                'User_ID' => $student['User_ID'],
                'Role' => 'STUDENT',
            ]));
            $service->processStudentScan($this->studentToken(), -6.81234, 107.19451, 'Student Device');
        }

        $this->assertCount(3, $created);
        $this->assertSame(['STU-A', 'STU-B', 'STU-C'], array_column($created, 'Student_ID'));
        $this->assertSame(['CLASS-1', 'CLASS-1', 'CLASS-2'], array_column($created, 'Class_ID'));
        $this->assertSame(['', '', ''], array_column($created, 'Schedule_ID'));
        $this->assertSame(['CLASS_QR', 'CLASS_QR', 'CLASS_QR'], array_column($created, 'Attendance_Type'));
    }

    public function test_permanent_student_qr_url_is_universal_across_active_classes_and_ignores_qr_scope(): void
    {
        Cache::flush();
        $identifier = 'WMS-ATT-STU-EXIST01';
        $this->bindPermanentQrService($identifier, 'STUDENT', 4, [
            'Class_ID' => 'LEGACY-CLASS-A',
            'Schedule_ID' => 'LEGACY-SCHEDULE-A',
            'Subject_ID' => 'LEGACY-SUBJECT-A',
            'Teacher_ID' => 'LEGACY-TEACHER-A',
        ]);

        $students = $this->studentsAcrossFourClasses();
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->times(4)->andReturn($students);

        $created = [];
        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')->times(4)->andReturn([], [], [], []);
        $attendanceRepo->shouldReceive('create')
            ->times(4)
            ->with(Mockery::on(function ($record) use (&$created) {
                $created[] = $record;

                return true;
            }))
            ->andReturn(true);

        $enterpriseEvent = Mockery::mock(EnterpriseEventService::class);
        $enterpriseEvent->shouldReceive('dispatch')->times(4);

        $service = new StudentQRAttendanceService(
            $attendanceRepo,
            $studentRepo,
            $this->studentQrSettings(),
            $enterpriseEvent
        );

        Carbon::setTestNow('2026-08-23 08:10:00');
        foreach ($students as $student) {
            $this->actingAs(new GenericUser([
                'id' => $student['User_ID'],
                'User_ID' => $student['User_ID'],
                'Role' => 'STUDENT',
            ]));

            $service->processStudentScan(
                $this->permanentQrUrl('student', $identifier),
                -6.81234,
                107.19451,
                'Student Device'
            );
        }

        $this->assertSame(['STU-A', 'STU-B', 'STU-C', 'STU-D'], array_column($created, 'Student_ID'));
        $this->assertSame(['CLASS-A', 'CLASS-B', 'CLASS-C', 'CLASS-D'], array_column($created, 'Class_ID'));
        $this->assertSame(['', '', '', ''], array_column($created, 'Schedule_ID'));
        $this->assertSame(['CLASS_QR', 'CLASS_QR', 'CLASS_QR', 'CLASS_QR'], array_column($created, 'Attendance_Type'));
    }

    public function test_permanent_student_qr_duplicate_scan_does_not_create_second_attendance(): void
    {
        Cache::flush();
        $identifier = 'WMS-ATT-STU-EXIST01';
        $this->bindPermanentQrService($identifier, 'STUDENT', 2);

        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->twice()->andReturn([$this->studentsAcrossFourClasses()[0]]);

        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')->twice()->andReturn(
            [],
            [[
                'Attendance_ID' => 'ATT-EXISTING',
                'Student_ID' => 'STU-A',
                'Class_ID' => 'CLASS-A',
                'Attendance_Type' => 'CLASS_QR',
                'Attendance_Date' => '2026-08-23',
                'Is_Active' => 'TRUE',
            ]]
        );
        $attendanceRepo->shouldReceive('create')->once()->andReturn(true);

        $enterpriseEvent = Mockery::mock(EnterpriseEventService::class);
        $enterpriseEvent->shouldReceive('dispatch')->once();

        $service = new StudentQRAttendanceService(
            $attendanceRepo,
            $studentRepo,
            $this->studentQrSettings(),
            $enterpriseEvent
        );

        Carbon::setTestNow('2026-08-23 08:10:00');
        $this->actingAs(new GenericUser(['id' => 'USR-A', 'User_ID' => 'USR-A', 'Role' => 'STUDENT']));
        $service->processStudentScan($this->permanentQrUrl('student', $identifier), -6.81234, 107.19451, 'Device A');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('sudah melakukan presensi');

        $service->processStudentScan($this->permanentQrUrl('student', $identifier), -6.81234, 107.19451, 'Device A');
    }

    public function test_student_using_employee_permanent_qr_is_denied_before_attendance_write(): void
    {
        Cache::flush();

        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldNotReceive('fetchAll');
        $attendanceRepo->shouldNotReceive('create');

        $service = new StudentQRAttendanceService(
            $attendanceRepo,
            $this->studentRepositoryForQr(),
            $this->studentQrSettings(),
            Mockery::mock(EnterpriseEventService::class)
        );

        Carbon::setTestNow('2026-08-23 08:10:00');
        $this->actingAs(new GenericUser(['id' => 'USR-STU', 'User_ID' => 'USR-STU', 'Role' => 'STUDENT']));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('khusus untuk Presensi Pegawai');

        $service->processStudentScan(
            $this->permanentQrUrl('employee', 'wms-att-emp-exist01'),
            -6.81234,
            107.19451,
            'Student Device'
        );
    }

    public function test_student_qr_without_student_mapping_is_denied_without_creating_attendance(): void
    {
        Cache::flush();
        $this->putOpenStudentSession();

        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldNotReceive('fetchAll');
        $attendanceRepo->shouldNotReceive('create');

        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->once()->andReturn([]);

        $service = new StudentQRAttendanceService(
            $attendanceRepo,
            $studentRepo,
            $this->studentQrSettings(),
            Mockery::mock(EnterpriseEventService::class)
        );

        Carbon::setTestNow('2026-08-23 08:10:00');
        $this->actingAs(new GenericUser(['id' => 'USR-UNMAPPED', 'User_ID' => 'USR-UNMAPPED', 'Role' => 'STUDENT']));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Profil siswa tidak ditemukan');

        $service->processStudentScan($this->studentToken(), -6.81234, 107.19451, 'Student Device');
    }

    public function test_student_qr_inside_window_is_present_without_late_minutes(): void
    {
        Cache::flush();
        $this->putOpenStudentSession();

        $created = null;
        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')->once()->andReturn([]);
        $attendanceRepo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($record) use (&$created) {
                $created = $record;

                return true;
            }))
            ->andReturn(true);

        $enterpriseEvent = Mockery::mock(EnterpriseEventService::class);
        $enterpriseEvent->shouldReceive('dispatch')->once();

        $service = new StudentQRAttendanceService(
            $attendanceRepo,
            $this->studentRepositoryForQr(),
            $this->studentQrSettings(),
            $enterpriseEvent
        );

        Carbon::setTestNow('2026-08-23 08:16:00');
        $this->actingAs(new GenericUser(['id' => 'USR-STU', 'User_ID' => 'USR-STU', 'Role' => 'STUDENT']));

        $result = $service->processStudentScan($this->studentToken(), -6.81234, 107.19451, 'Student Device');

        $this->assertSame('PRESENT', $result['status']);
        $this->assertSame(0, $result['late_minutes']);
        $this->assertSame(0, $created['Late_Minutes']);
    }

    public function test_student_qr_accepts_fixed_scale_coordinates_from_mysql_import(): void
    {
        Cache::flush();
        $this->putOpenStudentSession();

        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')->once()->andReturn([]);
        $attendanceRepo->shouldReceive('create')->once()->andReturn(true);

        $enterpriseEvent = Mockery::mock(EnterpriseEventService::class);
        $enterpriseEvent->shouldReceive('dispatch')->once();

        $service = new StudentQRAttendanceService(
            $attendanceRepo,
            $this->studentRepositoryForQr(),
            $this->studentQrSettings('-6812391', '107194458'),
            $enterpriseEvent
        );

        Carbon::setTestNow('2026-08-23 08:16:00');
        $this->actingAs(new GenericUser(['id' => 'USR-STU', 'User_ID' => 'USR-STU', 'Role' => 'STUDENT']));

        $result = $service->processStudentScan($this->studentToken(), -6.812391, 107.194458, 'Student Device');

        $this->assertSame('PRESENT', $result['status']);
        $this->assertSame(0, $result['late_minutes']);
        $this->assertSame(0.0, $result['distance_meters']);
    }

    public function test_student_qr_scan_outside_twenty_meter_geofence_is_denied(): void
    {
        Cache::flush();
        $this->putOpenStudentSession();

        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldNotReceive('fetchAll');
        $attendanceRepo->shouldNotReceive('create');

        $service = new StudentQRAttendanceService(
            $attendanceRepo,
            $this->studentRepositoryForQr(),
            $this->studentQrSettings(),
            Mockery::mock(EnterpriseEventService::class)
        );

        Carbon::setTestNow('2026-08-23 08:10:00');
        $this->actingAs(new GenericUser(['id' => 'USR-STU', 'User_ID' => 'USR-STU', 'Role' => 'STUDENT']));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('di luar area LPK');

        $service->processStudentScan($this->studentToken(), -6.80000, 107.19451, 'Student Device');
    }

    public function test_permanent_employee_qr_url_works_without_open_dynamic_session_for_multiple_employees(): void
    {
        Cache::flush();
        $this->disableEmployeeGeofence();
        $identifier = 'WMS-ATT-EMP-EXIST01';
        $this->bindPermanentQrService($identifier, 'EMPLOYEE', 2);

        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')->twice()->andReturn(
            [],
            [[
                'Attendance_ID' => 'ATT-EMP-A',
                'Employee_ID' => 'EMP-A',
                'Session_ID' => 'EMP-QRS-2026-08-23',
                'Is_Active' => 'TRUE',
            ]]
        );
        $attendanceRepo->shouldReceive('create')->twice()->andReturn(true);

        $employeeRepo = Mockery::mock(EmployeeRepositoryInterface::class);
        $employeeRepo->shouldReceive('fetchAll')->twice()->andReturn($this->employeeRowsForQr());

        $enterpriseEvent = Mockery::mock(EnterpriseEventService::class);
        $enterpriseEvent->shouldReceive('dispatch')->twice();

        $service = new QRAttendanceService($attendanceRepo, $employeeRepo, $enterpriseEvent);

        Carbon::setTestNow('2026-08-23 20:10:00');
        $this->actingAs(new GenericUser(['id' => 'USR-EMP-A', 'User_ID' => 'USR-EMP-A', 'Role' => 'HR']));
        $first = $service->processScan($this->permanentQrUrl('employee', $identifier), 'Device A', -6.81234, 107.19451);

        $this->actingAs(new GenericUser(['id' => 'usr-emp-b', 'User_ID' => 'usr-emp-b', 'Role' => 'TEACHER']));
        $second = $service->processScan($this->permanentQrUrl('employee', $identifier), 'Device B', -6.81234, 107.19451);

        $this->assertSame('EMP-A', $first['employee']['id']);
        $this->assertSame('EMP-B', $second['employee']['id']);
        $this->assertSame('LATE', $first['status']);
        $this->assertSame('LATE', $second['status']);
    }

    public function test_employee_qr_accepts_fixed_scale_coordinates_from_mysql_import(): void
    {
        Cache::flush();
        $this->configureEmployeeSettings(3600, '-6812391', '107194458');
        $identifier = 'WMS-ATT-EMP-IMPORT1';
        $this->bindPermanentQrService($identifier, 'EMPLOYEE', 1);

        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')->once()->andReturn([]);
        $attendanceRepo->shouldReceive('create')->once()->andReturn(true);

        $employeeRepo = Mockery::mock(EmployeeRepositoryInterface::class);
        $employeeRepo->shouldReceive('fetchAll')->once()->andReturn([$this->employeeRowsForQr()[0]]);

        $enterpriseEvent = Mockery::mock(EnterpriseEventService::class);
        $enterpriseEvent->shouldReceive('dispatch')->once();

        $service = new QRAttendanceService($attendanceRepo, $employeeRepo, $enterpriseEvent);

        Carbon::setTestNow('2026-08-23 20:10:00');
        $this->actingAs(new GenericUser(['id' => 'USR-EMP-A', 'User_ID' => 'USR-EMP-A', 'Role' => 'HR']));

        $result = $service->processScan(
            $this->permanentQrUrl('employee', $identifier),
            'Employee Device',
            -6.812391,
            107.194458
        );

        $this->assertSame('EMP-A', $result['employee']['id']);
        $this->assertSame(0.0, $result['distance_meters']);
    }

    public function test_permanent_employee_qr_duplicate_scan_does_not_create_second_attendance(): void
    {
        Cache::flush();
        $this->disableEmployeeGeofence();
        $identifier = 'WMS-ATT-EMP-EXIST01';
        $this->bindPermanentQrService($identifier, 'EMPLOYEE', 2);

        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')->twice()->andReturn(
            [],
            [[
                'Attendance_ID' => 'ATT-EMP-A',
                'Employee_ID' => 'EMP-A',
                'Session_ID' => 'EMP-QRS-2026-08-23',
                'Is_Active' => 'TRUE',
            ]]
        );
        $attendanceRepo->shouldReceive('create')->once()->andReturn(true);

        $employeeRepo = Mockery::mock(EmployeeRepositoryInterface::class);
        $employeeRepo->shouldReceive('fetchAll')->twice()->andReturn([$this->employeeRowsForQr()[0]]);

        $enterpriseEvent = Mockery::mock(EnterpriseEventService::class);
        $enterpriseEvent->shouldReceive('dispatch')->once();

        $service = new QRAttendanceService($attendanceRepo, $employeeRepo, $enterpriseEvent);

        Carbon::setTestNow('2026-08-23 20:10:00');
        $this->actingAs(new GenericUser(['id' => 'USR-EMP-A', 'User_ID' => 'USR-EMP-A', 'Role' => 'HR']));
        $service->processScan($this->permanentQrUrl('employee', $identifier), 'Device A', -6.81234, 107.19451);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('sudah melakukan presensi');

        $service->processScan($this->permanentQrUrl('employee', $identifier), 'Device A', -6.81234, 107.19451);
    }

    public function test_employee_using_student_permanent_qr_is_denied_before_attendance_write(): void
    {
        Cache::flush();

        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldNotReceive('fetchAll');
        $attendanceRepo->shouldNotReceive('create');

        $service = new QRAttendanceService(
            $attendanceRepo,
            Mockery::mock(EmployeeRepositoryInterface::class),
            Mockery::mock(EnterpriseEventService::class)
        );

        Carbon::setTestNow('2026-08-23 20:10:00');
        $this->actingAs(new GenericUser(['id' => 'USR-EMP-A', 'User_ID' => 'USR-EMP-A', 'Role' => 'HR']));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('khusus untuk Presensi Siswa');

        $service->processScan(
            $this->permanentQrUrl('student', 'wms-att-stu-exist01'),
            'Employee Device',
            -6.81234,
            107.19451
        );
    }

    private function putOpenEmployeeSession(): void
    {
        Cache::forever('qr_session_QRS-TEST', [
            'Session_ID' => 'QRS-TEST',
            'Title' => 'Presensi Pegawai',
            'Date' => '2026-08-23',
            'Start_Time' => '08:00',
            'End_Time' => '17:00',
            'Grace_Period' => 30,
            'Status' => 'ACTIVE',
            'Created_At' => '2026-08-23 07:00:00',
        ]);
    }

    private function disableEmployeeGeofence(): void
    {
        $this->configureEmployeeSettings(3600);
    }

    private function configureEmployeeSettings(
        int $ttl,
        mixed $latitude = -6.81234,
        mixed $longitude = 107.19451
    ): void {
        $settingService = Mockery::mock(SystemSettingService::class);
        $settingService->shouldReceive('get')->andReturnUsing(function ($key, $default = null) use ($ttl, $latitude, $longitude) {
            return match ($key) {
                'LPK_LATITUDE' => $latitude,
                'LPK_LONGITUDE' => $longitude,
                'LPK_ALLOWED_RADIUS_METERS' => 100,
                'QR_TOKEN_TTL_SECONDS' => $ttl,
                default => $default,
            };
        });
        $this->app->instance(SystemSettingService::class, $settingService);
    }

    private function putOpenStudentSession(): void
    {
        Cache::forever('student_qr_session_STUDENT-QRS-2026-08-23', [
            'Session_ID' => 'STUDENT-QRS-2026-08-23',
            'Title' => 'Presensi Siswa',
            'Type' => 'STUDENT',
            'Date' => '2026-08-23',
            'Start_Time' => '06:00',
            'End_Time' => '08:30',
            'Status' => 'ACTIVE',
            'Created_At' => '2026-08-23 07:00:00',
        ]);
    }

    private function studentToken(): string
    {
        $sessionId = 'STUDENT-QRS-2026-08-23';
        $expiresAt = Carbon::now()->addSeconds(25)->timestamp;
        $nonce = 'STU-NONCE-'.Str::random(6);
        $qrType = 'STUDENT';
        $signature = hash_hmac('sha256', "{$qrType}|{$sessionId}|{$expiresAt}|{$nonce}", (string) config('app.key'));
        Cache::put("qr_student_nonce_{$nonce}", $sessionId, 60);

        return base64_encode(json_encode([
            'qr_type' => $qrType,
            'session_id' => $sessionId,
            'expires_at' => $expiresAt,
            'nonce' => $nonce,
            'sig' => $signature,
        ]));
    }

    private function studentRepositoryForQr(): StudentRepositoryInterface
    {
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->once()->andReturn([
            [
                'Student_ID' => 'STU-A',
                'User_ID' => 'USR-STU',
                'Full_Name' => 'Student A',
                'Batch_ID' => 'BATCH-1',
                'Class_ID' => 'CLASS-1',
                'Enrollment_Status' => 'Aktif',
                'Graduation_Status' => '',
                'Is_Active' => 'TRUE',
            ],
        ]);

        return $studentRepo;
    }

    private function studentQrSettings(
        mixed $latitude = -6.81234,
        mixed $longitude = 107.19451
    ): SystemSettingService {
        $settingService = Mockery::mock(SystemSettingService::class);
        $settingService->shouldReceive('get')->andReturnUsing(function ($key, $default = null) use ($latitude, $longitude) {
            return match ($key) {
                'LPK_LATITUDE' => $latitude,
                'LPK_LONGITUDE' => $longitude,
                'LPK_ALLOWED_RADIUS_METERS' => 20,
                'STUDENT_ATTENDANCE_START_TIME' => '06:00',
                'STUDENT_ATTENDANCE_END_TIME' => '08:30',
                'LATE_TOLERANCE_MINUTES' => 30,
                default => $default,
            };
        });

        return $settingService;
    }

    private function bindPermanentQrService(string $identifier, string $type, int $times, array $extra = []): void
    {
        $qrService = Mockery::mock(PermanentQrService::class);
        $qrService->shouldReceive('getQrByIdentifier')
            ->times($times)
            ->with(strtoupper($identifier))
            ->andReturn($this->permanentQrRecord($identifier, $type, $extra));
        $qrService->shouldReceive('getAvailabilityStatus')
            ->times($times)
            ->andReturn(['usable' => true, 'state' => 'ACTIVE', 'message' => 'QR Presensi aktif.']);

        $this->app->instance(PermanentQrService::class, $qrService);
    }

    private function permanentQrRecord(string $identifier, string $type, array $extra = []): array
    {
        return array_merge([
            'QR_ID' => strtoupper($type) === 'STUDENT' ? 'QR00001' : 'QR00002',
            'QR_TYPE' => strtoupper($type),
            'IDENTIFIER' => strtoupper($identifier),
            'LABEL' => 'Existing Admin QR',
            'STATUS' => 'ACTIVE',
            'CREATED_AT' => '2026-09-01 19:35:28',
            'CREATED_BY' => 'USR-ADMIN',
            'UPDATED_AT' => '2026-09-01 19:35:28',
            'UPDATED_BY' => 'USR-ADMIN',
            'DEACTIVATED_AT' => '',
            'ACTIVE_FROM' => '2026-09-02 07:20:00',
            'ACTIVE_UNTIL' => '2027-09-02 08:30:00',
        ], $extra);
    }

    private function permanentQrUrl(string $type, string $identifier): string
    {
        return "https://wms.example.test/attendance/scan/{$type}/{$identifier}";
    }

    private function studentsAcrossFourClasses(): array
    {
        return [
            [
                'Student_ID' => 'STU-A', 'User_ID' => 'USR-A', 'Full_Name' => 'Student A',
                'Batch_ID' => 'BATCH-A', 'Class_ID' => 'CLASS-A', 'Enrollment_Status' => 'Aktif',
                'Graduation_Status' => '', 'Is_Active' => 'TRUE',
            ],
            [
                'Student_ID' => 'STU-B', 'User_ID' => 'USR-B', 'Full_Name' => 'Student B',
                'Batch_ID' => 'BATCH-B', 'Class_ID' => 'CLASS-B', 'Enrollment_Status' => 'Aktif',
                'Graduation_Status' => '', 'Is_Active' => 'TRUE',
            ],
            [
                'Student_ID' => 'STU-C', 'User_ID' => 'USR-C', 'Full_Name' => 'Student C',
                'Batch_ID' => 'BATCH-C', 'Class_ID' => 'CLASS-C', 'Enrollment_Status' => 'Aktif',
                'Graduation_Status' => '', 'Is_Active' => 'TRUE',
            ],
            [
                'Student_ID' => 'STU-D', 'User_ID' => 'USR-D', 'Full_Name' => 'Student D',
                'Batch_ID' => 'BATCH-D', 'Class_ID' => 'CLASS-D', 'Enrollment_Status' => 'Aktif',
                'Graduation_Status' => '', 'Is_Active' => 'TRUE',
            ],
        ];
    }

    private function employeeRowsForQr(): array
    {
        return [
            [
                'Employee_ID' => 'EMP-A',
                'User_ID' => 'USR-EMP-A',
                'Full_Name' => 'Employee A',
                'Is_Active' => 'TRUE',
            ],
            [
                'Employee_ID' => 'EMP-B',
                'User_ID' => 'USR-EMP-B',
                'Full_Name' => 'Employee B',
                'Is_Active' => 'TRUE',
            ],
        ];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::flush();
        Mockery::close();
        parent::tearDown();
    }
}
