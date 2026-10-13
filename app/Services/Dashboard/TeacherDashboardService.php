<?php

namespace App\Services\Dashboard;

use App\Helpers\AttendanceStatusHelper;
use App\Helpers\SheetValue;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\PayrollRepositoryInterface;
use App\Services\Academic\AssessmentService;
use App\Services\Academic\AttendanceService as AcademicAttendanceService;
use App\Services\Academic\ScheduleService;
use App\Services\Academic\ScoreService;
use App\Services\Core\ActivityLogService;
use App\Services\Core\ClassService;
use App\Services\Core\NotificationService;
use App\Services\Core\StudentService;
use App\Services\Core\TeacherService;
use App\Support\Academic\TeacherScopeResolver;
use App\Support\Presentation\IndonesianPresentation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class TeacherDashboardService
{
    protected $scheduleService;

    protected $attendanceService;

    protected $assessmentService;

    protected $scoreService;

    protected $teacherService;

    protected $studentService;

    protected $classService;

    protected $activityLogService;

    protected $notificationService;

    protected $teacherScopeResolver;

    public function __construct(
        ScheduleService $scheduleService,
        AcademicAttendanceService $attendanceService,
        AssessmentService $assessmentService,
        ScoreService $scoreService,
        TeacherService $teacherService,
        StudentService $studentService,
        ClassService $classService,
        ActivityLogService $activityLogService,
        NotificationService $notificationService,
        TeacherScopeResolver $teacherScopeResolver
    ) {
        $this->scheduleService = $scheduleService;
        $this->attendanceService = $attendanceService;
        $this->assessmentService = $assessmentService;
        $this->scoreService = $scoreService;
        $this->teacherService = $teacherService;
        $this->studentService = $studentService;
        $this->classService = $classService;
        $this->activityLogService = $activityLogService;
        $this->notificationService = $notificationService;
        $this->teacherScopeResolver = $teacherScopeResolver;
    }

    public function getDashboardData()
    {
        $user = Auth::user();
        if (! $user) {
            abort(403, 'Profil pengajar tidak ditemukan.');
        }

        $userId = trim((string) ($user->User_ID ?? Auth::id()));
        $scope = $this->teacherScopeResolver->resolveForUser($user);
        $teacherId = trim((string) ($scope['teacher_id'] ?? ''));
        $teacher = (array) data_get($scope, 'identity.teacher', []);
        if ($teacherId === '' || $teacher === []) {
            abort(403, 'Profil pengajar tidak ditemukan.');
        }

        // === Fetch data ONCE ===
        $allAttendances = collect($this->attendanceService->getAll());
        $allAssessments = collect($this->assessmentService->getAll());
        $allScores = collect($this->scoreService->getAll());
        $allStudents = collect($this->studentService->getAllStudents())
            ->filter(fn ($student) => SheetValue::isOperationalStudent((array) $student));

        // === Filter by Teacher ===
        $mySchedules = collect($scope['schedules'] ?? collect())->values();
        $myAssessments = $allAssessments->where('Teacher_ID', $teacherId);

        // === Today's Classes from MASTER_SCHEDULE ===
        $todayIndo = $this->getTodayIndo();
        $todayDate = date('Y-m-d');

        $todayClassesRaw = $mySchedules->filter(function ($s) use ($todayIndo) {
            return strtolower($s['Day'] ?? $s['Day_Of_Week'] ?? '') === strtolower($todayIndo);
        })->values();

        $todayClasses = $todayClassesRaw->map(function ($s) {
            return [
                'time' => ($s['Start_Time'] ?? '').' - '.($s['End_Time'] ?? ''),
                'subject' => $s['Subject_ID'] ?? 'Subject',
                'room' => $s['Room'] ?? 'Room',
                'class' => $s['Class_ID'] ?? '',
            ];
        })->toArray();

        // Homeroom ownership comes exclusively from MASTER_CLASS. Teaching
        // schedules remain separate and are used only for teaching activity.
        $myClassIds = collect($scope['managed_class_ids'] ?? []);
        $homeroomClasses = collect($scope['managed_classes'] ?? collect())->values();
        $myStudentCount = count($scope['managed_student_ids'] ?? []);

        $todayScheduleIds = $todayClassesRaw->pluck('Schedule_ID')->unique();
        $mySchedulesById = $mySchedules->keyBy('Schedule_ID');
        $todayAttendances = $allAttendances->filter(function ($a) use ($todayDate, $todayScheduleIds, $myClassIds) {
            if (($a['Attendance_Date'] ?? '') !== $todayDate
                || strtoupper(trim((string) ($a['Is_Active'] ?? 'TRUE'))) === 'FALSE'
                || empty($a['Student_ID'])) {
                return false;
            }

            $attendanceType = strtoupper(trim((string) ($a['Attendance_Type'] ?? '')));
            if (in_array($attendanceType, ['CLASS_QR', 'CLASS_MANUAL'], true)) {
                return $myClassIds->contains($a['Class_ID'] ?? '');
            }

            return $attendanceType === 'SCHEDULE'
                && $todayScheduleIds->contains($a['Schedule_ID'] ?? '');
        })->sortBy(function ($attendance) {
            $type = strtoupper(trim((string) ($attendance['Attendance_Type'] ?? '')));

            return in_array($type, ['CLASS_QR', 'CLASS_MANUAL'], true) ? 0 : 1;
        })->unique(function ($attendance) use ($mySchedulesById) {
            $type = strtoupper(trim((string) ($attendance['Attendance_Type'] ?? '')));
            if (in_array($type, ['CLASS_QR', 'CLASS_MANUAL'], true)) {
                $classId = $attendance['Class_ID'] ?? '';
            } else {
                $schedule = $mySchedulesById->get($attendance['Schedule_ID'] ?? '');
                $classId = $schedule['Class_ID'] ?? '';
            }

            return ($attendance['Student_ID'] ?? '').'|'.$classId;
        })->values();
        $attendanceStats = [
            'hadir' => $todayAttendances->filter(fn ($a) => in_array(AttendanceStatusHelper::normalize($a['Status'] ?? ''), ['PRESENT', 'LATE'], true))->count(),
            'sakit' => $todayAttendances->filter(fn ($a) => AttendanceStatusHelper::normalize($a['Status'] ?? '') === 'SICK')->count(),
            'izin' => $todayAttendances->filter(fn ($a) => AttendanceStatusHelper::normalize($a['Status'] ?? '') === 'PERMITTED')->count(),
            'alpa' => $todayAttendances->filter(fn ($a) => AttendanceStatusHelper::normalize($a['Status'] ?? '') === 'ABSENT')->count(),
        ];
        $attendanceToday = array_sum($attendanceStats);
        $todayClassIds = $todayClassesRaw->pluck('Class_ID')->filter()->unique();
        $attendedClassIds = $todayAttendances->map(function ($attendance) use ($mySchedulesById) {
            $type = strtoupper(trim((string) ($attendance['Attendance_Type'] ?? '')));
            if (in_array($type, ['CLASS_QR', 'CLASS_MANUAL'], true)) {
                return $attendance['Class_ID'] ?? '';
            }

            $schedule = $mySchedulesById->get($attendance['Schedule_ID'] ?? '');

            return $schedule['Class_ID'] ?? '';
        })->filter()->unique();
        $attendancePending = $todayClassIds->diff($attendedClassIds)->count();

        // === Assessment Pending (my assessments not yet fully scored) ===
        $myAssessmentIds = $myAssessments->pluck('Assessment_ID')->filter()->unique();
        $assessmentPending = $this->countAssessmentsWithoutFullScores($myAssessments, $allScores, $allStudents);

        // === Score Pending (scores with Draft/Pending status for my assessments) ===
        $scorePending = $allScores->filter(function ($s) use ($myAssessmentIds) {
            return $myAssessmentIds->contains($s['Assessment_ID'] ?? '') &&
                   in_array(($s['Status'] ?? ''), ['Draft', 'Pending', '']);
        })->count();

        // Gaji Bulan Ini
        $mySalaryStatus = 'Belum Diterima';
        try {
            $employeeRepo = app(EmployeeRepositoryInterface::class);
            $payrollRepo = app(PayrollRepositoryInterface::class);
            $employeeId = trim((string) data_get($scope, 'identity.employee_id', ''));
            $employee = $employeeId === ''
                ? null
                : collect($employeeRepo->fetchAll())->firstWhere('Employee_ID', $employeeId);
            if ($employee) {
                $payrolls = collect($payrollRepo->getAll())
                    ->where('Employee_ID', $employee['Employee_ID'])
                    ->where('Status', 'Paid')
                    ->sortByDesc('Created_At');

                $latestPayroll = $payrolls->first();
                if ($latestPayroll) {
                    $monthCreated = Carbon::parse($latestPayroll['Created_At'])->format('Y-m');
                    $currentMonth = Carbon::now()->format('Y-m');
                    if ($monthCreated === $currentMonth || Carbon::parse($latestPayroll['Paid_Date'] ?? now())->format('Y-m') === $currentMonth) {
                        $mySalaryStatus = 'Diterima';
                    }
                }
            }
        } catch (\Exception $e) {
        }

        // === KPI ===
        $kpi = [
            'today_classes' => $todayClassesRaw->count(),
            'my_classes' => $myClassIds->count(),
            'my_students' => $myStudentCount,
            'attendance_today' => $attendanceToday,
            'attendance_pending' => $attendancePending,
            'assessment_pending' => $assessmentPending,
            'score_pending' => $scorePending,
            'salary_status' => $mySalaryStatus,
        ];

        // === Reminders (data riil) ===
        $reminders = [];

        foreach ($todayClasses as $cls) {
            $reminders[] = [
                'title' => 'Class Today: '.($cls['subject'] ?? 'Unknown'),
                'description' => ($cls['time'] ?? '').' in '.($cls['room'] ?? ''),
                'action_url' => route('teacher.workspace.schedule'),
            ];
        }

        if ($attendancePending > 0) {
            $reminders[] = [
                'title' => 'Attendance Pending',
                'description' => $attendancePending.' sesi absensi belum diisi hari ini.',
                'action_url' => route('teacher.workspace.attendances'),
            ];
        }

        if ($assessmentPending > 0) {
            $reminders[] = [
                'title' => 'Assessment Pending',
                'description' => $assessmentPending.' assessment menunggu input nilai.',
                'action_url' => route('teacher.workspace.scores'),
            ];
        }

        // === Recent Activity (Teacher's own, max 10) ===
        $myClassIdsArray = $myClassIds->toArray();
        $myScheduleIds = $mySchedules->pluck('Schedule_ID')->toArray();
        $myAssessmentIdsArray = $myAssessmentIds->toArray();
        $myScoreIds = $allScores->filter(function ($s) use ($myAssessmentIdsArray) {
            return in_array($s['Assessment_ID'] ?? '', $myAssessmentIdsArray, true);
        })->pluck('Score_ID')->toArray();

        $relatedIds = array_merge(
            [$teacherId],
            $myClassIdsArray,
            $myScheduleIds,
            $myAssessmentIdsArray,
            $myScoreIds
        );
        $recentActivities = $this->getRecentActivity($userId, $relatedIds);

        // === Notification Count ===
        $unreadNotifications = 0;
        try {
            $unreadNotifications = $this->notificationService->UnreadCount($userId, 'TEACHER');
        } catch (\Exception $e) {
            $unreadNotifications = 0;
        }

        $dashboardNow = Carbon::now('Asia/Jakarta');
        $dashboardHour = $dashboardNow->hour;
        $dashboardContext = [
            'greeting' => match (true) {
                $dashboardHour >= 5 && $dashboardHour < 11 => 'Selamat pagi',
                $dashboardHour >= 11 && $dashboardHour < 15 => 'Selamat siang',
                $dashboardHour >= 15 && $dashboardHour < 18 => 'Selamat sore',
                default => 'Selamat malam',
            },
            'dateFormatted' => IndonesianPresentation::date($dashboardNow, 'l, j F Y'),
            'timeFormatted' => $dashboardNow->format('H:i'),
            'teacherName' => $teacher['Full_Name'] ?? $user->Full_Name ?? 'Pengajar',
        ];

        return compact(
            'kpi', 'todayClasses', 'homeroomClasses', 'attendanceStats', 'reminders', 'recentActivities', 'unreadNotifications', 'dashboardContext'
        );
    }

    private function getTodayIndo()
    {
        $dayMap = [
            'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu',
        ];

        return $dayMap[date('l')] ?? date('l');
    }

    private function countAssessmentsWithoutFullScores($assessments, $scores, $students): int
    {
        return collect($assessments)->filter(function ($assessment) use ($scores, $students) {
            $assessmentId = $assessment['Assessment_ID'] ?? '';
            if ($assessmentId === '') {
                return false;
            }

            $assessmentScores = collect($scores)->where('Assessment_ID', $assessmentId);
            $classId = trim((string) ($assessment['Class_ID'] ?? ''));
            if ($classId === '') {
                return $assessmentScores->isEmpty();
            }

            $classStudentIds = collect($students)
                ->filter(fn ($student) => trim((string) ($student['Class_ID'] ?? '')) === $classId)
                ->pluck('Student_ID')
                ->filter()
                ->unique()
                ->values();

            if ($classStudentIds->isEmpty()) {
                return $assessmentScores->isEmpty();
            }

            $scoredStudentIds = $assessmentScores
                ->whereIn('Student_ID', $classStudentIds->all())
                ->pluck('Student_ID')
                ->filter()
                ->unique();

            return $scoredStudentIds->count() < $classStudentIds->count();
        })->count();
    }

    private function getRecentActivity($userId, $relatedIds = [])
    {
        try {
            $allowedModules = ['SYSTEM', 'ACADEMIC', 'CLASS', 'SCHEDULE', 'SCORE', 'ASSESSMENT', 'ATTENDANCE', 'TEACHER'];
            $logs = collect($this->activityLogService->getAllLogs());

            return $logs->filter(function ($log) use ($userId, $relatedIds, $allowedModules) {
                $isAllowedModule = in_array(strtoupper($log['Module'] ?? ''), $allowedModules);
                if (! $isAllowedModule) {
                    return false;
                }

                return ($log['User_ID'] ?? '') === $userId
                    || ($log['Reference_ID'] ?? '') === $userId
                    || in_array($log['Reference_ID'] ?? '', $relatedIds);
            })->sortByDesc('Created_At')->take(5)->map(function ($log) {
                $desc = $log['Description'] ?? ($log['New_Value'] ?? '');
                if (is_string($desc) && str_starts_with($desc, '{')) {
                    $decoded = json_decode($desc, true);
                    if (is_array($decoded) && isset($decoded['description'])) {
                        $desc = $decoded['description'];
                    } elseif (is_array($decoded) && isset($decoded['title'])) {
                        $desc = $decoded['title'];
                    } else {
                        $action = str_replace('_', ' ', $log['Action'] ?? '');
                        $refId = $log['Reference_ID'] ?? '';
                        $desc = 'Aktivitas '.ucwords(strtolower($action)).($refId ? " pada {$refId}" : '');
                    }
                }

                return [
                    'title' => $log['Action'] ?? 'Aktivitas',
                    'description' => ($log['Module'] ?? '').' — '.$desc,
                    'time' => isset($log['Created_At']) ? Carbon::parse($log['Created_At'])->diffForHumans() : 'Baru saja',
                ];
            })->values()->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }
}
