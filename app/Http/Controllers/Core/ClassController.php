<?php

namespace App\Http\Controllers\Core;

use App\Helpers\CollectionHelper;
use App\Helpers\SheetValue;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClassRequest;
use App\Http\Requests\UpdateClassRequest;
use App\Services\Core\BatchService;
use App\Services\Core\ClassService;
use App\Services\Core\ProgramService;
use App\Services\Core\RoleService;
use App\Services\Core\StudentService;
use App\Services\Core\TeacherService;
use App\Support\Academic\TeacherScopeResolver;
use App\Traits\Exportable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClassController extends Controller
{
    use Exportable;

    protected $exportDateField = 'Created_At';

    protected function getExportConfig(Request $request)
    {

        $classes = $this->classService->getAllClasses();
        $programs = $this->programService->getAllPrograms();
        $batches = $this->batchService->getAllBatches();
        $teachers = $this->teacherService->getAllTeachers();

        $classes = $classes->map(function ($cls) use ($programs, $batches, $teachers) {
            $program = $programs->firstWhere('Program_ID', $cls['Program_ID']);
            $batch = $batches->firstWhere('Batch_ID', $cls['Batch_ID']);
            $teacher = $teachers->firstWhere('Teacher_ID', $cls['Homeroom_Teacher_ID']);

            $cls['Program_Name'] = $program ? $program['Program_Name'] : '-';
            $cls['Batch_Name'] = $batch ? $batch['Batch_Name'] : '-';
            $cls['Teacher_Name'] = $teacher ? $teacher['Full_Name'] : '-';

            return $cls;
        });

        $search = $request->input('search');
        if (! empty($search)) {
            $classes = CollectionHelper::search($classes, $search, ['Class_Code', 'Class_Name', 'Program_Name', 'Batch_Name', 'Teacher_Name', 'Room']);
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status !== 'all') {
                $classes = $classes->where('Is_Active', $status === 'active' ? 'TRUE' : 'FALSE');
            }
        }
        if ($request->filled('program_id')) {
            $programId = $request->input('program_id');
            if ($programId !== 'all') {
                $classes = $classes->where('Program_ID', $programId);
            }
        }
        if ($request->filled('batch_id')) {
            $batchId = $request->input('batch_id');
            if ($batchId !== 'all') {
                $classes = $classes->where('Batch_ID', $batchId);
            }
        }

        return [
            'moduleName' => 'Kelas (Class)',
            'data' => collect(array_values($classes->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Kode Kelas', 'Nama Kelas', 'Program', 'Angkatan', 'Wali Kelas', 'Ruangan', 'Status'],
            'mapRow' => function ($row) {

                return [
                    $row['Class_Code'] ?? '-',
                    $row['Class_Name'] ?? '-',
                    $row['Program_Name'] ?? '-',
                    $row['Batch_Name'] ?? '-',
                    $row['Teacher_Name'] ?? '-',
                    $row['Room'] ?? '-',
                    ($row['Is_Active'] ?? '') === 'TRUE' ? 'Aktif' : 'Tidak Aktif',
                ];
            },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$classes->count().'</td></tr>',
        ];
    }

    protected $classService;

    protected $programService;

    protected $batchService;

    protected $teacherService;

    public function __construct(
        ClassService $classService,
        ProgramService $programService,
        BatchService $batchService,
        TeacherService $teacherService
    ) {
        $this->classService = $classService;
        $this->programService = $programService;
        $this->batchService = $batchService;
        $this->teacherService = $teacherService;
    }

    public function index(Request $request)
    {
        try {
            $classes = $this->classService->getAllClasses();
            $programs = $this->programService->getAllPrograms();
            $batches = $this->batchService->getAllBatches();
            $teachers = $this->teacherService->getAllTeachers();

            // Mapping Names to Classes for display
            $classes = $classes->map(function ($cls) use ($programs, $batches, $teachers) {
                $program = $programs->firstWhere('Program_ID', $cls['Program_ID']);
                $batch = $batches->firstWhere('Batch_ID', $cls['Batch_ID']);
                $teacher = $teachers->firstWhere('Teacher_ID', $cls['Homeroom_Teacher_ID']);

                $cls['Program_Name'] = $program ? $program['Program_Name'] : 'Program Tidak Ditemukan';
                $cls['Batch_Name'] = $batch ? $batch['Batch_Name'] : 'Angkatan Tidak Ditemukan';
                $cls['Teacher_Name'] = $teacher ? $teacher['Full_Name'] : 'Wali Kelas Tidak Ditemukan';

                return $cls;
            });

            $search = $request->input('search');
            if (! empty($search)) {
                $classes = CollectionHelper::search($classes, $search, ['Class_ID', 'Class_Code', 'Class_Name', 'Program_Name', 'Batch_Name', 'Teacher_Name', 'Room']);
            }

            if ($request->filled('status')) {
                $status = $request->input('status');
                if ($status !== 'all') {
                    $classes = $classes->where('Is_Active', $status === 'active' ? 'TRUE' : 'FALSE');
                }
            }

            if ($request->filled('program_id')) {
                $programId = $request->input('program_id');
                if ($programId !== 'all') {
                    $classes = $classes->where('Program_ID', $programId);
                }
            }

            if ($request->filled('batch_id')) {
                $batchId = $request->input('batch_id');
                if ($batchId !== 'all') {
                    $classes = $classes->where('Batch_ID', $batchId);
                }
            }

            // Pagination
            $classesPaginated = CollectionHelper::paginate($classes, 10)->withQueryString();

            // For filter
            $activePrograms = $programs->where('Is_Active', 'TRUE')->values();
            $activeBatches = $batches->where('Is_Active', 'TRUE')->values();

            return view('academic.classes.index', [
                'classes' => $classesPaginated,
                'programs' => $activePrograms,
                'batches' => $activeBatches,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching classes: '.$e->getMessage());

            return redirect()->route('dashboard')->with('error', 'Gagal memuat data master kelas dari database.');
        }
    }

    public function create()
    {
        try {
            $programs = $this->programService->getAllPrograms()->where('Is_Active', 'TRUE')->values();
            $batches = $this->batchService->getAllBatches()->where('Is_Active', 'TRUE')->values();
            $teachers = $this->teacherService->getAllTeachers()->where('Is_Active', 'TRUE')->values();

            return view('academic.classes.create', compact('programs', 'batches', 'teachers'));
        } catch (\Exception $e) {
            Log::error('Error loading create class form: '.$e->getMessage());

            return redirect()->route('classes.index')->with('error', 'Gagal memuat form pendaftaran kelas.');
        }
    }

    public function store(StoreClassRequest $request)
    {
        try {
            $data = $request->validated();
            $class = $this->classService->createClass($data);

            return redirect()->route('classes.index')->with('success', 'Kelas berhasil ditambahkan.');
        } catch (\Exception $e) {
            Log::error('Error creating class: '.$e->getMessage());

            return back()->with('error', 'Terjadi kesalahan saat menyimpan data: '.$this->safeExceptionMessage($e))->withInput();
        }
    }

    public function show($id)
    {
        try {
            $class = $this->classService->getClassById($id);
            if (! $class) {
                return redirect()->route('classes.index')->with('error', 'Data kelas tidak ditemukan.');
            }

            $program = $this->programService->getProgramById($class['Program_ID']);
            $batch = $this->batchService->getBatchById($class['Batch_ID']);
            $teacher = $this->teacherService->getTeacherById($class['Homeroom_Teacher_ID']);

            $class['Program_Name'] = $program ? $program['Program_Name'] : 'Program Tidak Ditemukan';
            $class['Batch_Name'] = $batch ? $batch['Batch_Name'] : 'Angkatan Tidak Ditemukan';
            $class['Teacher_Name'] = $teacher ? $teacher['Full_Name'] : 'Wali Kelas Tidak Ditemukan';

            return view('academic.classes.show', compact('class'));
        } catch (\Exception $e) {
            Log::error('Error showing class: '.$e->getMessage());

            return redirect()->route('classes.index')->with('error', 'Terjadi kesalahan saat memuat data kelas.');
        }
    }

    public function edit($id)
    {
        try {
            $class = $this->classService->getClassById($id);
            if (! $class) {
                return redirect()->route('classes.index')->with('error', 'Data kelas tidak ditemukan.');
            }

            $programs = $this->programService->getAllPrograms()->where('Is_Active', 'TRUE')->values();
            $batches = $this->batchService->getAllBatches()->where('Is_Active', 'TRUE')->values();
            $teachers = $this->teacherService->getAllTeachers()->where('Is_Active', 'TRUE')->values();

            // Include inactive ones if currently selected
            $currentProgram = $this->programService->getProgramById($class['Program_ID']);
            if ($currentProgram && ($currentProgram['Is_Active'] ?? 'TRUE') === 'FALSE') {
                $programs->push($currentProgram);
            }

            $currentBatch = $this->batchService->getBatchById($class['Batch_ID']);
            if ($currentBatch && ($currentBatch['Is_Active'] ?? 'TRUE') === 'FALSE') {
                $batches->push($currentBatch);
            }

            $currentTeacher = $this->teacherService->getTeacherById($class['Homeroom_Teacher_ID']);
            if ($currentTeacher && ($currentTeacher['Is_Active'] ?? 'TRUE') === 'FALSE') {
                $teachers->push($currentTeacher);
            }

            return view('academic.classes.edit', compact('class', 'programs', 'batches', 'teachers'));
        } catch (\Exception $e) {
            Log::error('Error editing class: '.$e->getMessage());

            return redirect()->route('classes.index')->with('error', 'Terjadi kesalahan saat memuat form edit kelas.');
        }
    }

    public function update(UpdateClassRequest $request, $id)
    {
        try {
            $class = $this->classService->getClassById($id);
            if (! $class) {
                return redirect()->route('classes.index')->with('error', 'Data kelas tidak ditemukan.');
            }

            $data = $request->validated();
            $this->classService->updateClass($id, $data);

            return redirect()->route('classes.index')->with('success', 'Data kelas berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating class: '.$e->getMessage());

            return back()->with('error', 'Terjadi kesalahan saat memperbarui data: '.$this->safeExceptionMessage($e))->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $class = $this->classService->getClassById($id);
            if (! $class) {
                return redirect()->route('classes.index')->with('error', 'Data kelas tidak ditemukan.');
            }

            $this->classService->deleteClass($id);

            return redirect()->route('classes.index')->with('success', 'Data kelas berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting class: '.$e->getMessage());

            return redirect()->route('classes.index')->with('error', 'Terjadi kesalahan saat menghapus data kelas.');
        }
    }

    public function getStudents($id, StudentService $studentService)
    {
        try {
            $requestedClassId = SheetValue::id($id);
            $user = auth()->user();
            $roleService = app(RoleService::class);
            $role = collect($roleService->getAllRoles())->firstWhere('Role_ID', $user->Role_ID ?? '');
            $roleName = strtoupper(trim((string) ($role['Role_Name'] ?? $user->Role ?? session('role') ?? '')));
            $allowedStudentIds = null;

            if (str_contains($roleName, 'TEACHER') || str_contains($roleName, 'GURU')) {
                $resolver = app(TeacherScopeResolver::class);
                $scope = $resolver->resolveForUser($user);
                if (empty($scope['teacher_id'])) {
                    return response()->json(['error' => 'Profil pengajar tidak ditemukan.'], 403);
                }

                if (! $resolver->managedClassAllowed($scope, $requestedClassId)) {
                    return response()->json(['error' => 'Akses Ditolak: Kelas di luar wewenang Anda.'], 403);
                }
                $allowedStudentIds = $scope['managed_students_by_class'][$requestedClassId] ?? [];
            }

            $students = $studentService->getAllStudents();
            $classStudents = $students
                ->filter(fn ($student) => SheetValue::isActive($student))
                ->filter(function ($student) use ($requestedClassId, $allowedStudentIds) {
                    $studentId = SheetValue::id($student['Student_ID'] ?? '');
                    if (is_array($allowedStudentIds)) {
                        return in_array($studentId, $allowedStudentIds, true);
                    }

                    return SheetValue::id($student['Class_ID'] ?? '') === $requestedClassId;
                })
                ->map(function ($student) {
                    $student['Student_ID'] = trim((string) ($student['Student_ID'] ?? ''));
                    $student['Full_Name'] = trim((string) ($student['Full_Name'] ?? $student['Student_ID'] ?? 'Siswa'));

                    return $student;
                })
                ->values()
                ->toArray();

            return response()->json($classStudents);
        } catch (\Exception $e) {
            Log::error('Error fetching students by class: '.$e->getMessage());

            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }
}
