<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssignmentRequest;
use App\Http\Requests\UpdateAssignmentRequest;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Repositories\GoogleSheets\ClassRepository;
use App\Repositories\GoogleSheets\SubjectRepository;
use App\Repositories\GoogleSheets\TeacherRepository;
use App\Services\Core\AssignmentService;
use App\Services\Core\RoleService;
use App\Support\Academic\AssignmentStatus;
use App\Support\Academic\TeacherScopeResolver;
use App\Support\Reporting\HumanReadableResolver;
use App\Traits\Exportable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AssignmentController extends Controller
{
    use Exportable;

    protected $exportDateField = 'Deadline';

    protected function getExportConfig(Request $request)
    {
        $assignments = $this->assignmentService->getAll();

        $user = auth()->user();
        if ($user) {
            if ($this->isTeacherUser()) {
                $scope = app(TeacherScopeResolver::class)->resolveForUser($user);
                $teacherId = trim((string) ($scope['teacher_id'] ?? ''));
                if ($teacherId !== '') {
                    $myClassIds = $scope['teaching_class_ids'] ?? [];
                    $assignments = $assignments
                        ->where('Teacher_ID', $teacherId)
                        ->whereIn('Class_ID', $myClassIds)
                        ->values();
                } else {
                    $assignments = collect([]); // No teacher profile, no assignments
                }
            }
        }

        $classRepo = app(ClassRepository::class);
        $subjectRepo = app(SubjectRepository::class);
        $classes = $classRepo->fetchAll()->keyBy('Class_ID');
        $subjects = collect($subjectRepo->fetchAll())->keyBy('Subject_ID');
        $teachers = collect(app(TeacherRepositoryInterface::class)->fetchAll())->keyBy('Teacher_ID');

        return [
            'moduleName' => 'Tugas Harian',
            'data' => collect(array_values($assignments->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Judul', 'Kelas', 'Mata Pelajaran', 'Guru', 'Deadline', 'Status'],
            'mapRow' => function ($row) use ($classes, $subjects, $teachers) {
                return [
                    $row['Title'] ?? '-',
                    HumanReadableResolver::className($row['Class_ID'] ?? '', $classes),
                    HumanReadableResolver::subjectName($row['Subject_ID'] ?? '', $subjects),
                    HumanReadableResolver::teacherName($row['Teacher_ID'] ?? '', $teachers),
                    $row['Deadline'] ?? '-',
                    AssignmentStatus::label($row['Status'] ?? 'PUBLISHED'),
                ];
            },
            'isLandscape' => false,
        ];
    }

    protected $assignmentService;

    public function __construct(AssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    private function currentRoleName(): string
    {
        $user = auth()->user();
        if (! $user) {
            return '';
        }

        $roleName = strtolower(trim((string) ($user->Role ?? '')));
        if ($roleName !== '') {
            return $roleName;
        }

        $roleService = app(RoleService::class);

        return strtolower(trim($roleService->getRoleById($user->Role_ID ?? '')['Role_Name'] ?? ''));
    }

    private function isTeacherUser(): bool
    {
        $roleName = $this->currentRoleName();

        return str_contains($roleName, 'teacher') || str_contains($roleName, 'guru');
    }

    private function currentTeacherId(): ?string
    {
        $user = auth()->user();
        if (! $user || ! $this->isTeacherUser()) {
            return null;
        }

        $scope = app(TeacherScopeResolver::class)->resolveForUser($user);

        return ($scope['teacher_id'] ?? '') ?: null;
    }

    public function index()
    {
        if ($this->isTeacherUser()) {
            return redirect()->route('teacher.workspace.assignments');
        }

        $assignments = collect($this->assignmentService->getAll());
        $classes = collect(app(ClassRepository::class)->fetchAll())->keyBy('Class_ID');
        $subjects = collect(app(SubjectRepository::class)->fetchAll())->keyBy('Subject_ID');
        $teachers = collect(app(TeacherRepository::class)->fetchAll())->keyBy('Teacher_ID');
        $assignments = $assignments->map(function ($assignment) use ($classes, $subjects, $teachers) {
            $assignment['Class_Name'] = HumanReadableResolver::className($assignment['Class_ID'] ?? '', $classes);
            $assignment['Subject_Name'] = HumanReadableResolver::subjectName($assignment['Subject_ID'] ?? '', $subjects);
            $assignment['Teacher_Name'] = HumanReadableResolver::teacherName($assignment['Teacher_ID'] ?? '', $teachers);

            return $assignment;
        });

        return view('academic.assignments.index', compact('assignments'));
    }

    private function verifyAssignmentScope($assignmentId)
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        // Only restrict teachers
        if (! $this->isTeacherUser()) {
            return;
        }

        $assignment = $this->assignmentService->getById($assignmentId);
        if (! $assignment) {
            abort(404, 'Tugas tidak ditemukan.');
        }

        $teacherId = $this->currentTeacherId();
        if (! $teacherId) {
            abort(403, 'Profil Pengajar tidak ditemukan.');
        }

        if (($assignment['Teacher_ID'] ?? '') !== $teacherId) {
            abort(403, 'Anda tidak memiliki hak akses untuk tugas ini (IDOR Protected).');
        }

        if (! $this->teacherCanAccessClass($teacherId, $assignment['Class_ID'] ?? '')) {
            abort(403, 'Anda tidak memiliki hak akses untuk tugas kelas ini (IDOR Protected).');
        }
    }

    private function verifyTeacherClassScope($classId)
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        if (! $this->isTeacherUser()) {
            return;
        }

        $teacherId = $this->currentTeacherId();
        if (! $teacherId) {
            abort(403, 'Profil Pengajar tidak ditemukan.');
        }

        if (! $this->teacherCanAccessClass($teacherId, $classId)) {
            abort(403, 'Anda tidak memiliki hak akses untuk kelas ini (IDOR Protected).');
        }
    }

    private function teacherCanAccessClass(string $teacherId, string $classId): bool
    {
        if ($classId === '') {
            return false;
        }

        return app(TeacherScopeResolver::class)->teachingClassAllowed(
            app(TeacherScopeResolver::class)->resolveForTeacherId($teacherId),
            $classId
        );
    }

    public function create(
        ClassRepository $classRepo,
        TeacherRepository $teacherRepo
    ) {
        $classes = $classRepo->fetchAll();
        $teachers = $teacherRepo->fetchAll();

        $currentTeacherId = null;
        $user = auth()->user();
        if ($user) {
            if ($this->isTeacherUser()) {
                $currentTeacherId = $this->currentTeacherId();
            }
        }

        if ($currentTeacherId) {
            $allowedClassIds = app(TeacherScopeResolver::class)
                ->resolveForTeacherId($currentTeacherId)['teaching_class_ids'];
            $classes = collect($classes)->whereIn('Class_ID', $allowedClassIds)->values();
        }

        return view('academic.assignments.create', compact('classes', 'teachers', 'currentTeacherId'));
    }

    public function store(StoreAssignmentRequest $request)
    {
        $data = $request->validated();
        $this->verifyTeacherClassScope($data['Class_ID']);
        if ($this->isTeacherUser()) {
            $teacherId = $this->currentTeacherId();
            if (! $teacherId) {
                abort(403, 'Profil Pengajar tidak ditemukan.');
            }
            $data['Teacher_ID'] = $teacherId;
        }
        $this->assignmentService->create($data);

        $user = auth()->user();
        if ($user) {
            if ($this->isTeacherUser()) {
                return redirect()->route('teacher.workspace.assignments')->with('success', 'Tugas berhasil dibuat!');
            }
        }

        return redirect()->route('assignments.index')->with('success', 'Created!');
    }

    public function show(
        $id,
        ClassRepository $classRepo,
        TeacherRepository $teacherRepo
    ) {
        return $this->edit($id, $classRepo, $teacherRepo);
    }

    public function edit(
        $id,
        ClassRepository $classRepo,
        TeacherRepository $teacherRepo
    ) {
        $this->verifyAssignmentScope($id);

        $assignment = $this->assignmentService->getById($id);
        $classes = $classRepo->fetchAll();
        $teachers = $teacherRepo->fetchAll();

        $currentTeacherId = null;
        $user = auth()->user();
        if ($user) {
            if ($this->isTeacherUser()) {
                $currentTeacherId = $this->currentTeacherId();
            }
        }

        if ($currentTeacherId) {
            $allowedClassIds = app(TeacherScopeResolver::class)
                ->resolveForTeacherId($currentTeacherId)['teaching_class_ids'];
            $classes = collect($classes)->whereIn('Class_ID', $allowedClassIds)->values();
        }

        return view('academic.assignments.edit', compact('assignment', 'classes', 'teachers', 'currentTeacherId'));
    }

    public function update(UpdateAssignmentRequest $request, $id)
    {
        $this->verifyAssignmentScope($id);
        $data = $request->validated();
        $this->verifyTeacherClassScope($data['Class_ID']);
        if ($this->isTeacherUser()) {
            $teacherId = $this->currentTeacherId();
            if (! $teacherId) {
                abort(403, 'Profil Pengajar tidak ditemukan.');
            }
            $data['Teacher_ID'] = $teacherId;
        }

        Log::info('Assignment Update Payload: '.json_encode($data));

        $this->assignmentService->update($id, $data);

        $user = auth()->user();
        if ($user) {
            if ($this->isTeacherUser()) {
                return redirect()->route('teacher.workspace.assignments')->with('success', 'Tugas berhasil diperbarui!');
            }
        }

        return redirect()->route('assignments.index')->with('success', 'Updated!');
    }

    public function destroy($id)
    {
        $this->verifyAssignmentScope($id);

        $this->assignmentService->delete($id);

        $user = auth()->user();
        if ($user) {
            if ($this->isTeacherUser()) {
                return redirect()->route('teacher.workspace.assignments')->with('success', 'Tugas berhasil dihapus!');
            }
        }

        return redirect()->route('assignments.index')->with('success', 'Deleted!');
    }
}
