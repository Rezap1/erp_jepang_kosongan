<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Academic\StudentQRAttendanceService;
use App\Services\Attendance\AttendanceWindowService;
use App\Services\Core\ActivityLogService;
use App\Services\Core\PermanentQrService;
use App\Services\Core\RoleService;
use App\Services\Core\SystemSettingService;
use App\Services\HR\QRAttendanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PermanentQrController extends Controller
{
    protected $qrService;

    protected $studentQrService;

    protected $hrQrService;

    protected $activityLog;

    protected $settingService;

    public function __construct(
        PermanentQrService $qrService,
        StudentQRAttendanceService $studentQrService,
        QRAttendanceService $hrQrService,
        ActivityLogService $activityLog,
        SystemSettingService $settingService
    ) {
        $this->qrService = $qrService;
        $this->studentQrService = $studentQrService;
        $this->hrQrService = $hrQrService;
        $this->activityLog = $activityLog;
        $this->settingService = $settingService;
    }

    // ==========================================
    // MANAGEMENT UI METHODS
    // ==========================================

    public function index()
    {
        $roleAlias = $this->currentRoleAlias();
        $qrCodes = collect($this->qrService->getAllQrCodes())
            ->filter(function ($qr) use ($roleAlias) {
                return $this->canManageQrType($roleAlias, $qr['QR_TYPE'] ?? '');
            })
            ->values();

        $studentWindow = [
            'start' => $this->settingService->get(
                AttendanceWindowService::START_SETTING_KEY,
                AttendanceWindowService::DEFAULT_START
            ),
            'end' => $this->settingService->get(
                AttendanceWindowService::END_SETTING_KEY,
                AttendanceWindowService::DEFAULT_END
            ),
            'timezone' => AttendanceWindowService::TIMEZONE,
        ];

        return view('attendance.qr.index', compact('qrCodes', 'studentWindow'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'QR_TYPE' => 'required|in:STUDENT,EMPLOYEE',
            'LABEL' => 'required|string|max:100',
            'ACTIVE_FROM_DATE' => 'required|date_format:Y-m-d',
            'ACTIVE_FROM_TIME' => 'required|date_format:H:i',
            'ACTIVE_UNTIL_DATE' => 'required|date_format:Y-m-d',
            'ACTIVE_UNTIL_TIME' => 'required|date_format:H:i',
        ]);

        $data = $this->mapLifecycleInputs($data);

        $this->assertCanManageQrType($data['QR_TYPE']);

        try {
            $this->qrService->createQr($data);

            return redirect()->route('attendance.qr.index')->with('success', 'QR Presensi Permanen berhasil dibuat.');
        } catch (\Exception $e) {
            return redirect()->back()->with(
                'error',
                $this->safeExceptionMessage($e, 'QR Presensi Permanen tidak dapat dibuat.')
            )->withInput();
        }
    }

    public function preview($id)
    {
        $qr = $this->qrService->getQrById($id);
        if (! $qr) {
            return redirect()->route('attendance.qr.index')->with('error', 'QR tidak ditemukan.');
        }
        $this->assertCanManageQr($qr);

        return view('attendance.qr.preview', compact('qr'));
    }

    public function printView($id)
    {
        $qr = $this->qrService->getQrById($id);
        if (! $qr) {
            return redirect()->route('attendance.qr.index')->with('error', 'QR tidak ditemukan.');
        }
        $this->assertCanManageQr($qr);

        $this->activityLog->log(
            'ATTENDANCE_QR',
            'PRINT',
            "Mencetak QR Presensi Permanen: {$qr['IDENTIFIER']}",
            null,
            ['QR_ID' => $id, 'IDENTIFIER' => $qr['IDENTIFIER']]
        );

        return view('attendance.qr.print', compact('qr'));
    }

    public function downloadPdf($id)
    {
        // For simplicity, we just reuse the print layout and rely on browser print-to-PDF or
        // a simple wrapper if a PDF engine is used. Since EPS Rev.5.0 requires using existing PDF engine,
        // Let's use Barryvdh\DomPDF if available, otherwise just use Print view.
        // Assuming we can return the print view for now and user can print-to-pdf,
        // or actually generate PDF.
        $qr = $this->qrService->getQrById($id);
        if (! $qr) {
            return redirect()->route('attendance.qr.index')->with('error', 'QR tidak ditemukan.');
        }
        $this->assertCanManageQr($qr);

        $this->activityLog->log(
            'ATTENDANCE_QR',
            'DOWNLOAD_PDF',
            "Mengunduh PDF QR Presensi Permanen: {$qr['IDENTIFIER']}",
            null,
            ['QR_ID' => $id, 'IDENTIFIER' => $qr['IDENTIFIER']]
        );

        if (class_exists(Pdf::class)) {
            $isPdf = true;
            $pdf = Pdf::loadView('attendance.qr.print', compact('qr', 'isPdf'))
                ->setPaper('a4', 'portrait');

            return $pdf->download("QR_Presensi_{$qr['IDENTIFIER']}.pdf");
        }

        // Fallback to print view if PDF engine not found, though EPS demands using existing.
        // The existing engine is usually mpdf or dompdf.
        return view('attendance.qr.print', compact('qr'));
    }

    public function deactivate($id)
    {
        $qr = $this->qrService->getQrById($id);
        if (! $qr) {
            return redirect()->route('attendance.qr.index')->with('error', 'QR tidak ditemukan.');
        }
        $this->assertCanManageQr($qr);

        try {
            $this->qrService->deactivateQr($id);

            return redirect()->route('attendance.qr.index')->with('success', 'QR Presensi berhasil dinonaktifkan.');
        } catch (\Exception $e) {
            return redirect()->route('attendance.qr.index')->with(
                'error',
                $this->safeExceptionMessage($e, 'QR Presensi tidak dapat dinonaktifkan.')
            );
        }
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'STATUS' => 'required|in:ACTIVE,INACTIVE',
            'ACTIVE_FROM_DATE' => 'required|date_format:Y-m-d',
            'ACTIVE_FROM_TIME' => 'required|date_format:H:i',
            'ACTIVE_UNTIL_DATE' => 'required|date_format:Y-m-d',
            'ACTIVE_UNTIL_TIME' => 'required|date_format:H:i',
        ]);

        $data = $this->mapLifecycleInputs($data);

        $qr = $this->qrService->getQrById($id);
        if (! $qr) {
            return redirect()->route('attendance.qr.index')->with('error', 'QR tidak ditemukan.');
        }
        $this->assertCanManageQr($qr);

        try {
            $this->qrService->updateAvailability($id, $data);

            return redirect()->route('attendance.qr.index')->with('success', 'Jadwal aktif QR Presensi berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->route('attendance.qr.index')->with(
                'error',
                $this->safeExceptionMessage($e, 'Jadwal aktif QR Presensi tidak dapat diperbarui.')
            );
        }
    }

    public function updateStudentAttendanceSettings(Request $request)
    {
        $data = $request->validate([
            'WORK_START_TIME' => ['required', 'date_format:H:i'],
            'WORK_END_TIME' => ['required', 'date_format:H:i'],
        ]);

        [$prepared, $errors] = $this->settingService->prepareSettingsForUpdate($data);
        if ($errors !== []) {
            return redirect()->back()->withErrors(['attendance_settings' => implode(' ', $errors)])->withInput();
        }

        $actor = trim((string) (auth()->user()->User_ID ?? auth()->id() ?? ''));
        if ($actor === '') {
            abort(403, 'Identitas pengguna tidak valid.');
        }

        try {
            $this->settingService->set(
                AttendanceWindowService::START_SETTING_KEY,
                $prepared['WORK_START_TIME'],
                $actor
            );
            $this->settingService->set(
                AttendanceWindowService::END_SETTING_KEY,
                $prepared['WORK_END_TIME'],
                $actor
            );
            $this->settingService->reloadCache();
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->back()->withErrors([
                'attendance_settings' => 'Pengaturan jam absensi siswa tidak dapat disimpan.',
            ])->withInput();
        }

        return redirect()->route('attendance.qr.index')
            ->with('success', 'Pengaturan jam absensi siswa berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $qr = $this->qrService->getQrById($id);
        if (! $qr) {
            return redirect()->route('attendance.qr.index')->with('error', 'QR tidak ditemukan.');
        }
        $this->assertCanManageQr($qr);

        try {
            $this->qrService->deleteQr($id);

            return redirect()->route('attendance.qr.index')->with('success', 'QR Presensi berhasil dihapus permanen.');
        } catch (\Exception $e) {
            return redirect()->route('attendance.qr.index')->with(
                'error',
                $this->safeExceptionMessage($e, 'QR Presensi tidak dapat dihapus.')
            );
        }
    }

    // ==========================================
    // SCANNING ENTRY METHODS
    // ==========================================

    public function scanEntry($type, $identifier)
    {
        $qr = $this->qrService->getQrByIdentifier($identifier);

        if (! $qr || strtoupper($qr['QR_TYPE']) !== strtoupper($type)) {
            return view('attendance.qr.permanent_scanner', [
                'error' => 'QR Code tidak valid atau tidak sesuai.',
                'qr' => null,
            ]);
        }

        $availability = $this->qrService->getAvailabilityStatus($qr);
        if (! $availability['usable']) {
            return view('attendance.qr.permanent_scanner', [
                'error' => $availability['message'],
                'qr' => $qr,
            ]);
        }

        $user = auth()->user();
        if (! $user) {
            // Should be handled by middleware, but just in case
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu untuk melakukan presensi.');
        }

        // Cross QR Protection
        // If type is STUDENT, user must be a student
        if (strtoupper($type) === 'STUDENT') {
            $studentRepo = app(StudentRepositoryInterface::class);
            $student = collect($studentRepo->fetchAll())->firstWhere('User_ID', $user->User_ID);
            if (! $student) {
                return view('attendance.qr.permanent_scanner', [
                    'error' => 'QR Code ini khusus untuk Presensi Siswa. Anda bukan siswa.',
                    'qr' => $qr,
                ]);
            }
        } elseif (strtoupper($type) === 'EMPLOYEE') {
            $employeeRepo = app(EmployeeRepositoryInterface::class);
            $employee = collect($employeeRepo->fetchAll())->firstWhere('User_ID', $user->User_ID);
            if (! $employee) {
                return view('attendance.qr.permanent_scanner', [
                    'error' => 'QR Code ini khusus untuk Presensi Pegawai. Anda bukan pegawai.',
                    'qr' => $qr,
                ]);
            }
        }

        return view('attendance.qr.permanent_scanner', [
            'error' => null,
            'qr' => $qr,
            'type' => strtoupper($type),
            'identifier' => $identifier,
        ]);
    }

    public function scanVerify(Request $request, $type, $identifier)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lon' => 'required|numeric',
        ]);

        $lat = (float) $request->lat;
        $lon = (float) $request->lon;
        $deviceInfo = $request->header('User-Agent');

        $qr = $this->qrService->getQrByIdentifier($identifier);

        if (! $qr || strtoupper($qr['QR_TYPE']) !== strtoupper($type)) {
            return response()->json(['status' => 'error', 'message' => 'QR tidak valid atau tidak aktif.']);
        }

        $availability = $this->qrService->getAvailabilityStatus($qr);
        if (! $availability['usable']) {
            return response()->json(['status' => 'error', 'message' => $availability['message']]);
        }

        try {
            $typeUpper = strtoupper($type);

            // Check Geofence Coordinates
            // In existing logic, the geofence might be checked during processStudentScan/processScan.
            // But we must also check it or let them check it.
            // The EPS says: "Generate dynamic H8.22 token -> Invoke EXISTING H8.22 attendance processing"

            if ($typeUpper === 'STUDENT') {
                $result = $this->studentQrService->processStudentScan($identifier, $lat, $lon, $deviceInfo);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Presensi siswa berhasil dicatat sebagai hadir.',
                    'distance' => $result['distance_meters'] ?? null,
                    'data' => $result,
                ]);

            } else {
                $result = $this->hrQrService->processScan($identifier, $deviceInfo, $lat, $lon);

                return response()->json([
                    'status' => 'success',
                    'message' => $result['status'] === 'PRESENT' ? 'Presensi pegawai berhasil dicatat.' : 'Presensi pegawai berhasil dicatat sebagai terlambat.',
                    'distance' => $result['distance_meters'] ?? null,
                    'data' => $result,
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $this->safeExceptionMessage($e, 'Presensi QR tidak dapat diproses.'),
            ], 422);
        }
    }

    private function assertCanManageQr(array $qr): void
    {
        $this->assertCanManageQrType($qr['QR_TYPE'] ?? '');
    }

    private function assertCanManageQrType(string $qrType): void
    {
        if (! $this->canManageQrType($this->currentRoleAlias(), $qrType)) {
            abort(403, 'Anda tidak memiliki akses mengelola QR presensi tipe ini.');
        }
    }

    private function canManageQrType(string $roleAlias, string $qrType): bool
    {
        $type = strtoupper(trim($qrType));

        return $roleAlias === 'ADMINISTRATOR'
            && in_array($type, ['STUDENT', 'EMPLOYEE'], true);
    }

    private function currentRoleAlias(): string
    {
        $user = auth()->user();
        if (! $user || ! isset($user->Role_ID)) {
            return '';
        }

        $roleService = app(RoleService::class);
        $role = $roleService->getRoleById($user->Role_ID);
        $roleName = strtolower(trim($role['Role_Name'] ?? ''));

        if (str_contains($roleName, 'admin') || str_contains($roleName, 'master')) {
            return 'ADMINISTRATOR';
        }
        if (str_contains($roleName, 'hr')) {
            return 'HR';
        }
        if (str_contains($roleName, 'academic')) {
            return 'ACADEMIC';
        }

        return '';
    }

    private function mapLifecycleInputs(array $data): array
    {
        $data['ACTIVE_FROM'] = $data['ACTIVE_FROM_DATE'].' '.$data['ACTIVE_FROM_TIME'].':00';
        $data['ACTIVE_UNTIL'] = $data['ACTIVE_UNTIL_DATE'].' '.$data['ACTIVE_UNTIL_TIME'].':00';

        unset(
            $data['ACTIVE_FROM_DATE'],
            $data['ACTIVE_FROM_TIME'],
            $data['ACTIVE_UNTIL_DATE'],
            $data['ACTIVE_UNTIL_TIME']
        );

        return $data;
    }
}
