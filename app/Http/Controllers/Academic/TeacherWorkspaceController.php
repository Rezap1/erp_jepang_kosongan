<?php

namespace App\Http\Controllers\Academic;

use App\Helpers\AttendanceStatusHelper;
use App\Helpers\DateHelper;
use App\Helpers\ReportHelper;
use App\Http\Controllers\Controller;
use App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;
use App\Repositories\GoogleSheets\AssessmentRepository;
use App\Services\Academic\AssessmentConfigService;
use App\Services\Academic\AttendanceLegacyClassifier;
use App\Services\Academic\AttendanceService;
use App\Services\Academic\ScheduleService;
use App\Services\Academic\ScoreService;
use App\Services\Academic\SubjectService;
use App\Services\Attendance\AttendanceRequestService;
use App\Services\Core\AssignmentService;
use App\Services\Core\BatchService;
use App\Services\Core\ClassService;
use App\Services\Core\StudentService;
use App\Services\Core\TeacherService;
use App\Support\Academic\TeacherScopeResolver;
use App\Support\Reporting\HumanReadableResolver;
use Illuminate\Http\Request;

class TeacherWorkspaceController extends Controller
{
    protected $teacherService;

    protected $classService;

    protected $scheduleService;

    protected $studentService;

    protected $attendanceService;

    protected $attendanceRequestService;

    protected $subjectService;

    protected $batchService;

    protected $assignmentService;

    protected $scoreService;

    protected $assessmentConfigService;

    protected $teacherScopeResolver;

    public function __construct(
        TeacherService $teacherService,
        ClassService $classService,
        ScheduleService $scheduleService,
        StudentService $studentService,
        AttendanceService $attendanceService,
        AttendanceRequestService $attendanceRequestService,
        SubjectService $subjectService,
        BatchService $batchService,
        AssignmentService $assignmentService,
        ScoreService $scoreService,
        AssessmentConfigService $assessmentConfigService,
        TeacherScopeResolver $teacherScopeResolver
    ) {
        $this->teacherService = $teacherService;
        $this->classService = $classService;
        $this->scheduleService = $scheduleService;
        $this->studentService = $studentService;
        $this->attendanceService = $attendanceService;
        $this->attendanceRequestService = $attendanceRequestService;
        $this->subjectService = $subjectService;
        $this->batchService = $batchService;
        $this->assignmentService = $assignmentService;
        $this->scoreService = $scoreService;
        $this->assessmentConfigService = $assessmentConfigService;
        $this->teacherScopeResolver = $teacherScopeResolver;
    }

    private function getTeacherId()
    {
        $scope = $this->teacherScopeResolver->resolveForUser(auth()->user());

        return $scope['teacher_id'] ?? null;
    }

    private function verifyTeacherAccess()
    {
        $teacherId = $this->getTeacherId();
        if (! $teacherId) {
            abort(403, 'Profil Pengajar Belum Terhubung: Akun Anda belum dikaitkan dengan profil pengajar. Silakan hubungi Administrator.');
        }

        return $teacherId;
    }

    private function teacherScope(string $teacherId): array
    {
        return $this->teacherScopeResolver->resolveForTeacherId($teacherId);
    }

    public function schedule()
    {
        $teacherId = $this->verifyTeacherAccess();

        $scope = $this->teacherScope($teacherId);
        $schedules = collect($scope['schedules'] ?? collect())->values();

        $classesById = collect($this->classService->getAllClasses())->keyBy('Class_ID');
        $subjectsById = collect($this->subjectService->getAll())->keyBy('Subject_ID');

        $schedules = $schedules->map(function ($s) use ($classesById, $subjectsById) {
            $s['Class_Name'] = HumanReadableResolver::className($s['Class_ID'] ?? '', $classesById);
            $s['Subject_Name'] = HumanReadableResolver::subjectName($s['Subject_ID'] ?? '', $subjectsById);

            return $s;
        });

        return view('academic.teacher.schedule', compact('schedules', 'teacherId'));
    }

    public function myClasses()
    {
        $teacherId = $this->verifyTeacherAccess();

        $scope = $this->teacherScope($teacherId);
        $myClassIds = collect($scope['managed_class_ids'] ?? []);

        $allClasses = collect($this->classService->getAllClasses());
        $batchesById = collect($this->batchService->getAllBatches())->keyBy('Batch_ID');

        $classes = $allClasses->whereIn('Class_ID', $myClassIds)->map(function ($c) use ($batchesById) {
            $batch = $batchesById[$c['Batch_ID'] ?? ''] ?? null;
            $c['Batch_Name'] = $batch ? ($batch['Batch_Name'] ?? $c['Batch_ID']) : ($c['Batch_ID'] ?? '-');

            return $c;
        })->values();

        return view('academic.teacher.classes', compact('classes', 'teacherId'));
    }

    private function verifyClassAccess($teacherId, $classId, $schedules = null)
    {
        $scope = is_array($schedules) && array_key_exists('managed_class_ids', $schedules)
            ? $schedules
            : $this->teacherScope($teacherId);

        if (! in_array(trim((string) $classId), $scope['managed_class_ids'] ?? [], true)) {
            abort(403, 'Anda tidak memiliki akses ke kelas ini.');
        }
    }

    /**
     * Build the teacher-scoped attendance read model without trusting request
     * identifiers. Ownership is resolved from the authenticated teacher's
     * schedules before class-based attendance is grouped.
     */
    private function teacherAttendanceReadModel($teacherId, ?string $classFilter = null): array
    {
        $allSchedules = collect($this->scheduleService->getAll());
        $scope = $this->teacherScope($teacherId);
        $mySchedules = collect($scope['schedules'] ?? collect())->values();
        $teacherScheduleIds = $mySchedules->pluck('Schedule_ID')->filter()->unique()->values();
        $teacherClassIds = collect($scope['managed_class_ids'] ?? [])->values();

        $classes = collect($this->classService->getAllClasses())
            ->filter(function ($class) use ($teacherClassIds) {
                $isActive = strtoupper(trim((string) ($class['Is_Active'] ?? '')));

                return ($isActive === 'TRUE' || $isActive === '')
                    && $teacherClassIds->contains($class['Class_ID'] ?? '');
            })
            ->values();
        $studentRows = collect($this->studentService->getAllStudents());
        $legacyClassifier = new AttendanceLegacyClassifier;
        $attendanceRows = collect($this->attendanceService->getAll())
            ->filter(function ($attendance) use ($legacyClassifier, $classes, $allSchedules, $teacherClassIds, $teacherScheduleIds) {
                $classified = $legacyClassifier->classify($attendance, $classes, $allSchedules);
                if (in_array($classified['classification'], ['EMPLOYEE', 'UNKNOWN', 'AMBIGUOUS'], true)) {
                    return false;
                }
                if (empty($attendance['Student_ID'])) {
                    return false;
                }
                if ($classified['is_schedule_based']) {
                    return $teacherScheduleIds->contains($classified['schedule_id'] ?? '');
                }

                return $teacherClassIds->contains($classified['class_id'] ?? '');
            })
            ->sortBy(function ($attendance) {
                $type = strtoupper(trim((string) ($attendance['Attendance_Type'] ?? '')));

                return in_array($type, ['CLASS_QR', 'CLASS_MANUAL'], true) ? 0 : 1;
            })
            ->values();
        $dateFilter = request()->input('date', date('Y-m-d'));

        return [
            'groups' => $this->attendanceService->buildClassAttendanceGroups(
                $classes,
                $studentRows,
                $attendanceRows,
                $allSchedules,
                $dateFilter,
                null,
                $classFilter,
                null,
                null
            ),
            'classes' => $classes,
            'students' => $studentRows,
            'schedules' => $mySchedules,
            'teacherClassIds' => $teacherClassIds,
            'teacherScheduleIds' => $teacherScheduleIds,
            'dateFilter' => $dateFilter,
        ];
    }

