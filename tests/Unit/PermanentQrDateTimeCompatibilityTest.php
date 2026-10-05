<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\PermanentQrRepositoryInterface;
use App\Services\Core\ActivityLogService;
use App\Services\Core\PermanentQrService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PermanentQrDateTimeCompatibilityTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[DataProvider('supportedDateTimeProvider')]
    public function test_availability_accepts_only_supported_datetime_representations(
        string $activeFrom,
        string $activeUntil
    ): void {
        Carbon::setTestNow(CarbonImmutable::parse('2026-09-02 08:00:00', PermanentQrService::TIMEZONE));

        $result = $this->service()->getAvailabilityStatus($this->activeQr($activeFrom, $activeUntil));

        $this->assertSame('ACTIVE', $result['state']);
        $this->assertTrue($result['usable']);
    }

    public static function supportedDateTimeProvider(): array
    {
        return [
            'legacy seconds' => ['2026-09-02 07:20:00', '2027-09-02 08:30:00'],
            'production DATETIME(6) zero microseconds' => [
                '2026-09-02 07:20:00.000000',
                '2027-09-02 08:30:00.000000',
            ],
            'DATETIME(6) non-zero microseconds' => [
                '2026-09-02 07:20:00.123456',
                '2027-09-02 08:30:00.654321',
            ],
        ];
    }

    #[DataProvider('invalidDateTimeProvider')]
    public function test_availability_rejects_missing_malformed_and_invalid_calendar_datetimes(mixed $activeFrom): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-09-02 08:00:00', PermanentQrService::TIMEZONE));

        $result = $this->service()->getAvailabilityStatus(
            $this->activeQr($activeFrom, '2027-09-02 08:30:00.000000')
        );

        $this->assertSame('INVALID', $result['state']);
        $this->assertFalse($result['usable']);
    }

    public static function invalidDateTimeProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'malformed text' => ['abc'],
            'slash-separated date' => ['2026/09/02 07:20:00'],
            'day-first date' => ['02-09-2026 07:20:00'],
            'date only' => ['2026-09-02'],
            'time only' => ['07:20:00'],
            'invalid month' => ['2026-13-02 07:20:00'],
            'invalid day' => ['2026-02-30 07:20:00'],
            'invalid hour' => ['2026-09-02 25:20:00'],
            'wrong microsecond precision' => ['2026-09-02 07:20:00.123'],
        ];
    }

    #[DataProvider('lifecycleProvider')]
    public function test_datetime_six_preserves_existing_availability_states(
        string $status,
        string $now,
        string $expectedState,
        bool $expectedUsable
    ): void {
        Carbon::setTestNow(CarbonImmutable::parse($now, PermanentQrService::TIMEZONE));

        $result = $this->service()->getAvailabilityStatus([
            'STATUS' => $status,
            'ACTIVE_FROM' => '2026-09-02 07:20:00.123456',
            'ACTIVE_UNTIL' => '2027-09-02 08:30:00.654321',
        ]);

        $this->assertSame($expectedState, $result['state']);
        $this->assertSame($expectedUsable, $result['usable']);
    }

    public static function lifecycleProvider(): array
    {
        return [
            'inactive' => ['INACTIVE', '2026-09-02 08:00:00', 'INACTIVE', false],
            'upcoming before microsecond boundary' => [
                'ACTIVE',
                '2026-09-02 07:20:00.123455',
                'UPCOMING',
                false,
            ],
            'active at microsecond boundary' => [
                'ACTIVE',
                '2026-09-02 07:20:00.123456',
                'ACTIVE',
                true,
            ],
            'expired after microsecond boundary' => [
                'ACTIVE',
                '2027-09-02 08:30:00.654322',
                'EXPIRED',
                false,
            ],
        ];
    }

    private function service(): PermanentQrService
    {
        return new PermanentQrService(
            Mockery::mock(PermanentQrRepositoryInterface::class),
            Mockery::mock(ActivityLogService::class)
        );
    }

    private function activeQr(mixed $activeFrom, mixed $activeUntil): array
    {
        return [
            'STATUS' => 'ACTIVE',
            'ACTIVE_FROM' => $activeFrom,
            'ACTIVE_UNTIL' => $activeUntil,
        ];
    }
}
