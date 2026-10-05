<?php

namespace App\Http\Controllers\Academic;

use App\Helpers\CollectionHelper;
use App\Helpers\SheetValue;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAlumniRequest;
use App\Http\Requests\UpdateAlumniRequest;
use App\Interfaces\GoogleSheets\DocumentRepositoryInterface;
use App\Interfaces\GoogleSheets\ScoreRepositoryInterface;
use App\Services\Core\AlumniDirectoryService;
use App\Services\Core\AlumniService;
use App\Services\Core\BatchService;
use App\Services\Core\ClassService;
use App\Services\Core\ProgramService;
use App\Services\Core\StudentService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class AlumniController extends Controller
{
    protected $alumniService;

    protected $studentService;

    protected $programService;

    protected $batchService;

    protected $classService;

    protected $alumniDirectoryService;

    public function __construct(
        AlumniService $alumniService,
        StudentService $studentService,
        ProgramService $programService,
        BatchService $batchService,
        ClassService $classService,
        AlumniDirectoryService $alumniDirectoryService
    ) {
        $this->alumniService = $alumniService;
        $this->studentService = $studentService;
        $this->programService = $programService;
        $this->batchService = $batchService;
        $this->classService = $classService;
        $this->alumniDirectoryService = $alumniDirectoryService;
    }

    public function index(Request $request)
    {
        $schemaError = null;
        try {
            $directory = $this->alumniDirectoryService->directory($request->only([
                'search',
                'program',
                'batch',
                'class',
                'source',
                'pathway',
                'visa_type',
                'region',
                'departure_year',
                'status',
            ]));
        } catch (Throwable $e) {
            $schemaError = $this->safeExceptionMessage(
                $e,
                'Registry Alumni belum dapat dibaca. Pastikan tabel MASTER_ALUMNI dan kolom wajib sudah tersedia.'
            );
            Log::warning('Alumni registry unavailable', ['exception' => get_class($e)]);
            $directory = [
                'alumni' => CollectionHelper::paginate(collect(), 15)->withQueryString(),
                'analytics' => $this->alumniDirectoryService->analytics(collect()),
                'programs' => collect(),
                'batches' => collect(),
                'classes' => collect(),
                'visaTypes' => collect(),
                'regions' => collect(),
            ];
        }

        return view('academic.alumni.index', [
            ...$directory,
            'totalAlumni' => $directory['analytics']['total'],
            'schemaError' => $schemaError,
        ]);
    }

    public function exportCsv(Request $request)
    {
        try {
            $rows = $this->alumniDirectoryService->filteredRows($request->only([
                'search',
                'program',
                'batch',
                'class',
                'source',
                'pathway',
                'visa_type',
                'region',
                'departure_year',
                'status',
            ]), 'all');
        } catch (Throwable $e) {
            return back()->with(
                'error',
                $this->safeExceptionMessage($e, 'Export Alumni tidak dapat dibuat sebelum schema MASTER_ALUMNI tersedia.')
            );
        }

        $filename = 'alumni-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Nama Alumni',
                'NIK',
                'Nama Orang Tua',
                'Alamat Lengkap Indonesia',
                'Nomor Visa',
                'Kota di Jepang',
                'Tanggal Keberangkatan',
                'Sumber Alumni',
                'Status',
                'Jenis Visa',
                'Jalur',
                'Wilayah Kerja',
            ]);
            foreach ($rows as $row) {
                $isLegacy = ! empty($row['Is_Legacy']);
                fputcsv($handle, [
                    $row['Full_Name'] ?? '',
                    $row['NIK'] ?? '',
                    $row['Parent_Name'] ?? '',
                    $row['Indonesia_Address'] ?? '',
                    $row['Visa_Number'] ?? '',
                    $row['Japan_City'] ?? '',
                    $row['Departure_Date'] ?? '',
                    $isLegacy ? 'Legacy WMS' : (strtoupper((string) ($row['Source_Type'] ?? '')) === 'WMS' ? 'Dari Siswa WMS' : 'Input Manual'),
                    $isLegacy ? 'Legacy' : (SheetValue::isActive((array) $row) ? 'Aktif' : 'Nonaktif'),
                    $row['Visa_Type'] ?? '',
                    $row['Pathway_Label'] ?? '',
                    $row['Work_Region'] ?? '',
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create(Request $request)
    {
        $schemaError = null;
        try {
            $students = $this->alumniService->eligibleStudents();
        } catch (Throwable $e) {
            $students = collect();
            $schemaError = $this->safeExceptionMessage(
                $e,
                'Form Alumni belum dapat digunakan. Pastikan sheet MASTER_ALUMNI dan header wajib sudah disediakan.'
            );
            Log::warning('Alumni create form unavailable', ['exception' => get_class($e)]);
        }
        $programs = $this->safeCollection(fn () => $this->programService->getAllPrograms());
        $batches = $this->safeCollection(fn () => $this->batchService->getAllBatches());
        $classes = $this->safeCollection(fn () => $this->classService->getAllClasses());
        $students = $students->map(function ($student) use ($programs, $batches, $classes) {
            $student = (array) $student;
            $student['Program_Name'] = data_get($programs->firstWhere('Program_ID', $student['Program_ID'] ?? ''), 'Program_Name', '-');
            $student['Batch_Name'] = data_get($batches->firstWhere('Batch_ID', $student['Batch_ID'] ?? ''), 'Batch_Name', '-');
            $student['Class_Name'] = data_get($classes->firstWhere('Class_ID', $student['Class_ID'] ?? ''), 'Class_Name', '-');

            return $student;
        });

        return view('academic.alumni.create', [
            'students' => $students,
            'selectedStudentId' => $request->query('student_id'),
            'schemaError' => $schemaError,
        ]);
    }

    public function store(StoreAlumniRequest $request)
    {
        try {
            $data = $request->validated();
            if ($request->hasFile('Photo')) {
                $data['Photo'] = $request->file('Photo');
            }

            $this->alumniService->create(
                $data,
                $request->header('X-Idempotency-Key')
            );

            return redirect()->route('alumni.index')->with('success', 'Data Alumni berhasil didaftarkan.');
        } catch (Throwable $e) {
            Log::error('Alumni create failed', ['exception' => get_class($e)]);

            return back()
                ->with('error', $this->safeExceptionMessage($e, 'Data Alumni gagal disimpan.'))
                ->withInput();
        }
    }

    public function show(string $id)
    {
        try {
            $alumni = str_starts_with(strtoupper(trim($id)), 'LEGACY-')
                ? $this->alumniService->legacyById($id)
                : $this->alumniService->getById($id, false);
            if (! $alumni) {
                return redirect()->route('alumni.index')->with('error', 'Data Alumni tidak ditemukan.');
            }

            $student = null;
            $studentId = trim((string) ($alumni['Student_ID'] ?? ''));
            if ($studentId !== '') {
                $student = $this->studentService->getStudentById($studentId);
            }

            $programId = $alumni['Program_ID'] ?? ($student['Program_ID'] ?? '');
            $batchId = $alumni['Batch_ID'] ?? ($student['Batch_ID'] ?? '');
            $classId = $alumni['Class_ID'] ?? ($student['Class_ID'] ?? '');
            $program = $this->programService->getProgramById($programId);
            $batch = $this->batchService->getBatchById($batchId);
            $class = $this->classService->getClassById($classId);

            $display = array_merge((array) ($student ?: []), $alumni);
            $display['Program_Name'] = $program['Program_Name'] ?? '-';
            $display['Batch_Name'] = $batch['Batch_Name'] ?? '-';
            $display['Class_Name'] = $class['Class_Name'] ?? '-';
            $display['Source_Label'] = ! empty($alumni['Is_Legacy'])
                ? 'Legacy WMS'
                : (strtoupper((string) ($alumni['Source_Type'] ?? '')) === 'WMS'
                    ? 'Dari Siswa WMS'
                    : 'Input Manual');
            $display['Photo_URL'] = $alumni['Photo_URL'] ?? null;
            $display = $this->alumniDirectoryService->decorateProfile($display);

            $scores = [];
            $documents = [];
            if ($studentId !== '') {
                try {
                    $scoreRepo = app(ScoreRepositoryInterface::class);
                    $scores = collect($scoreRepo->fetchAll())
                        ->where('Student_ID', $studentId)
                        ->values()
                        ->toArray();
                } catch (Throwable $e) {
                    Log::notice('Alumni score history unavailable', ['student_id' => $studentId]);
                }

                try {
                    $docRepo = app(DocumentRepositoryInterface::class);
                    $documents = collect($docRepo->fetchAll())
                        ->where('Entity_ID', $studentId)
                        ->values()
                        ->toArray();
                } catch (Throwable $e) {
                    Log::notice('Alumni documents unavailable', ['student_id' => $studentId]);
                }
            }

            return view('academic.alumni.show', [
                'alumni' => $alumni,
                'student' => $display,
                'scores' => $scores,
                'documents' => $documents,
            ]);
        } catch (Throwable $e) {
            Log::error('Alumni show failed', ['exception' => get_class($e)]);

            return redirect()->route('alumni.index')->with(
                'error',
                $this->safeExceptionMessage($e, 'Detail Alumni tidak dapat dimuat.')
            );
        }
    }

    public function edit(string $id)
    {
        try {
            $alumni = str_starts_with(strtoupper(trim($id)), 'LEGACY-')
                ? $this->alumniService->legacyById($id)
                : $this->alumniService->getById($id, false);
            if (! $alumni) {
                return redirect()->route('alumni.index')->with('error', 'Data Alumni tidak ditemukan.');
            }
            if (! empty($alumni['Is_Legacy'])) {
                return redirect()->route('alumni.show', $id)->with(
                    'error',
                    'Data Alumni legacy bersifat read-only sampai registry MASTER_ALUMNI tersedia.'
                );
            }

            $student = null;
            if (! empty($alumni['Student_ID'])) {
                $student = $this->studentService->getStudentById((string) $alumni['Student_ID']);
            }

            return view('academic.alumni.edit', [
                'alumni' => $alumni,
                'student' => $student,
            ]);
        } catch (Throwable $e) {
            return redirect()->route('alumni.index')->with(
                'error',
                $this->safeExceptionMessage($e, 'Form edit Alumni tidak dapat dimuat.')
            );
        }
    }

    public function update(UpdateAlumniRequest $request, string $id)
    {
        try {
            $data = $request->validated();
            if ($request->hasFile('Photo')) {
                $data['Photo'] = $request->file('Photo');
            }

            $this->alumniService->update($id, $data);

            return redirect()->route('alumni.show', $id)->with('success', 'Data Alumni berhasil diperbarui.');
        } catch (Throwable $e) {
            Log::error('Alumni update failed', ['alumni_id' => $id, 'exception' => get_class($e)]);

            return back()
                ->with('error', $this->safeExceptionMessage($e, 'Data Alumni gagal diperbarui.'))
                ->withInput();
        }
    }

    public function destroy(string $id)
    {
        try {
            $this->alumniService->deactivate($id);

            return redirect()->route('alumni.index')->with('success', 'Data Alumni berhasil dinonaktifkan.');
        } catch (Throwable $e) {
            Log::error('Alumni deactivate failed', ['alumni_id' => $id, 'exception' => get_class($e)]);

            return redirect()->route('alumni.index')->with(
                'error',
                $this->safeExceptionMessage($e, 'Data Alumni gagal dinonaktifkan.')
            );
        }
    }

    protected function safeCollection(callable $resolver): Collection
    {
        try {
            return collect($resolver());
        } catch (Throwable $e) {
            Log::notice('Alumni reference data unavailable', ['exception' => get_class($e)]);

            return collect();
        }
    }

    protected function isActive(array $row): bool
    {
        return strtoupper(trim((string) ($row['Is_Active'] ?? 'TRUE'))) !== 'FALSE';
    }
}