    public function classStudents($classId)
    {
        $teacherId = $this->verifyTeacherAccess();
        $scope = $this->teacherScope($teacherId);
        $this->verifyClassAccess($teacherId, $classId, $scope);

        $cls = collect($this->classService->getAllClasses())->firstWhere('Class_ID', $classId);
        $className = $cls ? ($cls['Class_Name'] ?? $classId) : $classId;

        $students = collect($this->studentService->getAllStudents())
            ->whereIn('Student_ID', $scope['managed_students_by_class'][$classId] ?? [])
            ->map(function ($s) {
                return collect($s)->except(['Password', 'APP_KEY', 'HMAC', 'Token'])->toArray();
            })
            ->values();

        return view('academic.teacher.students', compact('students', 'className', 'classId'));
    }

    public function classAttendance($classId)
    {
        $teacherId = $this->verifyTeacherAccess();
        $readModel = $this->teacherAttendanceReadModel($teacherId, $classId);
        $this->verifyClassAccess($teacherId, $classId, $readModel);

        $cls = $readModel['classes']->firstWhere('Class_ID', $classId);
        $className = $cls ? ($cls['Class_Name'] ?? $classId) : $classId;

        $studentsById = collect($readModel['students'])->keyBy('Student_ID');
        $classesById = collect($this->classService->getAllClasses())->keyBy('Class_ID');
        $schedulesById = collect($this->scheduleService->getAll())->keyBy('Schedule_ID');
        $subjectsById = collect($this->subjectService->getAll())->keyBy('Subject_ID');

        $attendanceRequests = collect($this->attendanceRequestService->getTeacherRequests(auth()->user()))
            ->filter(function ($request) use ($studentsById, $classId) {
                $student = $studentsById[$request['Student_ID'] ?? ''] ?? null;

                return $student && ($student['Class_ID'] ?? '') === $classId;
            })
            ->map(fn ($request) => $this->enrichTeacherAttendanceRequest($request, $studentsById, $classesById, $schedulesById, $subjectsById))
            ->sortByDesc('Created_At')
            ->values();

        return view('academic.teacher.attendance', [
            'attendanceGroups' => $readModel['groups'],
            'dateFilter' => $readModel['dateFilter'],
            'attendanceRequests' => $attendanceRequests,
            'className' => $className,
            'classId' => $classId,
        ]);
    }

    public function reports()
    {
        $teacherId = $this->verifyTeacherAccess();

        return view('academic.teacher.reports', compact('teacherId'));
    }

    public function exportAttendancesCsv()
    {
        return $this->csvResponse(
            'laporan_kehadiran_guru.csv',
            $this->teacherAttendanceHeaders(),
            $this->teacherAttendanceRows()
        );
    }

    public function exportAttendancesPdf()
    {
        return $this->teacherReportResponse('pdf', 'Laporan Kehadiran Guru', $this->teacherAttendanceHeaders(), $this->teacherAttendanceRows());
    }

    public function printAttendances()
    {
        return $this->teacherReportResponse('print', 'Laporan Kehadiran Guru', $this->teacherAttendanceHeaders(), $this->teacherAttendanceRows());
    }

    public function exportScoresCsv()
    {
        return $this->csvResponse(
            'laporan_nilai_guru.csv',
            $this->teacherScoreHeaders(),
            $this->teacherScoreRows()
        );
    }

    public function exportScoresPdf(Request $request)
    {
        return $this->teacherScoreReportResponse('pdf', $request);
    }

    public function printScores(Request $request)
    {
        return $this->teacherScoreReportResponse('print', $request);
    }

