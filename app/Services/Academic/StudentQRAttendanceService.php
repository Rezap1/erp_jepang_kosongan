<?php

namespace App\Services\Academic;

use App\Exceptions\DuplicatePrimaryKeyException;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Repositories\MySql\BaseMySqlRepository;
use App\Services\Attendance\AttendanceWindowException;
use App\Services\Attendance\AttendanceWindowService;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\PermanentQrService;
use App\Services\Core\RoleService;
use App\Services\Core\SystemSettingService;
use App\Support\CoordinateNormalizer;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StudentQRAttendanceService
{
    protected $attendanceRepository;

    protected $studentRepository;

    protected $settingService;

    protected $enterpriseEvent;

    protected AttendanceWindowService $attendanceWindow;

    public function __construct(
        AttendanceRepositoryInterface $attendanceRepository,
        StudentRepositoryInterface $studentRepository,
        SystemSettingService $settingService,
        EnterpriseEventService $enterpriseEvent,
        ?AttendanceWindowService $attendanceWindow = null
    ) {
        $this->attendanceRepository = $attendanceRepository;
        $this->studentRepository = $studentRepository;
        $this->settingService = $settingService;
        $this->enterpriseEvent = $enterpriseEvent;
        $this->attendanceWindow = $attendanceWindow ?: new AttendanceWindowService($settingService);
    }

    /**
     * Calculate Great-Circle distance using Haversine formula (in meters)
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // Earth radius in meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    /**
     * Create or retrieve active Student QR Session
     */
    public function getOrCreateActiveStudentSession(): array
    {
        $window = $this->attendanceWindow->resolve();
        $today = $window['now']->toDateString();
        $sessionId = "STUDENT-QRS-{$today}";

        $session = Cache::get("student_qr_session_{$sessionId}");
        if (! $session) {
            $session = [
                'Session_ID' => $sessionId,
                'Title' => 'Presensi Kehadiran Siswa LPK',
                'Type' => 'STUDENT',
                'Date' => $today,
                'Start_Time' => $window['start_label'],
                'End_Time' => $window['end_label'],
                'Status' => 'ACTIVE',
                'Created_At' => $window['now']->toDateTimeString(),
            ];
            Cache::forever("student_qr_session_{$sessionId}", $session);
        } else {
            // This automatic daily session follows the canonical attendance window.
            // It is not a manually scheduled session and can safely be refreshed.
            $session['Start_Time'] = $window['start_label'];
            $session['End_Time'] = $window['end_label'];
            unset($session['Grace_Period']);
            Cache::forever("student_qr_session_{$sessionId}", $session);
        }

        return $session;
    }

    /**
     * Generate dynamic QR token for Student QR
     */
    public function generateStudentDynamicToken(): array
    {
        $window = $this->attendanceWindow->resolve();
        $this->attendanceWindow->assertOpen($window);
        $session = $this->getOrCreateActiveStudentSession();
        if (! $this->isStudentSessionActive($session, $window['now']->toDateString())) {
            throw new Exception($this->studentSessionClosedMessage($session));
        }

        $sessionId = $session['Session_ID'];

        $ttlSeconds = (int) $this->settingService->get('QR_TOKEN_TTL_SECONDS', 25);
        $expiresAt = $window['now']->addSeconds($ttlSeconds)->timestamp;
        $nonce = Str::random(16);
        $qrType = 'STUDENT';

        $signature = hash_hmac('sha256', "{$qrType}|{$sessionId}|{$expiresAt}|{$nonce}", $this->signingKey());

        $payload = [
            'qr_type' => $qrType,
            'session_id' => $sessionId,
            'expires_at' => $expiresAt,
            'nonce' => $nonce,
            'sig' => $signature,
        ];

        $tokenString = base64_encode(json_encode($payload));
        Cache::put("qr_student_nonce_{$nonce}", $sessionId, $ttlSeconds + 35);

        return [
            'token' => $tokenString,
            'expires_in' => $ttlSeconds,
            'session' => $session,
        ];
    }

    /**
     * Process Scan for Student Attendance
     */
    public function processStudentScan(string $tokenString, float $userLat, float $userLon, ?string $deviceInfo = null): array
    {
        // Identity, configuration, and authoritative server time are resolved
        // before any token, GPS, duplicate, or persistence work.
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

        if ($roleName !== 'STUDENT' && ! str_contains($roleName, 'STUDENT')) {
            throw new Exception('Akses Ditolak: Fitur ini khusus untuk akun Siswa.');
        }

        $student = $this->resolveStudentForUser($user);
        if (! $student) {
            throw new Exception('Akun Anda tidak dapat melakukan absensi. Profil siswa tidak ditemukan atau tidak aktif.');
        }

        try {
            $window = $this->attendanceWindow->resolve();
            $this->attendanceWindow->assertOpen($window);
        } catch (AttendanceWindowException $e) {
            $this->logRejectedAttempt($e->reasonCode, $user, $student, 'TIME_WINDOW');
            throw $e;
        }

        if (! is_finite($userLat) || ! is_finite($userLon)
            || $userLat < -90 || $userLat > 90 || $userLon < -180 || $userLon > 180) {
            throw new Exception('GPS wajib aktif dan koordinat lokasi harus valid untuk presensi QR siswa.');
        }

        // 1. Decode Payload & Identify QR Type
        if ($this->extractPermanentQrIdentifier($tokenString, 'EMP')) {
            throw new Exception('Akses Ditolak: QR Code ini khusus untuk Presensi Pegawai.');
        }

        $permanentIdentifier = $this->extractPermanentQrIdentifier($tokenString, 'STU');
        if ($permanentIdentifier !== null) {
            // Support for Permanent QR Code (URL-based) scanned from inside the Student App
            $permanentQrService = app(PermanentQrService::class);
            $qr = $permanentQrService->getQrByIdentifier($permanentIdentifier);

            if (! $qr || strtoupper(trim((string) ($qr['QR_TYPE'] ?? ''))) !== 'STUDENT' || strtoupper(trim((string) ($qr['STATUS'] ?? ''))) !== 'ACTIVE') {
                throw new Exception('QR Code Permanen tidak valid atau sudah tidak aktif.');
            }

            $availability = $permanentQrService->getAvailabilityStatus($qr);
            if (! $availability['usable']) {
                throw new Exception($availability['message']);
            }

            // Permanent Admin QR validity is controlled by MASTER_PERMANENT_QR.
            // Do not make it depend on the short-lived dynamic classroom session.
            $qrType = 'STUDENT';
            $session = $this->permanentStudentSessionContext();
            $sessionId = $session['Session_ID'];
            $nonce = 'PERM-'.Str::random(10);

        } else {
            // Support for Dynamic QR Code (Base64 JSON)
            $decodedJson = base64_decode($tokenString, true);
            if (! $decodedJson) {
                throw new Exception('QR Code tidak valid atau sudah kedaluwarsa.');
            }

            $payload = json_decode($decodedJson, true);
            if (! is_array($payload) || empty($payload['session_id']) || empty($payload['expires_at']) || empty($payload['nonce']) || empty($payload['sig'])) {
                throw new Exception('Format data QR Code tidak valid.');
            }

            $qrType = $payload['qr_type'] ?? 'STUDENT';
            $sessionId = $payload['session_id'];
            $expiresAt = (int) $payload['expires_at'];
            $nonce = $payload['nonce'];
            $sig = $payload['sig'];

            // 2. Strict QR Type Check
            if (strtoupper($qrType) !== 'STUDENT') {
                throw new Exception('Akses Ditolak: QR Code ini khusus untuk Presensi Pegawai.');
            }

            // 3. Expiration Check
            if ($expiresAt < $window['now']->timestamp) {
                throw new Exception('QR Code telah kedaluwarsa. Silakan pindai ulang QR Code terbaru pada layar.');
            }

            // 4. Single-use Nonce Replay Check
            if (! Cache::has("qr_student_nonce_{$nonce}")) {
                throw new Exception('QR Code telah digunakan atau kedaluwarsa. Silakan pindai QR terbaru.');
            }

            // 5. Cryptographic Signature Verification
            $expectedSig = hash_hmac('sha256', "{$qrType}|{$sessionId}|{$expiresAt}|{$nonce}", $this->signingKey());
            if (! hash_equals($expectedSig, $sig)) {
                throw new Exception('Tanda tangan digital QR Code tidak valid atau telah dimanipulasi.');
            }

            $session = Cache::get("student_qr_session_{$sessionId}");
            if (! $this->isStudentSessionActive($session, $window['now']->toDateString())) {
                throw new Exception($this->studentSessionClosedMessage($session ?? null));
            }

            // NOTE: We DO NOT consume the nonce via Cache::forget here.
            // Classroom attendance requires multiple students to scan the same QR code displayed on the projector
            // within its 25-second TTL. Replay attacks are mitigated by the short TTL, Geofence, and Duplicate check.
        }

        // 6. Geofence Location Verification (Server-Side Haversine)
        $lpkLat = $this->parseRequiredCoordinate($this->settingService->get('LPK_LATITUDE', null), -90, 90, 'latitude');
        $lpkLon = $this->parseRequiredCoordinate($this->settingService->get('LPK_LONGITUDE', null), -180, 180, 'longitude');

        $distance = $this->calculateDistance($userLat, $userLon, $lpkLat, $lpkLon);

        $maxRadius = $this->parseRequiredRadius(
            $this->settingService->get('LPK_ALLOWED_RADIUS_METERS', null)
        );
        if ($distance > $maxRadius) {
            throw new Exception("Anda berada di luar area LPK. Jarak Anda: {$distance} meter. Maksimal jarak yang diizinkan: {$maxRadius} meter.");
        }

        // 7. Student eligibility is server-resolved; request Student_ID is ignored.
        if (strtoupper(trim($student['Is_Active'] ?? 'TRUE')) === 'FALSE') {
            throw new Exception('Akun Anda tidak dapat melakukan absensi. Profil siswa tidak ditemukan atau tidak aktif.');
        }

        // 8. Strict Student Status Rules
        $enrollmentStatus = strtoupper(trim($student['Enrollment_Status'] ?? ''));
        $graduationStatus = strtoupper(trim($student['Graduation_Status'] ?? ''));
        $batchId = trim((string) ($student['Batch_ID'] ?? ''));

        if ($graduationStatus === 'LULUS' || str_contains($enrollmentStatus, 'ALUMNI')) {
            throw new Exception('Absensi Gagal: Siswa berstatus Lulus / Alumni tidak diperkenankan presensi harian.');
        }

        if (str_contains($enrollmentStatus, 'CUTI') || str_contains($enrollmentStatus, 'DROPOUT') || str_contains($enrollmentStatus, 'NON-AKTIF') || str_contains($enrollmentStatus, 'OUT')) {
            throw new Exception("Absensi Gagal: Status pendaftaran siswa saat ini ({$student['Enrollment_Status']}) tidak aktif.");
        }

        if (empty($batchId)) {
            throw new Exception('Absensi Gagal: Batch siswa belum dikonfigurasi pada sistem.');
        }

        $studentId = trim((string) ($student['Student_ID'] ?? ''));
        if ($studentId === '') {
            throw new Exception('Akun Anda tidak dapat melakukan absensi. Profil siswa tidak ditemukan atau tidak aktif.');
        }

        $classId = trim((string) ($student['Class_ID'] ?? ''));

        if ($classId === '' || $classId === '-') {
            throw new Exception('Absensi Gagal: Kelas siswa belum dikonfigurasi pada sistem.');
        }

        // 9. Atomic Concurrency Locking & Duplicate Check
        $lockKey = "student_qr_scan_{$sessionId}_{$studentId}_{$classId}_CLASS_QR";

        return Cache::lock($lockKey, 10)->block(3, function () use ($student, $studentId, $batchId, $classId, $user, $distance, $deviceInfo, $window) {
            return $this->runtimeTransaction(function () use ($student, $studentId, $batchId, $classId, $user, $distance, $deviceInfo, $window): array {
                // Check Duplicate Attendance for this Student today
                $todayStr = $window['now']->toDateString();
                $allAttendances = collect($this->attendanceRepository->fetchAll());
                $existing = $allAttendances->first(function ($att) use ($studentId, $classId, $todayStr) {
                    return ($att['Student_ID'] ?? '') === $studentId &&
                           trim((string) ($att['Class_ID'] ?? '')) === $classId &&
                           strtoupper(trim((string) ($att['Attendance_Type'] ?? ''))) === 'CLASS_QR' &&
                           ($att['Attendance_Date'] ?? '') === $todayStr &&
                           strtoupper(trim($att['Is_Active'] ?? 'TRUE')) !== 'FALSE';
                });

                if ($existing) {
                    throw new Exception('Absensi gagal: Anda sudah melakukan presensi hari ini.');
                }

                $statusDecision = $this->attendanceWindow->resolveStudentStatus($window);
                $status = $statusDecision['status'];
                $lateMinutes = $statusDecision['late_minutes'];
                $nowCarbon = $window['now'];

                // 10. Master Attendance Persistence
                $attendanceId = $this->deterministicAttendanceId($studentId, $todayStr, $classId);
                $record = [
                    'Attendance_ID' => $attendanceId,
                    'User_ID' => $user->User_ID,
                    'Student_ID' => $studentId,
                    'Batch_ID' => $batchId,
                    'Class_ID' => $classId,
                    'Schedule_ID' => '',
                    'Attendance_Type' => 'CLASS_QR',
                    'Attendance_Date' => $todayStr,
                    'Check_In_Time' => $nowCarbon->format('H:i:s'),
                    'Status' => $status,
                    'Late_Minutes' => $lateMinutes,
                    'Verification_Method' => 'STUDENT_GEO_QR',
                    'Device_Info' => ($deviceInfo ?? request()->header('User-Agent', 'Mobile Browser'))." [Dist: {$distance}m]",
                    'Is_Active' => 'TRUE',
                    'Created_At' => $nowCarbon->toDateTimeString(),
                    'Updated_At' => $nowCarbon->toDateTimeString(),
                    'Created_By' => $user->User_ID,
                ];

                try {
                    $created = $this->attendanceRepository->create($record);
                } catch (DuplicatePrimaryKeyException $e) {
                    throw new Exception('Absensi gagal: Anda sudah melakukan presensi hari ini.', 0, $e);
                }
                if ($created === false || $created === null) {
                    throw new Exception('Presensi siswa gagal disimpan ke penyimpanan.');
                }

                // Notifications are post-commit side effects. Their failure must
                // never turn a durable attendance write into a rejected response.
                try {
                    $this->enterpriseEvent->dispatch(
                        'ACADEMIC',
                        'CREATE',
                        'STUDENT_ATTENDANCE',
                        $attendanceId,
                        $user->User_ID,
                        ['STUDENT', 'TEACHER', 'ACADEMIC'],
                        [$studentId],
                        [
                            'Student_Name' => $student['Full_Name'] ?? $studentId,
                            'Batch_ID' => $batchId,
                            'Status' => $status,
                            'Distance_Meters' => $distance,
                        ]
                    );
                } catch (\Throwable $eventFailure) {
                    $this->logPostCommitFailure($attendanceId, $eventFailure);
                }

                return [
                    'attendance_id' => $attendanceId,
                    'student_name' => $student['Full_Name'] ?? $studentId,
                    'action' => 'CHECK_IN',
                    'status' => $status,
                    'late_minutes' => $lateMinutes,
                    'check_in_time' => $nowCarbon->format('H:i:s'),
                    'check_out_time' => null,
                    'distance_meters' => $distance,
                ];
            }, [[$this->studentRepository, $studentId]]);
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

    private function isStudentSessionActive(?array $session, string $serverDate): bool
    {
        if (! $session || strtoupper(trim((string) ($session['Status'] ?? ''))) !== 'ACTIVE') {
            return false;
        }

        return trim((string) ($session['Date'] ?? '')) === $serverDate;
    }

    /**
     * Resolve only the authenticated account's exact User_ID.  A missing
     * mapping is a hard failure; never substitute a first/nearest student.
     */
    private function resolveStudentForUser($user): ?array
    {
        $userId = trim((string) ($user->User_ID ?? ''));
        if ($userId === '') {
            return null;
        }

        foreach ($this->studentRepository->fetchAll() as $candidate) {
            if (strcasecmp(trim((string) ($candidate['User_ID'] ?? '')), $userId) === 0) {
                return $candidate;
            }
        }

        return null;
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

    private function permanentStudentSessionContext(): array
    {
        $window = $this->attendanceWindow->resolve();
        $today = $window['now']->toDateString();

        return [
            'Session_ID' => "STUDENT-QRS-{$today}",
            'Title' => 'Presensi Kehadiran Siswa LPK',
            'Type' => 'STUDENT',
            'Date' => $today,
            'Start_Time' => $window['start_label'],
            'End_Time' => $window['end_label'],
            'Status' => 'ACTIVE',
            'Created_At' => $window['now']->toDateTimeString(),
        ];
    }

    private function signingKey(): string
    {
        $key = trim((string) config('app.key'));
        if ($key === '') {
            throw new \RuntimeException('Kunci aplikasi untuk penandatanganan QR belum dikonfigurasi.');
        }

        return $key;
    }

    private function studentSessionClosedMessage(?array $session): string
    {
        if (! $session) {
            return 'Sesi presensi siswa belum dibuka oleh Academic.';
        }

        return 'Sesi presensi siswa belum aktif atau sudah ditutup. Jadwal aktif: '
            .($session['Start_Time'] ?? 'belum dikonfigurasi').' - '
            .($session['End_Time'] ?? 'belum dikonfigurasi').' WIB.';
    }

    private function deterministicAttendanceId(string $studentId, string $date, string $classId): string
    {
        return 'ATT-SQR-'.strtoupper(substr(hash('sha256', "{$studentId}|{$date}|{$classId}|CLASS_QR"), 0, 20));
    }

    private function logRejectedAttempt(string $reasonCode, object $user, array $student, string $stage): void
    {
        try {
            Log::warning('Student attendance rejected', [
                'reason_code' => $reasonCode,
                'validation_stage' => $stage,
                'user_id' => $user->User_ID ?? null,
                'student_id' => $student['Student_ID'] ?? null,
                'server_timestamp' => $this->attendanceWindow->serverNow()->toIso8601String(),
            ]);
        } catch (\Throwable) {
            // Logging must never change attendance validation semantics.
        }
    }

    private function logPostCommitFailure(string $attendanceId, \Throwable $failure): void
    {
        try {
            Log::error('Student attendance post-commit event failed', [
                'attendance_id' => $attendanceId,
                'exception' => get_class($failure),
            ]);
        } catch (\Throwable) {
            // The attendance is already durable; preserve the success result.
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
}
