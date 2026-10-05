<?php

namespace App\Services\HR;

use App\Exceptions\DuplicatePrimaryKeyException;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Repositories\MySql\BaseMySqlRepository;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\PermanentQrService;
use App\Services\Core\RoleService;
use App\Services\Core\SystemSettingService;
use App\Support\CoordinateNormalizer;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QRAttendanceService
{
    // Removed hardcoded constants to rely entirely on SystemSettingService for dynamic HR configs

    protected $attendanceRepository;

    protected $employeeRepository;

    protected $enterpriseEvent;

    public function __construct(
        AttendanceRepositoryInterface $attendanceRepository,
        EmployeeRepositoryInterface $employeeRepository,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->attendanceRepository = $attendanceRepository;
        $this->employeeRepository = $employeeRepository;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function createSession(array $data): array
    {
        $user = auth()->user();
        if (! $user) {
            throw new Exception('Sesi pengguna tidak valid. Silakan login kembali.');
        }

        $creator = $user->Full_Name ?? $user->Name ?? $user->Email ?? $user->email ?? $user->User_ID ?? null;
        if (! $creator) {
            throw new Exception('Identitas pembuat sesi QR tidak valid.');
        }

        $settingService = app(SystemSettingService::class);
        $gracePeriod = (int) $settingService->get('LATE_TOLERANCE_MINUTES', 30);

        $sessionId = 'QRS-'.date('Ymd').'-'.strtoupper(Str::random(6));
        $session = [
            'Session_ID' => $sessionId,
            'Title' => $data['Title'] ?? 'Presensi Kehadiran Pegawai',
            'Date' => now()->toDateString(),
            'Start_Time' => $data['Start_Time'] ?? '08:00',
            'End_Time' => $data['End_Time'] ?? '17:00',
            'Grace_Period' => $gracePeriod,
            'Status' => 'ACTIVE',
            'Created_By' => $creator,
            'Created_At' => now()->toDateTimeString(),
            'Notes' => $data['Notes'] ?? '',
        ];

        Cache::forever("qr_session_{$sessionId}", $session);

        $sessionsList = Cache::get('qr_sessions_list', []);
        $sessionsList[$sessionId] = $session;
        Cache::forever('qr_sessions_list', $sessionsList);

        $this->enterpriseEvent->dispatch(
            'HR',
            'CREATE',
            'ATTENDANCE_SESSION',
            $sessionId,
            $user->User_ID ?? auth()->id(),
            ['HR', 'ADMINISTRATOR'],
            [],
            $session
        );

        return $session;
    }

    public function getSession(string $sessionId): ?array
    {
        $session = Cache::get("qr_session_{$sessionId}");
        if (! $session) {
            $sessionsList = Cache::get('qr_sessions_list', []);
            $session = $sessionsList[$sessionId] ?? null;
        }

        return $session;
    }

    public function generateDynamicToken(string $sessionId): array
    {
        $session = $this->getSession($sessionId);
        if (! $this->isSessionOpen($session)) {
            throw new Exception("Sesi kehadiran QR #{$sessionId} telah ditutup atau tidak aktif.");
        }

        $settingService = app(SystemSettingService::class);
        $ttlSeconds = (int) $settingService->get('QR_TOKEN_TTL_SECONDS', 25);

        $expiresAt = now()->addSeconds($ttlSeconds)->timestamp;
        $nonce = Str::random(16);
        $signature = hash_hmac('sha256', "{$sessionId}|{$expiresAt}|{$nonce}", $this->signingKey());

        $payload = [
            'session_id' => $sessionId,
            'expires_at' => $expiresAt,
            'nonce' => $nonce,
            'sig' => $signature,
        ];

        $tokenString = base64_encode(json_encode($payload));

        // Keep nonce valid for the token lifetime. It is intentionally multi-user, not single-use.
        Cache::put("qr_nonce_{$nonce}", $sessionId, $ttlSeconds);

        return [
            'token' => $tokenString,
            'expires_in' => $ttlSeconds,
            'session' => $session,
        ];
    }

    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    public function processScan(string $tokenString, ?string $deviceInfo = null, ?float $userLat = null, ?float $userLon = null): array
    {
        // 1. Decode Payload & Identify QR Type
        if ($this->extractPermanentQrIdentifier($tokenString, 'STU')) {
            throw new Exception('Akses Ditolak: QR Code ini khusus untuk Presensi Siswa.');
        }

        $permanentIdentifier = $this->extractPermanentQrIdentifier($tokenString, 'EMP');
        if ($permanentIdentifier !== null) {
            $permanentQrService = app(PermanentQrService::class);
            $qr = $permanentQrService->getQrByIdentifier($permanentIdentifier);

            if (! $qr || strtoupper(trim((string) ($qr['QR_TYPE'] ?? ''))) !== 'EMPLOYEE' || strtoupper(trim((string) ($qr['STATUS'] ?? ''))) !== 'ACTIVE') {
                throw new Exception('QR Code Permanen tidak valid atau sudah tidak aktif.');
            }

            $availability = $permanentQrService->getAvailabilityStatus($qr);
            if (! $availability['usable']) {
                throw new Exception($availability['message']);
            }

            // Permanent Admin QR validity is controlled by MASTER_PERMANENT_QR.
            // It must not require HR to open a short-lived dynamic QR session.
            $session = $this->getPermanentEmployeeSessionContext();
            $sessionId = $session['Session_ID'];
            $nonce = 'PERM-'.Str::random(10);

        } else {
            $decodedJson = base64_decode($tokenString, true);
            if (! $decodedJson) {
                throw new Exception('Payload QR Code tidak valid.');
            }

            $payload = json_decode($decodedJson, true);
            if (! is_array($payload) || empty($payload['session_id']) || empty($payload['expires_at']) || empty($payload['nonce']) || empty($payload['sig'])) {
                throw new Exception('Format data Token QR Code tidak valid.');
            }

            $qrType = $payload['qr_type'] ?? 'EMPLOYEE';
            $sessionId = $payload['session_id'];
            $expiresAt = (int) $payload['expires_at'];
            $nonce = $payload['nonce'];
            $sig = $payload['sig'];

            // Strict QR Type Check
            if (strtoupper($qrType) === 'STUDENT') {
                throw new Exception('Akses Ditolak: QR Code ini khusus untuk Presensi Siswa.');
            }

            // 2. Security Validation: Expiration TTL Check
            if ($expiresAt < now()->timestamp) {
                throw new Exception('Token QR Code telah kadaluarsa. Mohon pindai ulang Token QR terbaru di layar.');
            }

            // 3. Security Validation: Single-use Nonce Replay Check
            if (! Cache::has("qr_nonce_{$nonce}")) {
                throw new Exception('Token QR Code telah digunakan atau tidak valid. Silakan pindai QR terbaru.');
            }

            // 4. Security Validation: HMAC Signature Verification
            $signingKey = $this->signingKey();
            $expectedSig = hash_hmac('sha256', "{$sessionId}|{$expiresAt}|{$nonce}", $signingKey);
            if (! hash_equals($expectedSig, $sig)) {
                $expectedSigWithType = hash_hmac('sha256', "{$qrType}|{$sessionId}|{$expiresAt}|{$nonce}", $signingKey);
                if (! hash_equals($expectedSigWithType, $sig)) {
                    throw new Exception('Tanda tangan digital QR Code tidak valid atau telah dimanipulasi.');
                }
            }

            // 5. Security Validation: Session Active Check
            $session = $this->getSession($sessionId);
            if (! $this->isSessionOpen($session)) {
                throw new Exception("Sesi kehadiran QR #{$sessionId} telah ditutup atau tidak aktif.");
            }
        }

        // 6. Security Validation: Server-side Authenticated Employee Resolution (NO CLIENT IDOR TRUST)
        $user = auth()->user();
        if (! $user) {
            throw new Exception('Sesi pengguna tidak valid. Silakan login kembali.');
        }

        $roleName = strtoupper(trim((string) ($user->Role ?? session('role', ''))));
        if (isset($user->Role_ID)) {
            $roleService = app(RoleService::class);
            $role = $roleService->getRoleById($user->Role_ID);
            $roleName = strtoupper(trim($role['Role_Name'] ?? $roleName));
        }

        if ($roleName === 'STUDENT' || str_contains($roleName, 'STUDENT')) {
            throw new Exception('Akses Ditolak: Hanya Pegawai/Guru yang dapat melakukan presensi HR.');
        }

        // GPS and the configured geofence are mandatory for every employee QR scan.
        $settingService = app(SystemSettingService::class);
        if ($userLat === null || $userLon === null || ! is_finite($userLat) || ! is_finite($userLon)
            || $userLat < -90 || $userLat > 90 || $userLon < -180 || $userLon > 180) {
            throw new Exception('GPS wajib aktif dan koordinat lokasi harus valid untuk presensi QR pegawai.');
        }

        $lpkLat = $this->parseRequiredCoordinate($settingService->get('LPK_LATITUDE', null), -90, 90, 'latitude');
        $lpkLon = $this->parseRequiredCoordinate($settingService->get('LPK_LONGITUDE', null), -180, 180, 'longitude');
        $maxRadius = $this->parseRequiredRadius(
            $settingService->get('LPK_ALLOWED_RADIUS_METERS', null)
        );
        $distance = $this->calculateDistance($userLat, $userLon, $lpkLat, $lpkLon);
        if ($distance > $maxRadius) {
            throw new Exception("Anda berada di luar area LPK. Jarak Anda: {$distance} meter. Maksimal jarak yang diizinkan: {$maxRadius} meter.");
        }

        $employee = $this->resolveEmployeeForUser($user);
        if (! $employee || strtoupper(trim($employee['Is_Active'] ?? 'TRUE')) === 'FALSE') {
            throw new Exception('Akses Ditolak: Profil pegawai Anda tidak ditemukan atau sedang tidak aktif.');
        }

        $employeeId = $employee['Employee_ID'];

        // 7. Atomic Concurrency Lock & Duplicate Check
        $lockKey = "qr_scan_{$sessionId}_{$employeeId}";

        return Cache::lock($lockKey, 10)->block(3, function () use ($sessionId, $session, $user, $employee, $employeeId, $deviceInfo, $distance) {
            return $this->runtimeTransaction(function () use ($sessionId, $session, $user, $employee, $employeeId, $deviceInfo, $distance): array {
                // Check Duplicate Attendance for this Employee in this Session
                $allAttendances = collect($this->attendanceRepository->fetchAll());
                $existing = $allAttendances->first(function ($att) use ($sessionId, $employeeId) {
                    return ($att['Employee_ID'] ?? '') === $employeeId &&
                           ($att['Session_ID'] ?? '') === $sessionId &&
                           strtoupper(trim($att['Is_Active'] ?? 'TRUE')) !== 'FALSE';
                });

                if ($existing) {
                    throw new Exception("Presensi gagal: Anda ({$employee['Full_Name']}) sudah melakukan presensi untuk sesi ini.");
                }

                // Consume Nonce immediately to prevent replay
                // Cache::forget("qr_nonce_{$nonce}"); // REMOVED TO ALLOW MULTI-USER QR

                // 8. Server-side Time Rules (Asia/Jakarta)
                $nowCarbon = now();
                $currentTimeStr = $nowCarbon->format('H:i:s');
                $startTimeStr = $session['Start_Time'] ?? '08:00';

                $settingService = app(SystemSettingService::class);
                // Read the current policy at decision time so HR setting changes
                // apply to the next scan without rebuilding the session.
                $gracePeriod = (int) $settingService->get(
                    'LATE_TOLERANCE_MINUTES',
                    $session['Grace_Period'] ?? 30
                );

                $startAt = Carbon::parse(($session['Date'] ?? now()->toDateString()).' '.$startTimeStr);
                $lateThreshold = $startAt->copy()->addMinutes($gracePeriod);
                $isLate = $nowCarbon->gt($lateThreshold);
                $status = $isLate ? 'LATE' : 'PRESENT';
                $lateMinutes = $isLate ? (int) max(1, $startAt->diffInMinutes($nowCarbon)) : 0;

                // 9. Persistence
                $attendanceId = 'ATT-EQR-'.strtoupper(substr(hash('sha256', "{$employeeId}|{$sessionId}"), 0, 20));
                $record = [
                    'Attendance_ID' => $attendanceId,
                    'Employee_ID' => $employeeId,
                    'User_ID' => $user->User_ID,
                    'Session_ID' => $sessionId,
                    'Attendance_Date' => now()->toDateString(),
                    'Check_In_Time' => $currentTimeStr,
                    'Status' => $status,
                    'Late_Minutes' => $lateMinutes,
                    'Verification_Method' => 'EMPLOYEE_GEO_QR',
                    'Device_Info' => $deviceInfo ?? request()->header('User-Agent', 'Mobile Scanner'),
                    'Is_Active' => 'TRUE',
                    'Created_At' => now()->toDateTimeString(),
                ];

                try {
                    $res = $this->attendanceRepository->create($record);
                } catch (DuplicatePrimaryKeyException $e) {
                    throw new Exception("Presensi gagal: Anda ({$employee['Full_Name']}) sudah melakukan presensi untuk sesi ini.", 0, $e);
                }
                if ($res === false || $res === null) {
                    throw new Exception('Presensi pegawai gagal disimpan ke penyimpanan.');
                }

                try {
                    Cache::forget("employee_attendance_{$employeeId}");
                    Cache::forget('hr_dashboard');
                } catch (\Throwable $cacheFailure) {
                    $this->logPostCommitFailure($attendanceId, $cacheFailure, 'cache');
                }

                // 10. Unified Event Dispatch
                try {
                    $this->enterpriseEvent->dispatch(
                        'HR',
                        'CREATE',
                        'ATTENDANCE',
                        $attendanceId,
                        $user->User_ID ?? auth()->id(),
                        ['HR', 'ADMINISTRATOR'],
                        [$employeeId],
                        [
                            'Employee_Name' => $employee['Full_Name'] ?? $employeeId,
                            'Status' => $status,
                            'Late_Minutes' => $lateMinutes,
                            'Check_In_Time' => $currentTimeStr,
                        ]
                    );
                } catch (\Throwable $eventFailure) {
                    $this->logPostCommitFailure($attendanceId, $eventFailure, 'event');
                }

                return [
                    'attendance' => $record,
                    'employee' => [
                        'id' => $employeeId,
                        'name' => $employee['Full_Name'] ?? $employeeId,
                    ],
                    'action' => 'CHECK_IN',
                    'status' => $status,
                    'late_minutes' => $lateMinutes,
                    'check_in_time' => $currentTimeStr,
                    'check_out_time' => null,
                    'distance_meters' => $distance,
                ];
            }, [[$this->employeeRepository, (string) $employeeId]]);
        });
    }

    /** @param list<array{0: object, 1: string}> $locks */
    private function runtimeTransaction(callable $callback, array $locks = [])
    {
        if (! $this->attendanceRepository instanceof BaseMySqlRepository) {
            return $callback();
        }

        return $this->attendanceRepository->transaction(function () use ($callback, $locks) {
            foreach ($locks as [$repository, $id]) {
                if ($repository instanceof BaseMySqlRepository && trim($id) !== '') {
                    $repository->lockById($id);
                }
            }

            return $callback();
        });
    }

    public function closeSession(string $sessionId): array
    {
        $user = auth()->user();
        if (! $user) {
            throw new Exception('Sesi pengguna tidak valid. Silakan login kembali.');
        }

        $session = $this->getSession($sessionId);
        if (! $session) {
            throw new Exception("Sesi kehadiran QR #{$sessionId} tidak ditemukan.");
        }

        $session['Status'] = 'CLOSED';
        $session['Closed_At'] = now()->toDateTimeString();

        Cache::forever("qr_session_{$sessionId}", $session);

        $sessionsList = Cache::get('qr_sessions_list', []);
        $sessionsList[$sessionId] = $session;
        Cache::forever('qr_sessions_list', $sessionsList);

        $this->enterpriseEvent->dispatch(
            'HR',
            'UPDATE',
            'ATTENDANCE_SESSION',
            $sessionId,
            $user->User_ID ?? auth()->id(),
            ['HR', 'ADMINISTRATOR'],
            [],
            ['Status' => 'CLOSED']
        );

        return $session;
    }

    public function getSessionSummary(string $sessionId): array
    {
        $session = $this->getSession($sessionId);
        $allAttendances = collect($this->attendanceRepository->fetchAll())
            ->where('Session_ID', $sessionId)
            ->where('Is_Active', '!=', 'FALSE');

        $employees = collect($this->employeeRepository->fetchAll())->keyBy('Employee_ID');

        $attended = $allAttendances->map(function ($att) use ($employees) {
            $empId = $att['Employee_ID'] ?? null;
            $att['Employee_Name'] = ($empId && isset($employees[$empId])) ? $employees[$empId]['Full_Name'] : ($empId ?? '-');

            return $att;
        })->values();

        return [
            'session' => $session,
            'total_scanned' => $attended->count(),
            'present_count' => $attended->where('Status', 'PRESENT')->count(),
            'late_count' => $attended->where('Status', 'LATE')->count(),
            'attendances' => $attended,
        ];
    }

    private function getLatestOpenSession(): ?array
    {
        $sessions = collect(Cache::get('qr_sessions_list', []))
            ->filter(function ($session) {
                return $this->isSessionOpen($session);
            })
            ->sortByDesc('Created_At')
            ->values();

        $openSession = $sessions->first();
        if (! $openSession) {
            $defaultSession = $this->getOrCreateDefaultEmployeeSession();
            $openSession = $this->isSessionOpen($defaultSession) ? $defaultSession : null;
        }

        return $openSession;
    }

    private function getPermanentEmployeeSessionContext(): array
    {
        $session = $this->latestActiveSessionForToday() ?? $this->getOrCreateDefaultEmployeeSession();
        $settingService = app(SystemSettingService::class);

        $session['Session_ID'] = $session['Session_ID'] ?? 'EMP-QRS-'.now()->toDateString();
        $session['Date'] = now()->toDateString();
        $session['Start_Time'] = $session['Start_Time'] ?? $settingService->get('WORK_START_TIME', '07:00');
        $session['End_Time'] = $session['End_Time'] ?? $settingService->get('WORK_END_TIME', '18:00');
        $session['Grace_Period'] = (int) $settingService->get('LATE_TOLERANCE_MINUTES', $session['Grace_Period'] ?? 30);
        $session['Status'] = 'ACTIVE';

        return $session;
    }

    private function latestActiveSessionForToday(): ?array
    {
        $today = now()->toDateString();

        return collect(Cache::get('qr_sessions_list', []))
            ->filter(function ($session) use ($today) {
                return strtoupper(trim((string) ($session['Status'] ?? ''))) === 'ACTIVE'
                    && (($session['Date'] ?? $today) === $today);
            })
            ->sortByDesc('Created_At')
            ->values()
            ->first();
    }

    private function extractPermanentQrIdentifier(string $tokenString, string $expectedActorPrefix): ?string
    {
        $decoded = rawurldecode($tokenString);
        $prefix = strtoupper($expectedActorPrefix);

        if (! preg_match('/\bWMS-ATT-'.preg_quote($prefix, '/').'-[A-Z0-9]+\b/i', $decoded, $matches)) {
            return null;
        }

        return strtoupper($matches[0]);
    }

    private function resolveEmployeeForUser($user): ?array
    {
        $userId = trim((string) ($user->User_ID ?? ''));
        if ($userId === '') {
            return null;
        }

        foreach ($this->employeeRepository->fetchAll() as $candidate) {
            if (strcasecmp(trim((string) ($candidate['User_ID'] ?? '')), $userId) === 0) {
                return $candidate;
            }
        }

        return null;
    }

    private function signingKey(): string
    {
        $key = trim((string) config('app.key'));
        if ($key === '') {
            throw new \RuntimeException('Kunci aplikasi untuk penandatanganan QR belum dikonfigurasi.');
        }

        return $key;
    }

    public function getOrCreateDefaultEmployeeSession(): array
    {
        $today = now()->toDateString();
        $sessionId = "EMP-QRS-{$today}";
        $settingService = app(SystemSettingService::class);
        $session = Cache::get("qr_session_{$sessionId}");
        if (! $session) {
            $workStart = $settingService->get('WORK_START_TIME', '07:00');
            $workEnd = $settingService->get('WORK_END_TIME', '18:00');
            $gracePeriod = (int) $settingService->get('LATE_TOLERANCE_MINUTES', 30);

            $session = [
                'Session_ID' => $sessionId,
                'Title' => 'Presensi Kehadiran Pegawai',
                'Date' => $today,
                'Start_Time' => $workStart,
                'End_Time' => $workEnd,
                'Grace_Period' => $gracePeriod,
                'Status' => 'ACTIVE',
                'Created_By' => 'SYSTEM',
                'Created_At' => now()->toDateTimeString(),
                'Notes' => 'Sesi harian otomatis untuk QR Permanen Pegawai',
            ];
            Cache::forever("qr_session_{$sessionId}", $session);

            $sessionsList = Cache::get('qr_sessions_list', []);
            $sessionsList[$sessionId] = $session;
            Cache::forever('qr_sessions_list', $sessionsList);
        } else {
            // This is the automatic daily fallback session; keep its window
            // synchronized with current HR settings.
            $session['Start_Time'] = $settingService->get('WORK_START_TIME', $session['Start_Time'] ?? '07:00');
            $session['End_Time'] = $settingService->get('WORK_END_TIME', $session['End_Time'] ?? '18:00');
            $session['Grace_Period'] = (int) $settingService->get('LATE_TOLERANCE_MINUTES', $session['Grace_Period'] ?? 30);
            Cache::forever("qr_session_{$sessionId}", $session);
            $sessionsList = Cache::get('qr_sessions_list', []);
            $sessionsList[$sessionId] = $session;
            Cache::forever('qr_sessions_list', $sessionsList);
        }

        return $session;
    }

    private function isSessionOpen(?array $session): bool
    {
        if (! $session || strtoupper(trim((string) ($session['Status'] ?? ''))) !== 'ACTIVE') {
            return false;
        }

        $sessionDate = $session['Date'] ?? now()->toDateString();
        if ($sessionDate !== now()->toDateString()) {
            return false;
        }

        if (! empty($session['Start_Time']) && ! empty($session['End_Time'])) {
            $nowTime = now()->format('H:i:s');
            $startTime = Carbon::parse($session['Start_Time'])->format('H:i:s');
            $endTime = Carbon::parse($session['End_Time'])->format('H:i:s');

            if ($startTime !== '00:00:00' || $endTime !== '23:59:59') {
                return $nowTime >= $startTime && $nowTime <= $endTime;
            }
        }

        return true;
    }

    private function calculateWorkDurationMinutes(?string $date, ?string $checkInTime, string $checkOutTime): int
    {
        if (empty($checkInTime)) {
            return 0;
        }

        try {
            $attendanceDate = $date ?: now()->toDateString();
            $checkIn = Carbon::parse("{$attendanceDate} {$checkInTime}");
            $checkOut = Carbon::parse("{$attendanceDate} {$checkOutTime}");

            return (int) max(0, $checkIn->diffInMinutes($checkOut));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function parseRequiredCoordinate(mixed $value, float $minimum, float $maximum, string $label): float
    {
        $coordinate = CoordinateNormalizer::parse(
            $value,
            $minimum,
            $maximum,
            allowMigratedFixedScaleInteger: true
        );
        if ($coordinate === null) {
            throw new Exception("Konfigurasi {$label} lokasi LPK belum valid. Presensi ditolak.");
        }

        return $coordinate;
    }

    private function parseRequiredRadius(mixed $value): float
    {
        $normalized = str_replace(',', '.', trim((string) $value));
        if ($normalized === '' || ! is_numeric($normalized)) {
            throw new Exception('Konfigurasi radius geofence belum valid. Presensi ditolak.');
        }

        $radius = (float) $normalized;
        if (! is_finite($radius) || $radius <= 0) {
            throw new Exception('Konfigurasi radius geofence belum valid. Presensi ditolak.');
        }

        return $radius;
    }

    private function logPostCommitFailure(string $attendanceId, \Throwable $failure, string $stage): void
    {
        try {
            Log::error('Employee attendance post-commit side effect failed', [
                'attendance_id' => $attendanceId,
                'stage' => $stage,
                'exception' => get_class($failure),
            ]);
        } catch (\Throwable) {
            // The attendance is already durable; preserve the success result.
        }
    }
}