    private function teacherScoreReportResponse(string $format, Request $request)
    {
        $filters = $request->validate([
            'mode' => ['required', 'string', 'in:student,class'],
            'student_id' => ['nullable', 'string', 'required_if:mode,student', 'prohibited_unless:mode,student'],
            'class_id' => ['nullable', 'string', 'required_if:mode,class', 'prohibited_unless:mode,class'],
            'date' => ['nullable', 'date_format:Y-m-d', 'required_if:mode,class', 'prohibited_unless:mode,class'],
        ], [
            'mode.required' => 'Jenis laporan wajib dipilih.',
            'mode.in' => 'Jenis laporan tidak valid.',
            'student_id.required_if' => 'Siswa wajib dipilih untuk laporan nilai per siswa.',
            'student_id.prohibited_unless' => 'Siswa hanya boleh dipilih untuk laporan nilai per siswa.',
            'class_id.required_if' => 'Kelas wajib dipilih untuk laporan nilai satu kelas.',
            'class_id.prohibited_unless' => 'Kelas hanya boleh dipilih untuk laporan nilai satu kelas.',
            'date.required_if' => 'Tanggal wajib dipilih untuk laporan nilai satu kelas.',
            'date.date_format' => 'Format tanggal laporan harus YYYY-MM-DD.',
            'date.prohibited_unless' => 'Tanggal hanya boleh dipilih untuk laporan nilai satu kelas.',
        ]);

        $teacherId = $this->verifyTeacherAccess();
        $scope = $this->scoreService->getTeacherScoreScope($teacherId);
        $mode = $filters['mode'];
        $studentId = trim((string) ($filters['student_id'] ?? ''));
        $classId = trim((string) ($filters['class_id'] ?? ''));
        $reportDate = trim((string) ($filters['date'] ?? ''));

        if ($mode === 'student' && ! in_array($studentId, $scope['teaching_student_ids'] ?? [], true)) {
            abort(403, 'Siswa berada di luar ruang lingkup pengajaran Anda.');
        }
        if ($mode === 'class' && ! in_array($classId, $scope['teaching_class_ids'] ?? [], true)) {
            abort(403, 'Kelas berada di luar ruang lingkup pengajaran Anda.');
        }

        $teacherRows = collect($this->teacherService->getAllTeachers())->keyBy('Teacher_ID');
        $teacherName = HumanReadableResolver::teacherName($teacherId, $teacherRows);
        $classesById = collect($this->classService->getAllClasses())->keyBy('Class_ID');
        $subjectsById = collect($this->subjectService->getAll())->keyBy('Subject_ID');
        $schedulesById = collect($this->scheduleService->getAll())->keyBy('Schedule_ID');
        $studentsById = collect($this->studentService->getAllStudents())->keyBy('Student_ID');
        $assessmentsById = collect(app(AssessmentRepositoryInterface::class)->getAll())->keyBy('Assessment_ID');
        $assignmentsById = collect($this->assignmentService->getAll())->keyBy('Assignment_ID');

        $scores = $this->teacherScopedScores($teacherId, $scope)
            ->filter(fn ($score) => $this->scoreBelongsToResolvedScope((array) $score, $scope, $schedulesById))
            ->filter(function ($score) use ($mode, $studentId, $classId, $reportDate, $schedulesById) {
                if ($mode === 'student') {
                    return trim((string) ($score['Student_ID'] ?? '')) === $studentId;
                }

                return $this->scoreClassId((array) $score, $schedulesById) === $classId
                    && $this->scoreReportDate((array) $score) === $reportDate;
            });

        $rows = $scores->map(function ($score) use ($classesById, $subjectsById, $schedulesById, $studentsById, $assessmentsById, $assignmentsById) {
            $schedule = (array) $schedulesById->get(trim((string) ($score['Schedule_ID'] ?? '')), []);
            $resolvedClassId = trim((string) ($schedule['Class_ID'] ?? $score['Class_ID'] ?? ''));
            $subjectId = trim((string) ($schedule['Subject_ID'] ?? $score['Subject_ID'] ?? ''));
            $details = $score['Parsed_Details'] ?? $this->scoreService->parseEvaluationDetails($score);
            $details = is_array($details) ? $details : [];
            $assessmentId = trim((string) ($score['Assessment_ID'] ?? ''));
            $assignmentId = trim((string) ($score['Assignment_ID'] ?? ''));
            $title = $assessmentId !== ''
                ? HumanReadableResolver::assessmentTitle($assessmentId, $assessmentsById)
                : ($assignmentId !== '' ? HumanReadableResolver::assignmentTitle($assignmentId, $assignmentsById) : 'Penilaian');

            return [
                'date' => $this->scoreReportDate($score),
                'student_id' => trim((string) ($score['Student_ID'] ?? '')),
                'student_name' => HumanReadableResolver::studentName($score['Student_ID'] ?? '', $studentsById),
                'student_number' => data_get($studentsById->get($score['Student_ID'] ?? '', []), 'Student_Number', '-'),
                'class_name' => HumanReadableResolver::className($resolvedClassId, $classesById),
                'subject_name' => HumanReadableResolver::subjectName($subjectId, $subjectsById),
                'category' => $this->assessmentConfigService->categoryLabel($score['Assessment_Category'] ?? ''),
                'title' => $title,
                'score' => $score['Score'] ?? $score['Score_Value'] ?? '-',
                'grade' => trim((string) ($score['Grade'] ?? '')) ?: '-',
                'status' => $this->scoreStatusLabel($score['Status'] ?? ''),
                'detail_lines' => $this->scoreDetailLines($details),
                'details' => $this->summarizeScoreDetails($details),
                'notes' => trim((string) ($details['notes'] ?? '')),
            ];
        })->sortBy(fn ($row) => ($row['date'] ?? '').'|'.($row['student_name'] ?? '').'|'.($row['subject_name'] ?? ''))->values();

        $rosterIds = $mode === 'student'
            ? collect([$studentId])
            : collect(data_get($scope, 'teaching_students_by_class.'.$classId, []));
        if ($mode === 'class' && $rosterIds->isEmpty()) {
            $rosterIds = $studentsById->filter(fn ($student) => trim((string) ($student['Class_ID'] ?? '')) === $classId)->keys();
        }
        $rosterIds = $rosterIds->filter(fn ($id) => in_array(trim((string) $id), $scope['teaching_student_ids'] ?? [], true))->map(fn ($id) => trim((string) $id))->unique()->values();
        $studentGroups = $rosterIds->map(function ($id) use ($studentsById, $rows, $classesById, $classId) {
            $student = (array) $studentsById->get($id, []);
            $studentClassId = trim((string) ($student['Class_ID'] ?? $classId));

            return [
                'student_id' => $id,
                'student_name' => HumanReadableResolver::studentName($id, $studentsById),
                'student_number' => trim((string) ($student['Student_Number'] ?? '')) ?: '-',
                'class_name' => HumanReadableResolver::className($studentClassId, $classesById),
                'records' => $rows->where('student_id', $id)->values(),
            ];
        })->sortBy('student_name')->values();

        $selectedStudent = $mode === 'student' ? (array) $studentGroups->first() : [];
        $selectedClassName = $mode === 'class' ? HumanReadableResolver::className($classId, $classesById) : '';
        $authorizedClassLabels = collect($scope['teaching_class_ids'] ?? [])
            ->map(fn ($id) => HumanReadableResolver::className($id, $classesById))
            ->filter()->unique()->implode(', ');
        $authorizedSubjectLabels = collect($scope['subject_ids'] ?? [])
            ->map(fn ($id) => HumanReadableResolver::subjectName($id, $subjectsById))
            ->filter()->unique()->implode(', ');
        $teachingScopeLabel = 'Kelas: '.($authorizedClassLabels ?: '-')
            .' | Mata pelajaran: '.($authorizedSubjectLabels ?: '-');
        $title = $mode === 'student' ? 'Laporan Nilai Siswa' : 'Laporan Nilai Kelas';
        $filename = $mode === 'student'
            ? 'LAPORAN-NILAI-'.($selectedStudent['student_name'] ?? $studentId).'-'.now()->format('Ymd')
            : 'LAPORAN-NILAI-'.$selectedClassName.'-'.str_replace('-', '', $reportDate);
        $emptyMessage = $mode === 'student'
            ? 'Belum ada data nilai untuk siswa ini.'
            : 'Belum ada data nilai pada tanggal yang dipilih.';
        $scopeLabel = $mode === 'student'
            ? 'Siswa: '.($selectedStudent['student_name'] ?? $studentId)
            : 'Kelas: '.$selectedClassName.' | Tanggal: '.DateHelper::format($reportDate, 'd F Y');

        return ReportHelper::export($format, $title, $rows, [
            'filename' => $filename,
            'scopeLabel' => $scopeLabel,
            'teachingScopeLabel' => $teachingScopeLabel,
            'teacherName' => $teacherName,
            'reportMode' => $mode,
            'selectedStudent' => $selectedStudent,
            'selectedClassName' => $selectedClassName,
            'reportDate' => $reportDate,
            'studentCount' => $studentGroups->count(),
            'studentGroups' => $studentGroups,
            'emptyMessage' => $emptyMessage,
            'summary' => '<tr><td>Total Siswa</td><td>: '.$studentGroups->count().'</td><td>Total Data Nilai</td><td>: '.$rows->count().'</td></tr>',
        ], 'pdf.teacher_score_report', [], null, true);
    }

