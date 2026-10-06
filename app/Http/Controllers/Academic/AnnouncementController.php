<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Http\Requests\UpdateAnnouncementRequest;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Services\Academic\AnnouncementCommentService;
use App\Services\Academic\AnnouncementService;
use App\Services\Academic\AnnouncementVisibilityService;
use App\Services\Core\ClassService;
use App\Services\Core\RoleService;
use App\Support\ActorIdentity;
use App\Traits\Exportable;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    use Exportable;

    protected $exportDateField = 'Created_At';

    protected function getExportConfig(Request $request)
    {
        $announcements = $this->managedAnnouncements();

        return [
            'moduleName' => 'Pengumuman Akademik',
            'data' => collect(array_values($announcements->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Judul', 'Target Penerima', 'Prioritas', 'Status', 'Dibuat Pada'],
            'mapRow' => function ($row) {
                return [
                    $row['Title'] ?? '-',
                    $this->announcementService->audienceLabel((array) $row),
                    $this->announcementService->priorityLabel($row['Priority'] ?? 'NORMAL'),
                    $this->announcementService->presentationStatus((array) $row),
                    $row['Created_At'] ?? '-',
                ];
            },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Pengumuman</td><td>: '.$announcements->count().'</td></tr>',
        ];
    }

    protected $announcementService;

    protected $classService;

    protected $roleService;

    public function __construct(AnnouncementService $announcementService, RoleService $roleService, ClassService $classService)
    {
        $this->announcementService = $announcementService;
        $this->roleService = $roleService;
        $this->classService = $classService;
    }

    public function index(Request $request)
    {
        $announcements = $this->managedAnnouncements();
        try {
            $commentCounts = app(AnnouncementCommentService::class)
                ->counts($announcements->pluck('Announcement_ID'));
        } catch (\Throwable) {
            $commentCounts = [];
        }
        $announcements = $announcements->map(function ($announcement) use ($commentCounts) {
            $announcement['Audience_Label'] = $this->announcementService->audienceLabel((array) $announcement);
            $announcement['Priority_Label'] = $this->announcementService->priorityLabel($announcement['Priority'] ?? 'NORMAL');
            $announcement['Status_Label'] = $this->announcementService->presentationStatus((array) $announcement);
            $announcement['Start_Label'] = $this->formatDate($this->announcementService->startAt((array) $announcement));
            $announcement['Expiry_Label'] = $this->formatDate($this->announcementService->expiresAt((array) $announcement));
            $announcement['Can_Manage'] = $this->canManage((array) $announcement);
            $announcement['Comment_Count'] = $commentCounts[$announcement['Announcement_ID']] ?? 0;

            return $announcement;
        });

        return view('academic.announcements.index', compact('announcements'));
    }

    public function create()
    {
        return view('academic.announcements.create', $this->formOptions());
    }

    public function store(StoreAnnouncementRequest $request)
    {
        try {
            $data = $request->validated();
            $this->announcementService->create($data);

            return redirect()->route('announcements.index')->with('success', 'Pengumuman berhasil dibuat.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)])->withInput();
        }
    }

    public function show($id)
    {
        $announcement = $this->announcementService->getById($id);
        if (! $announcement) {
            return redirect()->route('announcements.index')->withErrors(['error' => 'Pengumuman tidak ditemukan.']);
        }
        $this->authorizeTeacherOwnership((array) $announcement);
        $announcement['Audience_Label'] = $this->announcementService->audienceLabel((array) $announcement);
        $announcement['Priority_Label'] = $this->announcementService->priorityLabel($announcement['Priority'] ?? 'NORMAL');
        $announcement['Status_Label'] = $this->announcementService->presentationStatus((array) $announcement);
        $announcement['Start_Label'] = $this->formatDate($this->announcementService->startAt((array) $announcement));
        $announcement['Expiry_Label'] = $this->formatDate($this->announcementService->expiresAt((array) $announcement));
        $announcement['Can_Manage'] = $this->canManage((array) $announcement);

        return view('academic.announcements.show', compact('announcement'));
    }

    public function edit($id)
    {
        $announcement = $this->announcementService->getById($id);
        if (! $announcement) {
            return redirect()->route('announcements.index')->withErrors(['error' => 'Pengumuman tidak ditemukan.']);
        }
        $this->authorizeTeacherOwnership((array) $announcement);
        $options = $this->formOptions();
        $options['audienceState'] = app(AnnouncementVisibilityService::class)->formState((array) $announcement);

        return view('academic.announcements.edit-v2', array_merge(['announcement' => $announcement], $options));
    }

    public function update(UpdateAnnouncementRequest $request, $id)
    {
        $this->authorizeExistingForTeacher($id);
        try {
            $data = $request->validated();
            $this->announcementService->update($id, $data);

            return redirect()->route('announcements.index')->with('success', 'Pengumuman berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)])->withInput();
        }
    }

    public function destroy($id)
    {
        $this->authorizeExistingForTeacher($id);
        try {
            $this->announcementService->delete($id);

            return redirect()->route('announcements.index')->with('success', 'Pengumuman berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }

    public function deactivate($id)
    {
        $this->authorizeExistingForTeacher($id);
        try {
            $this->announcementService->delete($id);

            return redirect()->route('announcements.index')->with('success', 'Pengumuman dinonaktifkan.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }

    protected function classes()
    {
        return collect($this->classService->getAllClasses())
            ->filter(fn ($class): bool => $this->isActiveMasterRow($class['Is_Active'] ?? true))
            ->values();
    }

    protected function formOptions(): array
    {
        $classes = $this->classes();
        $visibility = app(AnnouncementVisibilityService::class);
        $roles = $visibility->availableRoles();
        $batches = collect(app(BatchRepositoryInterface::class)->fetchAll())
            ->filter(fn ($batch): bool => $this->isActiveMasterRow($batch['Is_Active'] ?? true))
            ->values();
        $studentRoleId = collect($roles)->firstWhere('name', 'STUDENT')['id'] ?? '';
        $classesByBatch = $classes
            ->map(fn ($class): array => (array) $class)
            ->filter(fn (array $class): bool => trim((string) ($class['Class_ID'] ?? '')) !== '' && trim((string) ($class['Batch_ID'] ?? '')) !== '')
            ->groupBy(fn (array $class): string => trim((string) $class['Batch_ID']))
            ->map(fn ($rows) => collect($rows)->map(fn (array $class): array => [
                'id' => trim((string) $class['Class_ID']),
                'name' => trim((string) ($class['Class_Name'] ?? $class['Class_Code'] ?? $class['Class_ID'])),
            ])->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all())
            ->all();
        foreach ($batches as $batch) {
            $batchId = trim((string) ($batch['Batch_ID'] ?? ''));
            if ($batchId !== '' && ! array_key_exists($batchId, $classesByBatch)) {
                $classesByBatch[$batchId] = [];
            }
        }
        $audienceState = [
            'audience' => AnnouncementVisibilityService::SELECTED_ROLES,
            'roles' => $studentRoleId === '' ? [] : [$studentRoleId],
            'student_scope' => AnnouncementVisibilityService::ALL_STUDENTS,
            'batch' => '',
            'classes' => [],
        ];

        return compact('classes', 'roles', 'batches', 'classesByBatch', 'studentRoleId', 'audienceState');
    }

    protected function isActiveMasterRow(mixed $value): bool
    {
        return $value === true
            || in_array(strtoupper(trim((string) $value)), ['TRUE', '1', 'YES', 'ACTIVE', 'ON'], true);
    }

    protected function formatDate($date): string
    {
        return $date ? $date->locale('id')->translatedFormat('j F Y, H.i').' WIB' : '-';
    }

    protected function managedAnnouncements()
    {
        $announcements = $this->announcementService->getAll();
        $role = $this->currentRole();
        if (in_array($role, ['ADMINISTRATOR', 'ACADEMIC', 'MASTER'], true)) {
            return $announcements;
        }
        abort_unless($role === 'TEACHER', 403, 'Anda tidak memiliki hak akses untuk mengelola pengumuman.');

        $actorId = ActorIdentity::required();

        return $announcements
            ->filter(fn ($announcement) => hash_equals($actorId, trim((string) ($announcement['Created_By'] ?? ''))))
            ->values();
    }

    protected function authorizeExistingForTeacher($id): void
    {
        $announcement = $this->announcementService->getById($id);
        if (! $announcement) {
            throw new \InvalidArgumentException('Pengumuman tidak ditemukan.');
        }
        $this->authorizeTeacherOwnership((array) $announcement);
    }

    protected function authorizeTeacherOwnership(array $announcement): void
    {
        abort_unless($this->canManage($announcement), 403, 'Guru hanya dapat mengelola pengumuman yang dibuatnya sendiri.');
    }

    protected function canManage(array $announcement): bool
    {
        $role = $this->currentRole();
        if (in_array($role, ['ADMINISTRATOR', 'ACADEMIC', 'MASTER'], true)) {
            return true;
        }
        if ($role !== 'TEACHER') {
            return false;
        }
        $creator = trim((string) ($announcement['Created_By'] ?? ''));

        return $creator !== '' && hash_equals(ActorIdentity::required(), $creator);
    }

    protected function currentRole(): string
    {
        $user = auth()->user();
        $fallback = strtoupper(trim((string) ($user->Role ?? $user->Role_Name ?? '')));
        $roleId = trim((string) ($user->Role_ID ?? ''));
        if ($roleId === '') {
            return $fallback;
        }

        try {
            return strtoupper(trim((string) ($this->roleService->getRoleById($roleId)['Role_Name'] ?? $fallback)));
        } catch (\Throwable) {
            return 'UNKNOWN';
        }
    }
}
