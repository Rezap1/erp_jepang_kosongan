<?php

namespace App\Http\Controllers\Academic;

use App\Exceptions\ScheduleConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\SubjectRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Repositories\GoogleSheets\AcademicYearRepository;
use App\Repositories\GoogleSheets\ClassRepository;
use App\Repositories\GoogleSheets\SubjectRepository;
use App\Repositories\GoogleSheets\TeacherRepository;
use App\Services\Academic\ScheduleService;
use App\Support\Academic\AcademicSheetMapper;
use App\Support\Reporting\HumanReadableResolver;
use App\Traits\Exportable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ScheduleController extends Controller
{
    use Exportable;

    protected $exportDateField = 'Schedule_Date';

    protected function getExportConfig(Request $request)
    {

        $schedules = $this->scheduleService->getAll();
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $schedules = $schedules->filter(function ($item) use ($search) {
                return str_contains(strtolower($item['Schedule_ID'] ?? ''), $search);
            })->values();
        }

        $classesById = collect(app(ClassRepositoryInterface::class)->fetchAll())->keyBy('Class_ID');
        $subjectsById = collect(app(SubjectRepositoryInterface::class)->fetchAll())->keyBy('Subject_ID');
        $teachersById = collect(app(TeacherRepositoryInterface::class)->fetchAll())->keyBy('Teacher_ID');

        return [
            'moduleName' => 'Jadwal (Schedule)',
            'data' => collect(array_values($schedules->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Kelas', 'Mata Pelajaran', 'Guru', 'Hari', 'Jam'],
            'mapRow' => function ($row) use ($classesById, $subjectsById, $teachersById) {

                return [
                    HumanReadableResolver::className($row['Class_ID'] ?? '', $classesById),
                    HumanReadableResolver::subjectName($row['Subject_ID'] ?? '', $subjectsById),
                    HumanReadableResolver::teacherName($row['Teacher_ID'] ?? '', $teachersById),
                    $row['Day_Of_Week'] ?? '-',
                    ($row['Start_Time'] ?? '').' - '.($row['End_Time'] ?? ''),
                ];
            },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$schedules->count().'</td></tr>',
        ];
    }

    protected $scheduleService;

    public function __construct(ScheduleService $scheduleService)
    {
        $this->scheduleService = $scheduleService;
    }

    public function index(Request $request)
    {
        $schedules = $this->scheduleService->getAll();

        $classRepo = app(ClassRepository::class);
        $classes = $classRepo->fetchAll()->keyBy('Class_ID');

        $subjectRepo = app(SubjectRepository::class);
        $subjects = $subjectRepo->fetchAll()->keyBy('Subject_ID');

        $teacherRepo = app(TeacherRepository::class);
        $teachers = $teacherRepo->fetchAll()->keyBy('Teacher_ID');

        $schedules = $schedules->map(function ($item) use ($classes, $subjects, $teachers) {
            $item = AcademicSheetMapper::normalizeScheduleRow((array) $item);
            $classId = $item['Class_ID'] ?? null;
            $subjectId = $item['Subject_ID'] ?? null;
            $teacherId = $item['Teacher_ID'] ?? null;

            $className = $classId && isset($classes[$classId]) ? $classes[$classId]['Class_Name'] : null;
            $subjectName = $subjectId && isset($subjects[$subjectId]) ? $subjects[$subjectId]['Subject_Name'] : null;
            $teacherName = $teacherId && isset($teachers[$teacherId]) ? $teachers[$teacherId]['Full_Name'] : null;

            $item['Class_Name'] = $className ?: 'Kelas tidak ditemukan';
            $item['Subject_Name'] = $subjectName ?: 'Mata pelajaran tidak ditemukan';
            $item['Teacher_Name'] = $teacherName ?: 'Pengajar tidak ditemukan';

            return $item;
        });

        $scheduleGroups = $schedules
            ->groupBy(fn ($schedule) => trim((string) ($schedule['Day_Of_Week'] ?? $schedule['Day'] ?? 'NO_DAY')) ?: 'NO_DAY')
            ->map(function ($group, $day) {
                return [
                    'id' => $day,
                    'title' => $day,
                    'total' => $group->count(),
                    'items' => $group->sortBy('Start_Time')->values(),
                ];
            })
            ->sortBy(function ($group) {
                $order = array_search($group['id'], ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], true);

                return $order === false ? 99 : $order;
            })
            ->values();

        return view('academic.schedules.index', compact('schedules', 'scheduleGroups'));
    }

    public function create(
        ClassRepository $classRepo,
        SubjectRepository $subjectRepo,
        TeacherRepository $teacherRepo,
        AcademicYearRepository $ayRepo
    ) {
        $classes = $classRepo->fetchAll();
        $subjects = $subjectRepo->fetchAll();
        $teachers = $teacherRepo->fetchAll();
        $academicYears = $this->availableAcademicYears($ayRepo);

        $currentTeacherId = '';
        if (auth()->check() && (auth()->user()->Role ?? '') === 'TEACHER') {
            $userId = auth()->user()->User_ID ?? '';
            $teacher = collect($teachers)->firstWhere('User_ID', $userId);
            if ($teacher) {
                $currentTeacherId = $teacher['Teacher_ID'];
            }
        }

        return view('academic.schedules.create', compact('classes', 'subjects', 'teachers', 'academicYears', 'currentTeacherId'));
    }

    public function store(StoreScheduleRequest $request)
    {
        try {
            $data = $request->validated();

            $days = is_array($data['Day_Of_Week'] ?? '') ? $data['Day_Of_Week'] : [$data['Day_Of_Week']];

            if (empty($days) || (count($days) === 1 && empty($days[0]))) {
                throw new \Exception('Pilih setidaknya satu hari.');
            }

            foreach ($days as $day) {
                $scheduleData = $data;
                $scheduleData['Day_Of_Week'] = $day;
                $this->scheduleService->create($scheduleData);
            }

            return redirect()->route('schedules.index')->with('success', 'Jadwal berhasil dibuat.');
        } catch (ScheduleConflictException $e) {
            return back()->withErrors(['error' => $e->userMessage()])->withInput();
        } catch (\Throwable $e) {
            return back()->withErrors([
                'error' => $this->safeExceptionMessage($e, 'Jadwal tidak dapat disimpan. Data lama tetap dipertahankan.'),
            ])->withInput();
        }
    }

    public function show(
        $id,
        ClassRepository $classRepo,
        SubjectRepository $subjectRepo,
        TeacherRepository $teacherRepo,
        AcademicYearRepository $ayRepo
    ) {
        return $this->edit($id, $classRepo, $subjectRepo, $teacherRepo, $ayRepo);
    }

    public function edit(
        $id,
        ClassRepository $classRepo,
        SubjectRepository $subjectRepo,
        TeacherRepository $teacherRepo,
        AcademicYearRepository $ayRepo
    ) {
        $schedule = $this->scheduleService->getById($id);
        if (! $schedule) {
            return redirect()->route('schedules.index')->withErrors(['error' => 'Jadwal tidak ditemukan.']);
        }
        $schedule = AcademicSheetMapper::normalizeScheduleRow((array) $schedule);

        $classes = $classRepo->fetchAll();
        $subjects = $subjectRepo->fetchAll();
        $teachers = $teacherRepo->fetchAll();
        $academicYears = $this->availableAcademicYears($ayRepo, (string) ($schedule['Academic_Year_ID'] ?? ''));

        $currentTeacherId = '';
        if (auth()->check() && (auth()->user()->Role ?? '') === 'TEACHER') {
            $userId = auth()->user()->User_ID ?? '';
            $teacher = collect($teachers)->firstWhere('User_ID', $userId);
            if ($teacher) {
                $currentTeacherId = $teacher['Teacher_ID'];
            }
        }

        return view('academic.schedules.edit', compact('schedule', 'classes', 'subjects', 'teachers', 'academicYears', 'currentTeacherId'));
    }

    public function update(UpdateScheduleRequest $request, $id)
    {
        try {
            $data = $request->validated();
            $this->scheduleService->update($id, $data);

            return redirect()->route('schedules.index')->with('success', 'Jadwal berhasil diperbarui.');
        } catch (ScheduleConflictException $e) {
            return back()->withErrors(['error' => $e->userMessage()])->withInput();
        } catch (\Throwable $e) {
            return back()->withErrors([
                'error' => $this->safeExceptionMessage($e, 'Jadwal tidak dapat diperbarui. Data lama tetap dipertahankan.'),
            ])->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $this->scheduleService->delete($id);

            return redirect()->route('schedules.index')->with('success', 'Jadwal berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }

    private function availableAcademicYears(
        AcademicYearRepository $repository,
        string $selectedId = ''
    ): Collection {
        $selectedId = trim($selectedId);

        return collect($repository->fetchAll())
            ->filter(fn ($year) => trim((string) ($year['Academic_Year_ID'] ?? '')) !== '')
            ->filter(function ($year) use ($selectedId): bool {
                $id = trim((string) ($year['Academic_Year_ID'] ?? ''));
                $active = strtoupper(trim((string) ($year['Is_Active'] ?? 'TRUE'))) !== 'FALSE';

                return $active || ($selectedId !== '' && $id === $selectedId);
            })
            ->unique('Academic_Year_ID')
            ->sortByDesc(fn ($year) => (string) ($year['Start_Date'] ?? $year['Academic_Year_ID'] ?? ''))
            ->values();
    }
}