    private function teacherAttendanceHeaders(): array
    {
        return ['Tanggal', 'Siswa', 'Kelas', 'Jadwal', 'Status', 'Masuk', 'Pulang', 'Catatan'];
    }

    private function teacherAttendanceRows(): array
    {
        $teacherId = $this->verifyTeacherAccess();
        $scope = $this->teacherScope($teacherId);
        $allSchedules = collect($this->scheduleService->getAll());
        $schedulesById = $allSchedules->keyBy('Schedule_ID');
        $mySchedules = collect($scope['schedules'] ?? collect())->values();
        $myScheduleIds = $mySchedules->pluck('Schedule_ID')->filter()->values();
        $myClassIds = collect($scope['managed_class_ids'] ?? [])->values();
        $classesById = collect($this->classService->getAllClasses())->keyBy('Class_ID');
        $studentsById = collect($this->studentService->getAllStudents())->keyBy('Student_ID');
        $subjectsById = collect($this->subjectService->getAll())->keyBy('Subject_ID');
        $teachersById = collect($this->teacherService->getAllTeachers())->keyBy('Teacher_ID');
        $classifier = new AttendanceLegacyClassifier;

        $rows = collect($this->attendanceService->getAll())
            ->filter(function ($attendance) use ($classifier, $classesById, $allSchedules, $myScheduleIds, $myClassIds) {
                $classified = $classifier->classify($attendance, $classesById->values(), $allSchedules);
                if (in_array($classified['classification'], ['EMPLOYEE', 'UNKNOWN', 'AMBIGUOUS'], true)) {
                    return false;
                }
                if (empty($attendance['Student_ID'])) {
                    return false;
                }
                if ($classified['is_schedule_based']) {
                    return $myScheduleIds->contains($classified['schedule_id'] ?? '');
                }

                return $myClassIds->contains($classified['class_id'] ?? '');
            })
            ->map(function ($attendance) use ($classifier, $studentsById, $classesById, $allSchedules, $schedulesById, $subjectsById, $teachersById) {
                $classified = $classifier->classify($attendance, $classesById->values(), $allSchedules);
                $scheduleId = $classified['schedule_id'] ?? '';
                $classId = $classified['class_id'] ?? ($attendance['Class_ID'] ?? '');

                return [
                    $attendance['Attendance_Date'] ?? $attendance['Date'] ?? '-',
                    HumanReadableResolver::studentName($attendance['Student_ID'] ?? '', $studentsById),
                    HumanReadableResolver::className($classId, $classesById),
                    $scheduleId ? HumanReadableResolver::scheduleLabel($scheduleId, $schedulesById, $classesById, $subjectsById, $teachersById) : 'Absensi Kelas / QR',
                    AttendanceStatusHelper::label($attendance['Status'] ?? ''),
                    $attendance['Time_In'] ?? $attendance['Check_In_Time'] ?? '-',
                    $attendance['Time_Out'] ?? $attendance['Check_Out_Time'] ?? '-',
                    $attendance['Notes'] ?? '-',
                ];
            })
            ->values()
            ->all();

        return $rows;
    }

    private function teacherScoreHeaders(): array
    {
        return ['Tanggal', 'Siswa', 'Kategori', 'Penilaian', 'Nilai', 'Grade', 'Status', 'Detail'];
    }

    private function teacherScoreRows(): array
    {
        $teacherId = $this->verifyTeacherAccess();
        $studentsById = collect($this->studentService->getAllStudents())
            ->keyBy('Student_ID');
        $assessmentsById = collect(app(AssessmentRepository::class)->fetchAll())
            ->keyBy('Assessment_ID');
        $assignmentsById = collect($this->assignmentService->getAll())
            ->keyBy('Assignment_ID');

        $rows = $this->teacherScopedScores($teacherId)
            ->map(function ($score) use ($studentsById, $assessmentsById, $assignmentsById) {
                $details = $score['Parsed_Details'] ?? $this->scoreService->parseEvaluationDetails($score);
                $summary = $this->summarizeScoreDetails($details);
                $assessmentId = trim((string) ($score['Assessment_ID'] ?? ''));
                $assignmentId = trim((string) ($score['Assignment_ID'] ?? ''));
                $title = $assessmentId !== ''
                    ? HumanReadableResolver::assessmentTitle($assessmentId, $assessmentsById)
                    : ($assignmentId !== '' ? HumanReadableResolver::assignmentTitle($assignmentId, $assignmentsById) : 'Penilaian tidak ditemukan');

                return [
                    $score['Created_At'] ?? $score['Assessment_Date'] ?? '-',
                    HumanReadableResolver::studentName($score['Student_ID'] ?? '', $studentsById),
                    $this->assessmentConfigService->categoryLabel($score['Assessment_Category'] ?? ''),
                    $title,
                    $score['Score'] ?? $score['Score_Value'] ?? '-',
                    $score['Grade'] ?? '-',
                    $score['Status'] ?? '-',
                    $summary,
                ];
            })
            ->values()
            ->all();

        return $rows;
    }

    private function teacherReportResponse(string $format, string $title, array $headers, array $rows)
    {
        return ReportHelper::export(
            $format,
            $title,
            collect($rows),
            ['summary' => '<tr><td>Total Data</td><td>: '.count($rows).'</td></tr>'],
            'pdf.generic_table',
            $headers,
            fn ($row) => $row,
            true
        );
    }

    private function translateAttendanceStatus($status): string
    {
        return AttendanceStatusHelper::label($status);
    }

    private function summarizeScoreDetails(array $details): string
    {
        $summary = [];
        foreach ($details as $key => $value) {
            if (in_array(strtolower((string) $key), ['category', 'notes', 'subject_id'], true)) {
                continue;
            }
            $displayValue = is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
            $summary[] = ucwords(str_replace('_', ' ', (string) $key)).': '.$displayValue;
        }

        return implode('; ', $summary) ?: ($details['notes'] ?? '-');
    }

    private function scoreBelongsToResolvedScope(array $score, array $scope, $schedulesById): bool
    {
        $studentId = trim((string) ($score['Student_ID'] ?? ''));
        if ($studentId === '' || ! in_array($studentId, $scope['teaching_student_ids'] ?? [], true)) {
            return false;
        }

        $scheduleId = trim((string) ($score['Schedule_ID'] ?? ''));
        $schedule = (array) $schedulesById->get($scheduleId, []);
        if ($scheduleId !== '') {
            if (! in_array($scheduleId, $scope['schedule_ids'] ?? [], true)) {
                return false;
            }
            if (! in_array($studentId, data_get($scope, 'schedule_student_ids.'.$scheduleId, []), true)) {
                return false;
            }
        } else {
            $assessmentId = trim((string) ($score['Assessment_ID'] ?? $score['Assignment_ID'] ?? ''));
            if ($assessmentId === '' || ! in_array($assessmentId, $scope['assessment_ids'] ?? [], true)) {
                return false;
            }
        }

        $classId = trim((string) ($schedule['Class_ID'] ?? $score['Class_ID'] ?? ''));
        $subjectId = trim((string) ($schedule['Subject_ID'] ?? $score['Subject_ID'] ?? ''));

        return ($classId === '' || in_array($classId, $scope['teaching_class_ids'] ?? [], true))
            && ($subjectId === '' || in_array($subjectId, $scope['subject_ids'] ?? [], true));
    }

