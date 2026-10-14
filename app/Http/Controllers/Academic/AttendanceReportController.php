<?php

namespace App\Http\Controllers\Academic;

use App\Helpers\ReportHelper;
use App\Http\Controllers\Controller;
use App\Services\Academic\AttendanceReportService;
use App\Services\Core\RoleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AttendanceReportController extends Controller
{
    public function __construct(
        private AttendanceReportService $reportService,
        private RoleService $roleService
    ) {}

    public function index(Request $request)
    {
        $roleName = $this->roleName($request);
        $classes = $this->reportService->availableClasses($request->user(), $roleName);
        $studentsByClass = $this->reportService->availableStudentsByClass($request->user(), $roleName, $classes);
        $report = null;

        if ($request->filled('report_type') || $request->filled('class_id')) {
            $filters = $this->validatedFilters($request);
            $report = $this->reportService->build($request->user(), $roleName, $filters);
        }

        return view('academic.attendance-reports.index', [
            'classes' => $classes,
            'studentsByClass' => $studentsByClass,
            'report' => $report,
            'defaults' => $this->defaults($request),
        ]);
    }

    public function pdf(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $report = $this->reportService->build($request->user(), $this->roleName($request), $filters);
        $filename = $this->filename($report);
        $pdf = Pdf::loadView('pdf.attendance_report', ['report' => $report])
            ->setPaper('A4', $report['is_individual'] || $report['report_type'] === 'harian' ? 'portrait' : 'landscape');
        $pdf->getDomPDF()->getOptions()->set([
            'defaultFont' => 'Helvetica',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
        ]);
        $pdf->getDomPDF()->addInfo('Title', $report['title']);
        $pdf->getDomPDF()->addInfo('Author', config('branding.application.name'));

        return $pdf->download($filename);
    }

    private function validatedFilters(Request $request): array
    {
        return Validator::make($request->all(), [
            'class_id' => ['required', 'string', 'max:100'],
            'student_id' => ['nullable', 'string', 'max:100'],
            'report_type' => ['required', 'string', 'in:harian,mingguan,bulanan,rentang'],
            'daily_date' => ['nullable', 'required_if:report_type,harian', 'date_format:Y-m-d'],
            'weekly_anchor' => ['nullable', 'required_if:report_type,mingguan', 'date_format:Y-m-d'],
            'month' => ['nullable', 'required_if:report_type,bulanan', 'integer', 'between:1,12'],
            'year' => ['nullable', 'required_if:report_type,bulanan', 'integer', 'between:2000,2100'],
            'date_start' => ['nullable', 'required_if:report_type,rentang', 'date_format:Y-m-d'],
            'date_end' => ['nullable', 'required_if:report_type,rentang', 'date_format:Y-m-d', 'after_or_equal:date_start'],
        ], [
            'class_id.required' => 'Kelas wajib dipilih.',
            'report_type.required' => 'Jenis rekap wajib dipilih.',
            'report_type.in' => 'Jenis rekap tidak valid.',
            'daily_date.required_if' => 'Tanggal harian wajib dipilih.',
            'weekly_anchor.required_if' => 'Tanggal acuan minggu wajib dipilih.',
            'month.required_if' => 'Bulan wajib dipilih.',
            'year.required_if' => 'Tahun wajib dipilih.',
            'date_start.required_if' => 'Tanggal mulai wajib dipilih.',
            'date_end.required_if' => 'Tanggal selesai wajib dipilih.',
            'date_end.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            '*.date_format' => 'Format tanggal harus YYYY-MM-DD.',
        ])->validate();
    }

    private function roleName(Request $request): string
    {
        $role = $this->roleService->getRoleById($request->user()->Role_ID ?? '');
        $roleName = strtoupper(trim((string) ($role['Role_Name'] ?? '')));
        if (! in_array($roleName, ['TEACHER', 'ADMINISTRATOR', 'MASTER'], true)) {
            abort(403, 'Anda tidak memiliki hak akses ke Laporan Absensi.');
        }

        return $roleName;
    }

    private function defaults(Request $request): array
    {
        $now = now('Asia/Jakarta');

        return [
            'report_type' => $request->input('report_type', 'harian'),
            'daily_date' => $request->input('daily_date', $now->toDateString()),
            'weekly_anchor' => $request->input('weekly_anchor', $now->toDateString()),
            'month' => (int) $request->input('month', $now->month),
            'year' => (int) $request->input('year', $now->year),
            'date_start' => $request->input('date_start', $now->toDateString()),
            'date_end' => $request->input('date_end', $now->toDateString()),
            'student_id' => $request->input('student_id', ''),
        ];
    }

    private function filename(array $report): string
    {
        $class = Str::slug($report['class']['name']) ?: 'kelas';
        $student = $report['student'] ? Str::slug($report['student']['name']) : null;
        $start = $report['period']['start']->format('d-m-Y');
        $end = $report['period']['end']->format('d-m-Y');

        $base = match ($report['report_type']) {
            'harian' => "absensi-{$class}-{$start}",
            'mingguan' => "absensi-mingguan-{$class}-{$start}-{$end}",
            'bulanan' => 'absensi-bulanan-'.$class.'-'.Str::slug($report['period']['label']),
            default => "absensi-{$class}-{$start}-{$end}",
        };
        if ($student) {
            $base = "rekap-absensi-siswa-{$student}-{$start}-{$end}";
        }

        return strtolower(ReportHelper::safeFilename($base)).'.pdf';
    }
}
