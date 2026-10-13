<?php

namespace App\Http\Controllers\Core;

use App\Helpers\CollectionHelper;
use App\Helpers\SheetValue;
use App\Helpers\UserResolverHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Services\Academic\PlacementService;
use App\Services\Core\AlumniService;
use App\Services\Core\BatchService;
use App\Services\Core\ClassService;
use App\Services\Core\ProgramService;
use App\Services\Core\StudentService;
use App\Services\Core\UserService;
use App\Support\ActorIdentity;
use App\Traits\Exportable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StudentController extends Controller
{
    use Exportable;

    protected $exportDateField = 'Created_At';

    protected function getExportConfig(Request $request)
    {

        $students = $this->studentService->getAllStudents();
        $programs = $this->programService->getAllPrograms();
        $batches = $this->batchService->getAllBatches();
        $classes = $this->classService->getAllClasses();

        $students = $students->map(function ($student) use ($programs, $batches, $classes) {
            $program = $programs->firstWhere('Program_ID', $student['Program_ID']);
            $batch = $batches->firstWhere('Batch_ID', $student['Batch_ID']);
            $class = $classes->firstWhere('Class_ID', $student['Class_ID']);

            $student['Program_Name'] = $program ? $program['Program_Name'] : '-';
            $student['Batch_Name'] = $batch ? $batch['Batch_Name'] : '-';
            $student['Class_Name'] = $class ? $class['Class_Name'] : '-';

            return $student;
        });

        $search = $request->input('search');
        if (! empty($search)) {
            $students = CollectionHelper::search($students, $search, ['NIS', 'Full_Name', 'Program_Name', 'Batch_Name', 'Class_Name']);
        }

        if ($request->filled('program')) {
            $students = $students->where('Program_ID', $request->input('program'));
        }
        if ($request->filled('batch')) {
            $students = $students->where('Batch_ID', $request->input('batch'));
        }
        if ($request->filled('class')) {
            $students = $students->where('Class_ID', $request->input('class'));
        }

        $status = strtolower((string) $request->input('status', 'active'));
        if ($status !== 'all') {
            $students = $students->filter(fn ($student) => $status === 'inactive'
                ? ! SheetValue::isOperationalStudent((array) $student)
                : SheetValue::isOperationalStudent((array) $student));
        }

        return [
            'moduleName' => 'Siswa (Student)',
            'data' => collect(array_values($students->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['NIS', 'Nama Siswa', 'Program', 'Angkatan', 'Kelas', 'Status'],
            'mapRow' => function ($row) {

                return [
                    $row['NIS'] ?? '-',
                    $row['Full_Name'] ?? '-',
                    $row['Program_Name'] ?? '-',
                    $row['Batch_Name'] ?? '-',
                    $row['Class_Name'] ?? '-',
                    SheetValue::isOperationalStudent((array) $row) ? 'Aktif' : 'Tidak Aktif',
                ];
            },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$students->count().'</td></tr>',
        ];
    }

    protected $studentService;

    protected $programService;

    protected $batchService;

    protected $classService;

    protected $userService;

    protected $placementService;

    public function __construct(
        StudentService $studentService,
        ProgramService $programService,
        BatchService $batchService,
        ClassService $classService,
        UserService $userService,
        PlacementService $placementService
    ) {
        $this->studentService = $studentService;
        $this->programService = $programService;
        $this->batchService = $batchService;
        $this->classService = $classService;
        $this->userService = $userService;
        $this->placementService = $placementService;
    }

    public function index(Request $request)
    {
        try {
            $students = $this->studentService->getAllStudents();
            $programs = $this->programService->getAllPrograms();
            $batches = $this->batchService->getAllBatches();
            $classes = $this->classService->getAllClasses();

            // Mapping Names to Students for display
            $students = $students->map(function ($student) use ($programs, $batches, $classes) {
                $program = $programs->firstWhere('Program_ID', $student['Program_ID']);
                $batch = $batches->firstWhere('Batch_ID', $student['Batch_ID']);
                $class = $classes->firstWhere('Class_ID', $student['Class_ID']);

                $student['Program_Name'] = $program ? $program['Program_Name'] : 'Tidak Ditemukan';
                $student['Batch_Name'] = $batch ? $batch['Batch_Name'] : 'Tidak Ditemukan';
                $student['Class_Name'] = $class ? $class['Class_Name'] : 'Tidak Ditemukan';

                return $student;
            });

            $search = $request->input('search');
            if (! empty($search)) {
                $students = CollectionHelper::search($students, $search, ['Student_ID', 'NIS', 'Full_Name', 'Email', 'Phone', 'Program_Name', 'Batch_Name', 'Class_Name']);
            }

            if ($request->filled('program')) {
                $students = $students->where('Program_ID', $request->input('program'));
            }

            if ($request->filled('batch')) {
                $students = $students->where('Batch_ID', $request->input('batch'));
            }

            if ($request->filled('class')) {
                $students = $students->where('Class_ID', $request->input('class'));
            }

            $status = strtolower((string) $request->input('status', 'active'));
            if ($status !== 'all') {
                $students = $students->filter(fn ($student) => $status === 'inactive'
                    ? ! SheetValue::isOperationalStudent((array) $student)
                    : SheetValue::isOperationalStudent((array) $student));
            }

            if ($request->filled('date_from')) {
                $dateFrom = Carbon::parse($request->input('date_from'))->startOfDay();
                $students = $students->filter(function ($item) use ($dateFrom) {
                    $dateField = $item['Registration_Date'] ?? $item['Created_At'] ?? null;
                    if (! $dateField) {
                        return false;
                    }

                    return Carbon::parse($dateField)->startOfDay()->gte($dateFrom);
                });
            }

            if ($request->filled('date_to')) {
                $dateTo = Carbon::parse($request->input('date_to'))->endOfDay();
                $students = $students->filter(function ($item) use ($dateTo) {
                    $dateField = $item['Registration_Date'] ?? $item['Created_At'] ?? null;
                    if (! $dateField) {
                        return false;
                    }

                    return Carbon::parse($dateField)->endOfDay()->lte($dateTo);
                });
            }

            $studentGroups = $students
                ->groupBy(fn ($student) => trim((string) ($student['Class_ID'] ?? '')) ?: 'NO_CLASS')
                ->map(function ($group, $classId) {
                    $first = $group->first();
                    $active = $group->filter(fn ($student) => SheetValue::isOperationalStudent((array) $student))->count();

                    return [
                        'id' => $classId,
                        'title' => $first['Class_Name'] ?? ($classId === 'NO_CLASS' ? 'Belum Ada Kelas' : $classId),
                        'subtitle' => trim(($first['Program_Name'] ?? '-').' / '.($first['Batch_Name'] ?? '-')),
                        'total' => $group->count(),
                        'active' => $active,
                        'inactive' => $group->count() - $active,
                        'items' => $group->sortBy('Full_Name')->values(),
                    ];
                })
                ->sortBy('title')
                ->values();

            // Pagination
            $studentsPaginated = CollectionHelper::paginate($students, 10)->withQueryString();

            // For filter
            $activePrograms = $programs->where('Is_Active', 'TRUE')->values();
            $activeBatches = $batches->where('Is_Active', 'TRUE')->values();
            $activeClasses = $classes->where('Is_Active', 'TRUE')->values();

            return view('students.index', [
                'students' => $studentsPaginated,
                'studentGroups' => $studentGroups,
                'programs' => $activePrograms,
                'batches' => $activeBatches,
                'classes' => $activeClasses,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching students: '.$e->getMessage());

            return redirect()->route('dashboard')->with('error', 'Gagal memuat data master siswa dari database.');
        }
    }

    public function create()
    {
        try {
            $programs = $this->programService->getAllPrograms()->where('Is_Active', 'TRUE')->values();
            $batches = $this->batchService->getAllBatches()->where('Is_Active', 'TRUE')->values();
            $classes = $this->classService->getAllClasses()->where('Is_Active', 'TRUE')->values();

            $allUsers = $this->userService->getAllUsers();
            $allStudents = $this->studentService->getAllStudents();
            $usedUserIds = $allStudents->pluck('User_ID')->filter()->toArray();

            $users = collect($allUsers)->filter(function ($user) use ($usedUserIds) {
                return ! in_array($user['User_ID'], $usedUserIds)
                    && ($user['Is_Active'] ?? 'TRUE') === 'TRUE'
                    && $this->userHasRole($user, 'STUDENT');
            })->values();

            return view('students.create', compact('programs', 'batches', 'classes', 'users'));
        } catch (\Exception $e) {
            Log::error('Error loading create student form: '.$e->getMessage());

            return redirect()->route('students.index')->with('error', 'Gagal memuat form pendaftaran siswa.');
        }
    }

    public function store(StoreStudentRequest $request)
    {
        try {
            $data = $request->validated();
            $student = $this->studentService->createStudent($data);

            if ($request->hasFile('Photo')) {
                $file = $request->file('Photo');
                $filename = 'student_'.$student['Student_ID'].'.'.$file->getClientOriginalExtension();
                $file->storeAs('profiles', $filename, 'public');
            }

            return redirect()->route('students.index')->with('success', 'Siswa berhasil didaftarkan.');
        } catch (\Exception $e) {
            Log::error('Error creating student: '.$e->getMessage());

            return back()->with('error', 'Terjadi kesalahan saat menyimpan data: '.$this->safeExceptionMessage($e))->withInput();
        }
    }

    public function show($id)
    {
        try {
            $student = $this->studentService->getStudentById($id);
            if (! $student) {
                return redirect()->route('students.index')->with('error', 'Data siswa tidak ditemukan.');
            }

            $program = $this->programService->getProgramById($student['Program_ID']);
            $batch = $this->batchService->getBatchById($student['Batch_ID']);
            $class = $this->classService->getClassById($student['Class_ID']);

            $student['Program_Name'] = $program
                ? ($program['Program_Name'] ?? '')
                : (empty($student['Program_ID']) ? '' : 'Data tidak ditemukan');
            $student['Batch_Name'] = $batch
                ? ($batch['Batch_Name'] ?? '')
                : (empty($student['Batch_ID']) ? '' : 'Data tidak ditemukan');
            $student['Class_Name'] = $class
                ? ($class['Class_Name'] ?? '')
                : (empty($student['Class_ID']) ? '' : 'Data tidak ditemukan');

            $alumni = null;
            try {
                $alumni = app(AlumniService::class)->getByStudentId((string) $id, false);
            } catch (\Throwable $e) {
                Log::notice('Alumni registry lookup skipped on student detail', ['student_id' => $id]);
            }

            return view('students.show', compact('student', 'alumni'));
        } catch (\Exception $e) {
            Log::error('Error showing student: '.$e->getMessage());

            return redirect()->route('students.index')->with('error', 'Terjadi kesalahan saat memuat profil siswa.');
        }
    }

    public function edit($id)
    {
        try {
            $student = $this->studentService->getStudentById($id);
            if (! $student) {
                return redirect()->route('students.index')->with('error', 'Data siswa tidak ditemukan.');
            }

            $programs = $this->programService->getAllPrograms()->where('Is_Active', 'TRUE')->values();
            $batches = $this->batchService->getAllBatches()->where('Is_Active', 'TRUE')->values();
            $classes = $this->classService->getAllClasses()->where('Is_Active', 'TRUE')->values();

            // Include inactive ones if currently selected
            $currentProgram = $this->programService->getProgramById($student['Program_ID']);
            if ($currentProgram && ($currentProgram['Is_Active'] ?? 'TRUE') === 'FALSE') {
                $programs->push($currentProgram);
            }

            $currentBatch = $this->batchService->getBatchById($student['Batch_ID']);
            if ($currentBatch && ($currentBatch['Is_Active'] ?? 'TRUE') === 'FALSE') {
                $batches->push($currentBatch);
            }

            $currentClass = $this->classService->getClassById($student['Class_ID']);
            if ($currentClass && ($currentClass['Is_Active'] ?? 'TRUE') === 'FALSE') {
                $classes->push($currentClass);
            }

            $allUsers = $this->userService->getAllUsers();
            $allStudents = $this->studentService->getAllStudents();
            $usedUserIds = $allStudents->where('Student_ID', '!=', $id)->pluck('User_ID')->filter()->toArray();

            $users = collect($allUsers)->filter(function ($user) use ($usedUserIds, $student) {
                return ! in_array($user['User_ID'], $usedUserIds)
                    && ($this->userHasRole($user, 'STUDENT') || ($user['User_ID'] ?? '') === ($student['User_ID'] ?? ''));
            })->values();

            return view('students.edit', compact('student', 'programs', 'batches', 'classes', 'users'));
        } catch (\Exception $e) {
            Log::error('Error editing student: '.$e->getMessage());

            return redirect()->route('students.index')->with('error', 'Terjadi kesalahan saat memuat form edit siswa.');
        }
    }

    public function update(UpdateStudentRequest $request, $id)
    {
        try {
            $student = $this->studentService->getStudentById($id);
            if (! $student) {
                return redirect()->route('students.index')->with('error', 'Data siswa tidak ditemukan.');
            }

            $data = $request->validated();

            // 1. Handle Placement if Class/Batch changed
            if (isset($data['Batch_ID']) || isset($data['Class_ID'])) {
                $newBatchId = $data['Batch_ID'] ?? ($student['Batch_ID'] ?? null);
                $newClassId = $data['Class_ID'] ?? ($student['Class_ID'] ?? null);
                $this->placementService->placeStudent($id, $newBatchId, $newClassId, ActorIdentity::required());
            }

            // 2. Update the rest of student profile (Class_ID & Batch_ID are ignored internally by StudentService now)
            $this->studentService->updateStudent($id, $data);

            if ($request->hasFile('Photo')) {
                $file = $request->file('Photo');
                $filename = 'student_'.$id.'.'.$file->getClientOriginalExtension();
                // Delete old ones first to prevent extension mismatch (e.g., .png vs .jpg)
                $oldFiles = glob(storage_path('app/public/profiles/student_'.$id.'.*'));
                foreach ($oldFiles as $oldFile) {
                    if (is_file($oldFile)) {
                        @unlink($oldFile);
                    }
                }
                $file->storeAs('profiles', $filename, 'public');
            }

            return redirect()->route('students.index')->with('success', 'Data profil siswa berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating student: '.$e->getMessage());

            return back()->with('error', 'Terjadi kesalahan saat memperbarui data: '.$this->safeExceptionMessage($e))->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $student = $this->studentService->getStudentById($id);
            if (! $student) {
                return redirect()->route('students.index')->with('error', 'Data siswa tidak ditemukan.');
            }

            $this->studentService->deleteStudent($id);

            return redirect()->route('students.index')->with('success', 'Data siswa berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting student: '.$e->getMessage());

            return redirect()->route('students.index')->with('error', 'Terjadi kesalahan saat menghapus data siswa.');
        }
    }

    public function lookup($id)
    {
        try {
            $student = $this->studentService->getStudentById($id);
            if (! $student) {
                return response()->json(['error' => 'Data siswa tidak ditemukan.'], 404);
            }

            $program = $this->programService->getProgramById($student['Program_ID']);
            $batch = $this->batchService->getBatchById($student['Batch_ID']);
            $class = $this->classService->getClassById($student['Class_ID']);

            return response()->json([
                'Student_ID' => $student['Student_ID'] ?? '',
                'Student_Number' => $student['Student_Number'] ?? '',
                'Full_Name' => $student['Full_Name'] ?? '',
                'Email' => $student['Email'] ?? '',
                'Phone_Number' => $student['Phone_Number'] ?? '',
                'Enrollment_Status' => $student['Enrollment_Status'] ?? '',
                'Program_Name' => $program ? $program['Program_Name'] : '-',
                'Batch_Name' => $batch ? $batch['Batch_Name'] : '-',
                'Class_Name' => $class ? $class['Class_Name'] : '-',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Terjadi kesalahan internal.'], 500);
        }
    }

    public function graduate($id)
    {
        try {
            $student = $this->studentService->getStudentById($id);
            if (! $student) {
                return redirect()->route('students.show', $id)->with('error', 'Data siswa tidak ditemukan.');
            }

            return redirect()->route('alumni.create', ['student_id' => $id])
                ->with('info', 'Lengkapi data keberangkatan untuk mendaftarkan siswa ini sebagai Alumni.');
        } catch (\Exception $e) {
            Log::error('Error graduating student: '.$e->getMessage());

            return redirect()->route('students.show', $id)->with('error', 'Terjadi kesalahan: '.$this->safeExceptionMessage($e));
        }
    }

    private function userHasRole(array $user, string $expectedRole): bool
    {
        $roleName = UserResolverHelper::getRoleName($user['Role_ID'] ?? '');

        return strtoupper(trim($roleName)) === strtoupper($expectedRole);
    }
}