    private function scoreClassId(array $score, $schedulesById): string
    {
        $schedule = (array) $schedulesById->get(trim((string) ($score['Schedule_ID'] ?? '')), []);

        return trim((string) ($schedule['Class_ID'] ?? $score['Class_ID'] ?? ''));
    }

    private function scoreReportDate(array $score): string
    {
        $date = DateHelper::parse($score['Assessment_Date'] ?? $score['Date'] ?? $score['Created_At'] ?? null);

        return $date?->format('Y-m-d') ?? '';
    }

    private function scoreStatusLabel($status): string
    {
        return match (strtoupper(trim((string) $status))) {
            'PASS', 'PASSED', 'LULUS' => 'Lulus',
            'FAIL', 'FAILED', 'BELUM LULUS' => 'Belum Lulus',
            'COMPLETED', 'SELESAI' => 'Selesai',
            'PUBLISHED', 'PUBLISH', 'DIPUBLIKASIKAN' => 'Dipublikasikan',
            default => trim((string) $status) ?: '-',
        };
    }

    private function scoreDetailLines(array $details): array
    {
        $scale = [1 => 'Sangat Kurang', 2 => 'Kurang', 3 => 'Cukup', 4 => 'Baik', 5 => 'Sangat Baik'];
        $lines = [];
        foreach ($details as $key => $value) {
            if (in_array(strtolower((string) $key), ['category', 'notes', 'subject_id'], true)) {
                continue;
            }
            $label = ucwords(str_replace('_', ' ', (string) $key));
            $display = is_array($value) ? implode(', ', array_map('strval', $value)) : trim((string) $value);
            if (is_numeric($value) && isset($scale[(int) $value])) {
                $display .= ' - '.$scale[(int) $value];
            }
            $lines[] = ['label' => $label, 'value' => $display ?: '-'];
        }

        return $lines;
    }

