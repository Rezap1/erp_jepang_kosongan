<?php

namespace Tests\Unit;

use App\Exceptions\DuplicatePrimaryKeyException;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Academic\StudentQRAttendanceService;
use App\Services\Attendance\AttendanceWindowException;
use App\Services\Attendance\AttendanceWindowService;
use App\Services\Core\AttendanceService;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\PermanentQrService;
use App\Services\Core\SystemSettingService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CriticalAttendanceIntegrityTest extends TestCase
{
    #[DataProvider('attendanceBoundaryProvider')]
    public function test_attendance_window_is_a_hard_server_side_gate(
        string $time,
        bool $allowed,
        ?string $status,
        ?string $reasonCode
    ): void {
        Carbon::setTestNow("2026-09-29 {$time}");
        $windowService = new AttendanceWindowService($this->settings());
        $window = $windowService->resolve();

        if (! $allowed) {
            try {
                $windowService->assertOpen($window);
                $this->fail("{$time} seharusnya ditolak.");
            } catch (AttendanceWindowException $e) {
                $this->assertSame($reasonCode, $e->reasonCode);
            }

            return;
        }

        $windowService->assertOpen($window);
        $this->assertSame($status, $windowService->resolveStudentStatus($window)['status']);
    }

    public static function attendanceBoundaryProvider(): array
    {
        return [
            '05:00 rejected' => ['05:00:00', false, null, 'TOO_EARLY'],
            'one second before start rejected' => ['05:59:59', false, null, 'TOO_EARLY'],
            'start inclusive' => ['06:00:00', true, 'PRESENT', null],
            '06:15 present' => ['06:15:00', true, 'PRESENT', null],
            '06:30 present' => ['06:30:00', true, 'PRESENT', null],
            '06:30:01 present' => ['06:30:01', true, 'PRESENT', null],
            '07:00 present' => ['07:00:00', true, 'PRESENT', null],
            '07:20 present' => ['07:20:00', true, 'PRESENT', null],
            '07:50 present' => ['07:50:00', true, 'PRESENT', null],
            '08:00 present' => ['08:00:00', true, 'PRESENT', null],
            'one second before end present' => ['08:29:59', true, 'PRESENT', null],
            'end inclusive present' => ['08:30:00', true, 'PRESENT', null],
            'one second after end rejected' => ['08:30:01', false, null, 'TOO_LATE'],
            '09:00 rejected' => ['09:00:00', false, null, 'TOO_LATE'],
            '23:00 rejected' => ['23:00:00', false, null, 'TOO_LATE'],
        ];
    }

    #[DataProvider('studentPresentTimeProvider')]
    public function test_student_attendance_never_generates_late_inside_valid_window(string $time): void
    {
        $windowService = new AttendanceWindowService($this->settings([
            'LATE_TOLERANCE_MINUTES' => 'malformed-but-student-irrelevant',
        ]));
        $window = $windowService->resolve(
            CarbonImmutable::parse("2026-09-29 {$time}", AttendanceWindowService::TIMEZONE)
        );

        $windowService->assertOpen($window);
        $decision = $windowService->resolveStudentStatus($window);

        $this->assertSame('PRESENT', $decision['status']);
        $this->assertSame(0, $decision['late_minutes']);
    }

    public static function studentPresentTimeProvider(): array
    {
        return [
            '06:00' => ['06:00:00'],
            '06:31' => ['06:31:00'],
            '07:00' => ['07:00:00'],
            '07:30' => ['07:30:00'],
            '08:00' => ['08:00:00'],
            '08:30' => ['08:30:00'],
        ];
    }

    #[DataProvider('utcToWibBoundaryProvider')]
    public function test_utc_instants_resolve_to_canonical_wib_window(
        string $utcInstant,
        string $expectedWib,
        bool $allowed
    ): void {
        $windowService = new AttendanceWindowService($this->settings());
        $window = $windowService->resolve(CarbonImmutable::parse($utcInstant, 'UTC'));

        $this->assertSame($expectedWib, $window['now']->format('Y-m-d H:i:s T'));

        if (! $allowed) {
            $this->expectException(AttendanceWindowException::class);
        }

        $windowService->assertOpen($window);
    }

    public static function utcToWibBoundaryProvider(): array
    {
        return [
            '23:00 UTC previous date opens at 06:00 WIB' => [
                '2026-09-28 23:00:00', '2026-09-29 06:00:00 WIB', true,
            ],
            '01:30 UTC closes at 08:30 WIB' => [
                '2026-09-29 01:30:00', '2026-09-29 08:30:00 WIB', true,
            ],
            '01:30:01 UTC is after close' => [
                '2026-09-29 01:30:01', '2026-09-29 08:30:01 WIB', false,
            ],
        ];
    }

    public function test_window_is_independent_of_php_default_timezone(): void
    {
        $originalTimezone = date_default_timezone_get();

        try {
            $results = [];
            foreach (['UTC', AttendanceWindowService::TIMEZONE] as $hostTimezone) {
                date_default_timezone_set($hostTimezone);
                $window = (new AttendanceWindowService($this->settings()))->resolve(
                    CarbonImmutable::parse('2026-09-28 23:00:00', 'UTC')
                );
                $results[$hostTimezone] = [
                    $window['now']->format('Y-m-d H:i:s'),
                    (new AttendanceWindowService($this->settings()))
                        ->resolveStudentStatus($window)['status'],
                ];
            }

            $this->assertSame($results['UTC'], $results[AttendanceWindowService::TIMEZONE]);
            $this->assertSame(['2026-09-29 06:00:00', 'PRESENT'], $results['UTC']);
        } finally {
            date_default_timezone_set($originalTimezone);
        }
    }

    #[DataProvider('rejectedTimeProvider')]
    public function test_rejected_attendance_must_never_create_present_record(
        string $time,
        string $message
    ): void {
        Carbon::setTestNow("2026-09-29 {$time}");
        $attendance = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendance->shouldNotReceive('fetchAll');
        $attendance->shouldNotReceive('create');
        $attendance->shouldNotReceive('update');

        $service = $this->service($attendance, null, 0);

        try {
            $service->processStudentScan($this->permanentQrUrl(), -6.81234, 107.19451, 'Integrity Test');
            $this->fail('Pemindaian di luar window seharusnya ditolak.');
        } catch (AttendanceWindowException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    public static function rejectedTimeProvider(): array
    {
        return [
            'too early' => ['05:59:59', 'Absensi dimulai pukul 06:00'],
            'too late' => ['08:30:01', 'berakhir pada pukul 08:30'],
        ];
    }

    public function test_rejected_attempt_does_not_poison_later_valid_attempt(): void
    {
        $attendance = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendance->shouldReceive('fetchAll')->once()->andReturn([]);
        $attendance->shouldReceive('create')->once()->andReturn(true);
        $event = Mockery::mock(EnterpriseEventService::class);
        $event->shouldReceive('dispatch')->once();
        $service = $this->service($attendance, $event);

        Carbon::setTestNow('2026-09-29 05:59:59');
        try {
            $service->processStudentScan($this->permanentQrUrl(), -6.81234, 107.19451);
            $this->fail('Attempt pertama seharusnya ditolak.');
        } catch (AttendanceWindowException $e) {
            $this->assertSame('TOO_EARLY', $e->reasonCode);
        }

        Carbon::setTestNow('2026-09-29 07:30:00');
        $result = $service->processStudentScan($this->permanentQrUrl(), -6.81234, 107.19451);

        $this->assertSame('PRESENT', $result['status']);
    }

    public function test_dynamic_qr_expired_inside_window_is_zero_write(): void
    {
        Carbon::setTestNow('2026-09-29 07:30:00');
        $attendance = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendance->shouldNotReceive('fetchAll');
        $attendance->shouldNotReceive('create');
        $service = $this->service($attendance, null, 0);
        $this->putStudentSession();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('kedaluwarsa');
        $service->processStudentScan($this->dynamicToken(-1), -6.81234, 107.19451);
    }

    public function test_invalid_dynamic_qr_inside_window_is_zero_write(): void
    {
        Carbon::setTestNow('2026-09-29 07:30:00');
        $attendance = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendance->shouldNotReceive('fetchAll');
        $attendance->shouldNotReceive('create');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('tidak valid');
        $this->service($attendance, null, 0)->processStudentScan('not-a-token', -6.81234, 107.19451);
    }

    public function test_post_commit_event_failure_cannot_turn_success_into_rejection(): void
    {
        Carbon::setTestNow('2026-09-29 07:30:00');
        $written = [];
        $attendance = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendance->shouldReceive('fetchAll')->once()->andReturn([]);
        $attendance->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $row) use (&$written): bool {
                $written[] = $row;

                return true;
            }))
            ->andReturn(true);
        $event = Mockery::mock(EnterpriseEventService::class);
        $event->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('notification unavailable'));

        $result = $this->service($attendance, $event)->processStudentScan(
            $this->permanentQrUrl(),
            -6.81234,
            107.19451
        );

        $this->assertCount(1, $written);
        $this->assertSame('PRESENT', $written[0]['Status']);
        $this->assertSame($written[0]['Attendance_ID'], $result['attendance_id']);
    }

    public function test_repository_write_failure_never_returns_success(): void
    {
        Carbon::setTestNow('2026-09-29 07:30:00');
        $attendance = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendance->shouldReceive('fetchAll')->once()->andReturn([]);
        $attendance->shouldReceive('create')->once()->andThrow(new RuntimeException('Sheets unavailable'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Sheets unavailable');
        $this->service($attendance)->processStudentScan($this->permanentQrUrl(), -6.81234, 107.19451);
    }

    public function test_repository_primary_key_barrier_prevents_concurrent_duplicate(): void
    {
        Carbon::setTestNow('2026-09-29 07:30:00');
        $attendance = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendance->shouldReceive('fetchAll')->once()->andReturn([]);
        $attendance->shouldReceive('create')->once()->andThrow(new DuplicatePrimaryKeyException('duplicate'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('sudah melakukan presensi');
        $this->service($attendance)->processStudentScan($this->permanentQrUrl(), -6.81234, 107.19451);
    }

    public function test_legacy_core_service_cannot_bypass_authorized_write_workflows(): void
    {
        $attendance = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendance->shouldNotReceive('create');
        $attendance->shouldNotReceive('update');
        $attendance->shouldNotReceive('delete');
        $legacyService = new AttendanceService($attendance);

        foreach (['create', 'update', 'delete'] as $operation) {
            try {
                match ($operation) {
                    'create' => $legacyService->create([]),
                    'update' => $legacyService->update('ATT-1', []),
                    'delete' => $legacyService->delete('ATT-1'),
                };
                $this->fail("Legacy {$operation} seharusnya dinonaktifkan.");
            } catch (LogicException $e) {
                $this->assertStringContainsString('disabled', $e->getMessage());
            }
        }
    }

    #[DataProvider('invalidConfigurationProvider')]
    public function test_invalid_attendance_configuration_fails_closed(array $overrides): void
    {
        Carbon::setTestNow('2026-09-29 07:30:00');
        $attendance = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendance->shouldNotReceive('fetchAll');
        $attendance->shouldNotReceive('create');

        $this->expectException(InvalidArgumentException::class);
        $this->service($attendance, null, 0, $overrides)
            ->processStudentScan($this->permanentQrUrl(), -6.81234, 107.19451);
    }

    public static function invalidConfigurationProvider(): array
    {
        return [
            'missing start' => [['STUDENT_ATTENDANCE_START_TIME' => '']],
            'missing end' => [['STUDENT_ATTENDANCE_END_TIME' => '']],
            'malformed start' => [['STUDENT_ATTENDANCE_START_TIME' => '6:00']],
            'same boundary' => [['STUDENT_ATTENDANCE_END_TIME' => '06:00']],
            'overnight unsupported' => [['STUDENT_ATTENDANCE_START_TIME' => '22:00', 'STUDENT_ATTENDANCE_END_TIME' => '06:00']],
        ];
    }

    private function service(
        AttendanceRepositoryInterface $attendance,
        ?EnterpriseEventService $event = null,
        int $permanentQrCalls = 1,
        array $settingOverrides = []
    ): StudentQRAttendanceService {
        $this->actingAs(new GenericUser([
            'id' => 'USR-STU',
            'User_ID' => 'USR-STU',
            'Role' => 'STUDENT',
        ]));

        $student = Mockery::mock(StudentRepositoryInterface::class);
        $student->shouldReceive('fetchAll')->atLeast()->once()->andReturn([$this->studentRow()]);

        if ($permanentQrCalls > 0) {
            $qr = Mockery::mock(PermanentQrService::class);
            $qr->shouldReceive('getQrByIdentifier')->times($permanentQrCalls)->andReturn([
                'QR_TYPE' => 'STUDENT',
                'STATUS' => 'ACTIVE',
                'IDENTIFIER' => 'WMS-ATT-STU-H874',
            ]);
            $qr->shouldReceive('getAvailabilityStatus')->times($permanentQrCalls)->andReturn([
                'usable' => true,
                'state' => 'ACTIVE',
                'message' => 'QR aktif.',
            ]);
            $this->app->instance(PermanentQrService::class, $qr);
        }

        return new StudentQRAttendanceService(
            $attendance,
            $student,
            $this->settings($settingOverrides),
            $event ?: Mockery::mock(EnterpriseEventService::class)
        );
    }

    private function settings(array $overrides = []): SystemSettingService
    {
        $values = array_merge([
            'STUDENT_ATTENDANCE_START_TIME' => '06:00',
            'STUDENT_ATTENDANCE_END_TIME' => '08:30',
            'LATE_TOLERANCE_MINUTES' => '30',
            'LPK_LATITUDE' => '-6.81234',
            'LPK_LONGITUDE' => '107.19451',
            'LPK_ALLOWED_RADIUS_METERS' => '20',
            'QR_TOKEN_TTL_SECONDS' => '25',
        ], $overrides);

        $settings = Mockery::mock(SystemSettingService::class);
        $settings->shouldReceive('get')->andReturnUsing(
            fn (string $key, mixed $default = null): mixed => array_key_exists($key, $values)
                ? $values[$key]
                : $default
        );

        return $settings;
    }

    private function studentRow(): array
    {
        return [
            'Student_ID' => 'STU-H874',
            'User_ID' => 'USR-STU',
            'Full_Name' => 'Siswa Integrity',
            'Batch_ID' => 'BATCH-1',
            'Class_ID' => 'CLASS-1',
            'Enrollment_Status' => 'AKTIF',
            'Graduation_Status' => '',
            'Is_Active' => 'TRUE',
        ];
    }

    private function permanentQrUrl(): string
    {
        return 'https://wms.test/attendance/scan/student/WMS-ATT-STU-H874';
    }

    private function putStudentSession(): void
    {
        Cache::forever('student_qr_session_STUDENT-QRS-2026-09-29', [
            'Session_ID' => 'STUDENT-QRS-2026-09-29',
            'Type' => 'STUDENT',
            'Date' => '2026-09-29',
            'Status' => 'ACTIVE',
        ]);
    }

    private function dynamicToken(int $expiresOffsetSeconds): string
    {
        $sessionId = 'STUDENT-QRS-2026-09-29';
        $expiresAt = Carbon::now()->addSeconds($expiresOffsetSeconds)->timestamp;
        $nonce = 'H874-'.Str::random(8);
        $type = 'STUDENT';
        $signature = hash_hmac(
            'sha256',
            "{$type}|{$sessionId}|{$expiresAt}|{$nonce}",
            (string) config('app.key')
        );
        Cache::put("qr_student_nonce_{$nonce}", $sessionId, 60);

        return base64_encode(json_encode([
            'qr_type' => $type,
            'session_id' => $sessionId,
            'expires_at' => $expiresAt,
            'nonce' => $nonce,
            'sig' => $signature,
        ]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::flush();
        Mockery::close();
        parent::tearDown();
    }
}