    private function csvResponse(string $filename, array $headers, array $rows)
    {
        $file = fopen('php://temp', 'r+');
        $sanitize = [ReportHelper::class, 'sanitizeCsvCell'];

        fputcsv($file, array_map($sanitize, $headers));
        foreach ($rows as $row) {
            fputcsv($file, array_map($sanitize, $row));
        }

        rewind($file);
        $csv = stream_get_contents($file);
        fclose($file);

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    // ==========================================
    // OPERATIONAL ROUTES
    // ==========================================

    public function students()
    {
        $teacherId = $this->verifyTeacherAccess();

        $scope = $this->teacherScope($teacherId);

        $students = collect($this->studentService->getAllStudents())
            ->whereIn('Student_ID', $scope['managed_student_ids'] ?? [])
            ->map(function ($s) {
                return collect($s)->except(['Password', 'APP_KEY', 'HMAC', 'Token'])->toArray();
            })
            ->values();

        $classesById = collect($this->classService->getAllClasses())->keyBy('Class_ID');

        $students = $students->map(function ($s) use ($classesById) {
            $s['Class_Name'] = HumanReadableResolver::className($s['Class_ID'] ?? '', $classesById);

            return $s;
        });

        return view('academic.teacher.students', compact('students', 'teacherId'));
    }

    public function attendances()
    {
        $teacherId = $this->verifyTeacherAccess();
        $classFilter = request()->input('class_id');
        if (trim((string) $classFilter) !== '') {
            $this->verifyClassAccess($teacherId, trim((string) $classFilter));
        }
        $readModel = $this->teacherAttendanceReadModel($teacherId, $classFilter);
        $classOptions = $readModel['classes']->mapWithKeys(function ($class) {
            $classId = trim((string) ($class['Class_ID'] ?? ''));
            $label = trim((string) ($class['Class_Name'] ?? '')) ?: $classId;

            return $classId === '' ? [] : [$classId => $label];
        })->all();

        return view('academic.teacher.attendances', [
            'attendanceGroups' => $readModel['groups'],
            'dateFilter' => $readModel['dateFilter'],
            'classOptions' => $classOptions,
            'classFilter' => trim((string) $classFilter),
            'teacherId' => $teacherId,
        ]);
    }

    public function attendanceRequests()
    {
        $teacherId = $this->verifyTeacherAccess();

        $studentsById = collect($this->studentService->getAllStudents())->keyBy('Student_ID');
        $classesById = collect($this->classService->getAllClasses())->keyBy('Class_ID');
        $schedulesById = collect($this->scheduleService->getAll())->keyBy('Schedule_ID');
        $subjectsById = collect($this->subjectService->getAll())->keyBy('Subject_ID');

        $attendanceRequests = collect($this->attendanceRequestService->getTeacherRequests(auth()->user()))
            ->map(fn ($request) => $this->enrichTeacherAttendanceRequest($request, $studentsById, $classesById, $schedulesById, $subjectsById))
            ->sortByDesc('Created_At')
            ->values();

        return view('academic.teacher.attendance-requests', compact('attendanceRequests', 'teacherId'));
    }

    public function scores()
    {
        $teacherId = $this->verifyTeacherAccess();
        $scope = $this->scoreService->getTeacherScoreScope($teacherId);
        $assessmentConfigs = collect($this->assessmentConfigService->getActiveCategories())
            ->keyBy(fn ($config) => strtoupper(trim((string) ($config['Category_ID'] ?? ''))))
            ->toArray();
        $classIds = array_values($scope['teaching_class_ids'] ?? []);
        $reportClasses = collect($scope['classes'] ?? [])
            ->filter(fn ($class) => in_array(trim((string) ($class['Class_ID'] ?? '')), $classIds, true))
            ->map(fn ($class) => [
                'Class_ID' => trim((string) ($class['Class_ID'] ?? '')),
                'Class_Name' => trim((string) ($class['Class_Name'] ?? $class['Name'] ?? $class['Class_ID'] ?? 'Kelas')),
            ]);
        if ($reportClasses->isEmpty()) {
            $reportClasses = collect($classIds)->map(fn ($id) => [
                'Class_ID' => trim((string) $id),
                'Class_Name' => trim((string) $id),
            ]);
        }
        $reportClasses = $reportClasses->sortBy('Class_Name')->values();
        $requestedClass = trim((string) request()->query('class'));
        if ($requestedClass !== '' && ! in_array($requestedClass, $classIds, true)) {
            abort(403, 'Anda tidak memiliki akses ke kelas ini.');
        }
        $selectedClass = $requestedClass !== ''
            ? $requestedClass
            : trim((string) ($reportClasses->first()['Class_ID'] ?? ''));

        $students = collect($scope['students'] ?? []);
        if ($students->isEmpty()) {
            $students = collect($this->studentService->getAllStudents())
                ->whereIn('Student_ID', $scope['teaching_student_ids'] ?? [])
                ->values();
        }
        $studentsById = $students->keyBy('Student_ID');
        $schedules = collect($scope['schedules'] ?? []);
        $schedulesById = $schedules->keyBy('Schedule_ID');
        $subjectsById = $schedules->isEmpty()
            ? collect()
            : collect($this->subjectService->getAll())->keyBy('Subject_ID');
        $singleClassFallback = count($classIds) === 1 ? $classIds[0] : '';

        $scores = $this->teacherScopedScores($teacherId, $scope)->map(function ($score) use ($studentsById, $schedulesById, $subjectsById, $assessmentConfigs, $singleClassFallback) {
            $student = $studentsById[$score['Student_ID'] ?? ''] ?? null;
            $schedule = $schedulesById[$score['Schedule_ID'] ?? ''] ?? null;
            $details = json_decode((string) ($score['Evaluation_Details'] ?? ''), true);
            $details = is_array($details) ? $details : [];
            $classId = trim((string) ($score['Class_ID'] ?? $schedule['Class_ID'] ?? $student['Class_ID'] ?? $singleClassFallback));
            $subjectId = trim((string) ($score['Subject_ID'] ?? $schedule['Subject_ID'] ?? $details['subject_id'] ?? ''));
            $category = strtoupper(trim((string) ($score['Assessment_Category'] ?? '')));
            $config = $assessmentConfigs[$category] ?? null;
            $date = trim((string) ($score['Assessment_Date'] ?? $score['Date'] ?? ''));
            if ($date === '') {
                $date = substr((string) ($score['Created_At'] ?? ''), 0, 10);
            }

            $score['Student_Name'] = $student
                ? ($student['Full_Name'] ?? $student['Username'] ?? 'Data siswa tidak ditemukan')
                : 'Data siswa tidak ditemukan';
            $score['Class_ID'] = $classId;
            $score['Subject_ID'] = $subjectId;
            $score['Subject_Name'] = HumanReadableResolver::subjectName($subjectId, $subjectsById);
            $score['Category_Label'] = $config['Category_Name'] ?? ($category ?: 'Penilaian');
            $score['Display_Date'] = $date;

            return $score;
        });

        if ($selectedClass !== '') {
            $scores = $scores->where('Class_ID', $selectedClass);
        }

        $subjectOptions = $schedules
            ->when($selectedClass !== '', fn ($rows) => $rows->where('Class_ID', $selectedClass))
            ->pluck('Subject_ID')
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->map(fn ($id) => ['Subject_ID' => $id, 'Subject_Name' => HumanReadableResolver::subjectName($id, $subjectsById)])
            ->sortBy('Subject_Name')
            ->values();
        $selectedSubject = trim((string) request()->query('subject'));
        if ($selectedSubject !== '' && ! $subjectOptions->contains('Subject_ID', $selectedSubject)) {
            abort(403, 'Mata pelajaran berada di luar scope kelas Anda.');
        }
        if ($selectedSubject !== '') {
            $scores = $scores->where('Subject_ID', $selectedSubject);
        }

        $selectedCategory = strtolower(trim((string) request()->query('category', 'all')));
        abort_unless(in_array($selectedCategory, ['all', 'assessment', 'ujian_bab'], true), 422, 'Filter kategori penilaian tidak valid.');
        if ($selectedCategory === 'ujian_bab') {
            $scores = $scores->filter(fn ($score) => strtoupper(trim((string) ($score['Assessment_Category'] ?? ''))) === 'UJIAN_BAB');
        } elseif ($selectedCategory === 'assessment') {
            $scores = $scores->reject(fn ($score) => strtoupper(trim((string) ($score['Assessment_Category'] ?? ''))) === 'UJIAN_BAB');
        }
        $scores = $scores->sortByDesc(fn ($score) => ($score['Display_Date'] ?? '').'|'.($score['Created_At'] ?? ''))->values();

        $selectedClassName = $reportClasses->firstWhere('Class_ID', $selectedClass)['Class_Name'] ?? $selectedClass;
        $selectedSubjectName = $selectedSubject !== ''
            ? ($subjectOptions->firstWhere('Subject_ID', $selectedSubject)['Subject_Name'] ?? $selectedSubject)
            : 'Semua Mata Pelajaran';
        $classStudentIds = collect($scope['teaching_students_by_class'][$selectedClass] ?? [])
            ->map(fn ($id) => trim((string) $id))->filter()->unique();
        $summary = [
            'students' => $classStudentIds->count(),
            'assessed' => $scores->pluck('Student_ID')->filter()->unique()->count(),
        ];
        $summary['unassessed'] = max(0, $summary['students'] - $summary['assessed']);
        $scoreGroups = $scores->groupBy(fn ($score) => ($score['Category_Label'] ?? 'Penilaian').'|'.($score['Display_Date'] ?? ''));

        $reportStudents = $studentsById
            ->filter(fn ($student, $id) => in_array(trim((string) $id), $scope['teaching_student_ids'] ?? [], true))
            ->map(fn ($student) => [
                'Student_ID' => trim((string) ($student['Student_ID'] ?? '')),
                'Full_Name' => trim((string) ($student['Full_Name'] ?? $student['Username'] ?? $student['Student_ID'] ?? 'Siswa')),
                'Student_Number' => trim((string) ($student['Student_Number'] ?? '')),
            ])->sortBy('Full_Name')->values();

        return view('academic.teacher.scores', compact(
            'scores', 'scoreGroups', 'teacherId', 'assessmentConfigs', 'reportStudents', 'reportClasses',
            'selectedClass', 'selectedClassName', 'subjectOptions', 'selectedSubject', 'selectedSubjectName',
            'selectedCategory', 'summary'
        ));
    }

    public function scoresCreate()
    {
        $teacherId = $this->verifyTeacherAccess();

        $scope = $this->teacherScope($teacherId);
        $myClassIds = $scope['teaching_class_ids'];

        $schedulesById = collect($this->scheduleService->getAll())
            ->filter(fn ($schedule) => in_array(trim((string) ($schedule['Schedule_ID'] ?? '')), $scope['schedule_ids'], true))
            ->keyBy('Schedule_ID');
        $schedules = $schedulesById->map(function ($schedule) {
            return [
                'Schedule_ID' => $schedule['Schedule_ID'],
                'Class_ID' => $schedule['Class_ID'] ?? '',
                'Subject_ID' => $schedule['Subject_ID'] ?? '',
                'label' => trim((string) ($schedule['Subject_Name'] ?? $schedule['Subject_ID'] ?? 'Jadwal'))
                    .' — '.trim((string) ($schedule['Class_ID'] ?? '')),
            ];
        })->values();

        $classesById = collect($this->classService->getAllClasses())->keyBy('Class_ID');
        $subjectsById = collect($this->subjectService->getAll())->keyBy('Subject_ID');
        $schedules = $schedules->map(function ($schedule) use ($schedulesById, $classesById, $subjectsById) {
            $schedule['label'] = HumanReadableResolver::scheduleLabel(
                $schedule['Schedule_ID'] ?? '',
                $schedulesById,
                $classesById,
                $subjectsById
            );

            return $schedule;
        });
        $classes = collect($myClassIds)->map(function ($cid) use ($classesById) {
            return [
                'Class_ID' => $cid,
                'Class_Name' => HumanReadableResolver::className($cid, $classesById),
            ];
        });

        $students = collect($this->studentService->getAllStudents())
            ->whereIn('Student_ID', $scope['teaching_student_ids'] ?? [])
            ->map(function ($s) use ($classesById) {
                $s['Class_Name'] = HumanReadableResolver::className($s['Class_ID'] ?? '', $classesById);

                return collect($s)->only(['Student_ID', 'Full_Name', 'Username', 'Class_ID', 'Class_Name'])->toArray();
            })
            ->values();

        $studentsByClass = collect($scope['teaching_students_by_class'] ?? [])->map(fn ($ids) => array_values($ids))->all();
        $studentsBySchedule = collect($scope['schedule_student_ids'] ?? [])->map(fn ($ids) => array_values($ids))->all();
        $assessmentConfigs = $this->assessmentConfigService->getActiveCategories();

        return view('academic.teacher.scores-create', compact('classes', 'students', 'schedules', 'studentsByClass', 'studentsBySchedule', 'teacherId', 'assessmentConfigs'));
    }

    public function scoresStore(Request $request)
    {
        $teacherId = $this->verifyTeacherAccess();

        $validated = $request->validate([
            'Student_ID' => 'required|string',
            'Schedule_ID' => 'required|string',
            'Assessment_Category' => 'required|string',
            'Date' => 'required|date',
            'Assessment_ID' => 'nullable|string',
            'Score_ID' => ['nullable', 'string', 'regex:/^SCR-[0-9a-f-]{36}$/i'],
            'Score' => 'nullable|numeric|min:0|max:100',
            'Score_Value' => 'nullable|numeric|min:0|max:100',
            'Notes' => 'nullable|string|max:2000',
        ]);

        $category = strtoupper(trim((string) $validated['Assessment_Category']));
        $categoryConfig = $this->assessmentConfigService->getCategoryConfig($category);
        if (! $categoryConfig || (! $this->assessmentConfigService->isNumericCategory($category) && empty($this->assessmentConfigService->getAspects($category)))) {
            return redirect()->back()->with('error', 'Kategori penilaian belum tersedia di MASTER_ASSESSMENT_CONFIG.')->withInput();
        }

        $studentId = trim((string) $validated['Student_ID']);
        $scope = $this->scoreService->getTeacherScoreScope($teacherId);
        $scheduleId = trim((string) $validated['Schedule_ID']);
        if (! in_array($scheduleId, $scope['schedule_ids'], true)
            || ! $this->scoreService->isStudentInSchedule($studentId, $scheduleId, $scope)) {
            abort(403, 'Siswa atau jadwal tidak berada dalam scope pengajaran Anda.');
        }

        $assessmentId = trim((string) ($validated['Assessment_ID'] ?? ''));
        if ($assessmentId !== '' && ! in_array($assessmentId, $scope['assessment_ids'], true)) {
            abort(403, 'Assessment tidak berada dalam scope pengajaran Anda.');
        }

        // Validate aspects against SSOT
        $data = $request->except(['_token', '_method']);
        unset($data['Class_ID'], $data['Subject_ID']);
        // Ownership identity is always resolved from the authenticated actor;
        // a client-supplied Teacher_ID is never evidence of authorization.
        $data['Teacher_ID'] = $teacherId;
        $data['Student_ID'] = $studentId;
        $data['Schedule_ID'] = $scheduleId;
        $data['Assessment_Category'] = $category;
        $data['Assessment_Date'] = $validated['Date'];
        $aspectKeys = collect($data)->except([
            'Student_ID', 'Teacher_ID', 'Assessment_Category', 'Date',
            'Assessment_Date', 'Assessment_ID', 'Score_ID', 'Notes',
            'Schedule_ID', 'Class_ID', 'Subject_ID',
        ])->keys()->toArray();
        $evaluationDetails = [];
        foreach ($aspectKeys as $key) {
            if (isset($data[$key]) && $data[$key] !== '') {
                $evaluationDetails[$key] = $data[$key];
            }
        }

        $schedule = $this->scheduleService->getById($scheduleId);
        if (! $schedule) {
            return redirect()->back()->with('error', 'Jadwal pengajaran tidak ditemukan.')->withInput();
        }
        $data['Class_ID'] = trim((string) ($schedule['Class_ID'] ?? ''));
        $data['Subject_ID'] = trim((string) ($schedule['Subject_ID'] ?? ''));

        if ($this->assessmentConfigService->isNumericCategory($category)) {
            $scoreInput = $data['Score'] ?? $data['Score_Value'] ?? null;
            if (trim((string) $scoreInput) === '') {
                return redirect()->back()->with('error', 'Nilai Ujian Bab harus berupa angka.')->withInput();
            }
        } elseif (! empty($evaluationDetails)) {
            $isValid = $this->assessmentConfigService->validateAspectPayload($category, $evaluationDetails);
            if (! $isValid) {
                return redirect()->back()->with('error', 'Aspek penilaian tidak valid atau tidak terdaftar untuk kategori ini.')->withInput();
            }
        } else {
            return redirect()->back()->with('error', 'Seluruh aspek penilaian wajib diisi.')->withInput();
        }

        try {
            // We pass the dynamic data to the score service.
            // The score service needs to properly format it into Evaluation_Details JSON.
            $data['Teacher_ID'] = $teacherId;
            $this->scoreService->create($data);

            return redirect()->route('teacher.workspace.scores')->with('success', 'Penilaian berhasil disimpan.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menyimpan penilaian: '.$this->safeExceptionMessage($e))->withInput();
        }
    }

    public function scoresEdit(string $id)
    {
        $teacherId = $this->verifyTeacherAccess();
        $score = $this->scoreService->getById($id);
        $scope = $this->scoreService->getTeacherScoreScope($teacherId);

        if (! $score || ! $this->scoreService->isScoreInTeacherScope($score, $teacherId, $scope)) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah nilai ini.');
        }

        $studentsById = collect($this->studentService->getAllStudents())->keyBy('Student_ID');
        $score['Student_Name'] = HumanReadableResolver::studentName($score['Student_ID'] ?? '', $studentsById);
        $assessmentConfigs = $this->assessmentConfigService->getActiveCategories();

        return view('academic.teacher.scores-edit', compact('score', 'teacherId', 'assessmentConfigs'));
    }

    public function scoresUpdate(Request $request, string $id)
    {
        $teacherId = $this->verifyTeacherAccess();
        $existing = $this->scoreService->getById($id);
        $scope = $this->scoreService->getTeacherScoreScope($teacherId);

        if (! $existing || ! $this->scoreService->isScoreInTeacherScope($existing, $teacherId, $scope)) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah nilai ini.');
        }

        $validated = $request->validate([
            'Assessment_Category' => 'required|string',
            'Date' => 'required|date',
            'Notes' => 'nullable|string|max:2000',
            'Score' => 'nullable|numeric|min:0|max:100',
            'Score_Value' => 'nullable|numeric|min:0|max:100',
        ]);
        $category = strtoupper(trim((string) $validated['Assessment_Category']));
        if (! $this->assessmentConfigService->getCategoryConfig($category)
            || (! $this->assessmentConfigService->isNumericCategory($category) && empty($this->assessmentConfigService->getAspects($category)))) {
            return redirect()->back()->with('error', 'Kategori penilaian belum tersedia di MASTER_ASSESSMENT_CONFIG.')->withInput();
        }

        // Scope/ownership references are immutable during a teacher edit;
        // never let a client move an existing score to another schedule or
        // assignment after the authorization check.
        $data = $request->except(['_token', '_method', 'Student_ID', 'Score_ID', 'Teacher_ID', 'Assessment_ID', 'Schedule_ID', 'Class_ID', 'Assignment_ID']);
        unset($data['Subject_ID']);
        $data['Student_ID'] = $existing['Student_ID'];
        $data['Teacher_ID'] = $teacherId;
        $data['Assessment_Category'] = $category;
        $data['Assessment_Date'] = $validated['Date'];
        $evaluationDetails = collect($data)
            ->except(['Student_ID', 'Teacher_ID', 'Assessment_Category', 'Date', 'Assessment_Date', 'Notes'])
            ->filter(fn ($value) => $value !== '' && $value !== null)
            ->toArray();

        $existingScheduleId = trim((string) ($existing['Schedule_ID'] ?? ''));
        $schedule = $existingScheduleId !== '' ? $this->scheduleService->getById($existingScheduleId) : null;
        if (! $schedule) {
            return redirect()->back()->with('error', 'Jadwal pengajaran tidak ditemukan.')->withInput();
        }
        $data['Class_ID'] = trim((string) ($schedule['Class_ID'] ?? ''));
        $data['Subject_ID'] = trim((string) ($schedule['Subject_ID'] ?? ''));

        if ($this->assessmentConfigService->isNumericCategory($category)) {
            $scoreInput = $data['Score'] ?? $data['Score_Value'] ?? null;
            if (trim((string) $scoreInput) === '') {
                return redirect()->back()->with('error', 'Nilai Ujian Bab harus berupa angka.')->withInput();
            }
        } elseif (! $this->assessmentConfigService->validateAspectPayload($category, $evaluationDetails)) {
            return redirect()->back()->with('error', 'Aspek penilaian tidak valid atau belum lengkap.')->withInput();
        }

        try {
            $this->scoreService->update($id, $data);

            return redirect()->route('teacher.workspace.scores')->with('success', 'Penilaian berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal memperbarui penilaian: '.$this->safeExceptionMessage($e))->withInput();
        }
    }

    public function assignments()
    {
        $teacherId = $this->verifyTeacherAccess();

        $scope = $this->teacherScope($teacherId);
        $myClassIds = $scope['teaching_class_ids'] ?? [];

        $assignments = collect($this->assignmentService->getAll())
            ->where('Teacher_ID', $teacherId)
            ->whereIn('Class_ID', $myClassIds)
            ->values();

        $classesById = collect($this->classService->getAllClasses())->keyBy('Class_ID');

        $assignments = $assignments->map(function ($a) use ($classesById) {
            $a['Class_Name'] = HumanReadableResolver::className($a['Class_ID'] ?? '', $classesById);

            return $a;
        });

        return view('academic.teacher.assignments', compact('assignments', 'teacherId'));
    }

    public function calendar()
    {
        $teacherId = $this->verifyTeacherAccess();

        $events = [];
        $allSchedules = collect($this->teacherScope($teacherId)['schedules'] ?? collect());
        $classesById = collect($this->classService->getAllClasses())->keyBy('Class_ID');
        $subjectsById = collect($this->subjectService->getAll())->keyBy('Subject_ID');
        $events = $allSchedules->map(function ($s) use ($classesById, $subjectsById) {
            return [
                'date' => $s['Date'] ?? date('Y-m-d'),
                'title' => HumanReadableResolver::subjectName($s['Subject_ID'] ?? '', $subjectsById)
                    .' - '.HumanReadableResolver::className($s['Class_ID'] ?? '', $classesById)
                    .' - '.($s['Topic'] ?? ''),
                'type' => $s['Type'] ?? 'Class',
            ];
        })->values()->toArray();

        return view('academic.teacher.calendar', compact('events', 'teacherId'));
    }

    private function teacherScopedScores(string $teacherId, ?array $scope = null)
    {
        $scope ??= $this->scoreService->getTeacherScoreScope($teacherId);

        return collect($this->scoreService->getAll())
            ->filter(fn ($score) => $this->scoreService->isScoreInTeacherScope($score, $teacherId, $scope))
            ->values();
    }

    private function enrichTeacherAttendanceRequest($request, $studentsById, $classesById, $schedulesById, $subjectsById): array
    {
        $request = is_array($request) ? $request : (array) $request;
        $student = $studentsById[$request['Student_ID'] ?? ''] ?? null;
        $studentClassId = trim((string) ($student['Class_ID'] ?? $request['Class_ID'] ?? ''));
        $attendanceType = strtoupper(trim((string) ($request['Attendance_Type'] ?? '')))
            ?: (empty($request['Schedule_ID']) ? 'CLASS_QR' : 'SCHEDULE');
        $isClassBased = in_array($attendanceType, ['CLASS_QR', 'CLASS_MANUAL'], true);
        $schedule = $isClassBased ? null : ($schedulesById[$request['Schedule_ID'] ?? ''] ?? null);

        $request['Student_Name'] = HumanReadableResolver::studentName($request['Student_ID'] ?? '', $studentsById);
        $request['Class_Name'] = HumanReadableResolver::className($studentClassId, $classesById);
        $request['Attendance_Type'] = $isClassBased ? 'CLASS_QR' : 'SCHEDULE';
        $request['Target_Display'] = $isClassBased
            ? 'Absensi Kelas / QR'
            : HumanReadableResolver::scheduleLabel($request['Schedule_ID'] ?? '', $schedulesById, $classesById, $subjectsById);
        $request['Subject_Name'] = $isClassBased
            ? 'Absensi Kelas / QR'
            : HumanReadableResolver::subjectName($schedule['Subject_ID'] ?? '', $subjectsById);
        $request['Status_Label'] = match (strtoupper(trim((string) ($request['Status'] ?? 'PENDING')))) {
            'APPROVED' => 'Disetujui',
            'REJECTED' => 'Ditolak',
            default => 'Menunggu Review',
        };
        $request['Request_Type_Label'] = AttendanceStatusHelper::label($request['Request_Type'] ?? '');

        return $request;
    }
}
