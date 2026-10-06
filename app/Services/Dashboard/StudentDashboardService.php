<?php

namespace App\Services\Dashboard;

use App\Services\Academic\AnnouncementService;
use App\Services\Academic\AttendanceService as AcademicAttendanceService;
use App\Services\Academic\ScheduleService;
use App\Services\Academic\ScoreService;
use App\Services\Attendance\AttendanceRequestService;
use App\Services\Core\ActivityLogService;
use App\Services\Core\AssignmentService;
use App\Services\Core\NotificationService;
use App\Services\Core\StudentService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\StudentBillingNotificationService;
use App\Services\Quiz\QuizService;
use App\Support\Finance\PaymentStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class StudentDashboardService
{
    protected $scoreService;

    protected $scheduleService;

    protected $attendanceService;

    protected $invoiceService;

    protected $paymentService;

    protected $studentService;

    protected $activityLogService;

    protected $notificationService;

    protected $attendanceRequestService;

    protected $announcementService;

    protected $billingNotificationService;

    protected $quizService;

    public function __construct(
        ScoreService $scoreService,
        ScheduleService $scheduleService,
        AcademicAttendanceService $attendanceService,
        InvoiceService $invoiceService,
        PaymentService $paymentService,
        StudentService $studentService,
        ActivityLogService $activityLogService,
        NotificationService $notificationService,
        AttendanceRequestService $attendanceRequestService,
        StudentBillingNotificationService $billingNotificationService,
        QuizService $quizService,
        ?AnnouncementService $announcementService = null
    ) {
        $this->scoreService = $scoreService;
        $this->scheduleService = $scheduleService;
        $this->attendanceService = $attendanceService;
        $this->invoiceService = $invoiceService;
        $this->paymentService = $paymentService;
        $this->studentService = $studentService;
        $this->activityLogService = $activityLogService;
        $this->notificationService = $notificationService;
        $this->attendanceRequestService = $attendanceRequestService;
        $this->billingNotificationService = $billingNotificationService;
        $this->announcementService = $announcementService;
        $this->quizService = $quizService;
    }

    public function getDashboardData()
    {
        $user = Auth::user();
        if (! $user) {
            abort(403, 'Profil siswa tidak ditemukan.');
        }

        $userId = $user->User_ID ?? Auth::id();

        // === Resolve Student ID (fetch once) ===
        $allStudents = collect($this->studentService->getAllStudents());
        $student = $allStudents->firstWhere('User_ID', $userId);
        if (! $student || empty($student['Student_ID'])) {
            abort(403, 'Profil siswa tidak ditemukan.');
        }

        $studentId = $student['Student_ID'];
        $studentClassId = $student['Class_ID'] ?? null;

        $quizDashboard = $this->quizService->studentDashboard((array) $student);

        // Announcements are resolved once from the authenticated identity so
        // role, batch and class rules cannot diverge from the detail endpoint.
        // Legacy regression marker: getActiveAnnouncements('STUDENT', $studentClassId)->take(3)
        $announcements = collect();
        try {
            $announcementService = $this->announcementService ?: app(AnnouncementService::class);
            $announcements = $announcementService->getActiveForUser($user)->take(3)->map(function ($announcement) use ($announcementService) {
                $announcement = (array) $announcement;
                $announcement['Priority_Label'] = $announcementService->priorityLabel($announcement['Priority'] ?? 'NORMAL');
                $announcement['Audience_Label'] = $announcementService->audienceLabel($announcement);
                $announcement['Start_At_Label'] = $this->formatAnnouncementDate($announcementService->startAt($announcement));
                $announcement['Expires_At_Label'] = $this->formatAnnouncementDate($announcementService->expiresAt($announcement));

                return $announcement;
            })->values();
        } catch (\Throwable) {
            // A dashboard should remain usable if the optional sheet is
            // temporarily unavailable; no announcement is safer than stale data.
            $announcements = collect();
        }

        // === Fetch data ONCE ===
        $allScores = collect($this->scoreService->getAll());
        $allSchedules = collect($this->scheduleService->getAll());
        $allAttendances = collect($this->attendanceService->getAll());
        $allInvoices = collect($this->invoiceService->getAll());
        $allPayments = collect($this->paymentService->getAll());

        // === Filter by Student ===
        $myScores = $studentId ? $allScores->where('Student_ID', $studentId) : collect([]);
        $myAttendances = $studentId ? $allAttendances->where('Student_ID', $studentId) : collect([]);
        $myInvoices = $studentId ? $allInvoices->where('Student_ID', $studentId) : collect([]);
        $myPayments = $studentId ? $allPayments->where('Student_ID', $studentId) : collect([]);

        // === Today's Class from MASTER_SCHEDULE ===
        $todayIndo = $this->getTodayIndo();
        $mySchedules = $studentClassId
            ? $allSchedules->where('Class_ID', $studentClassId)
            : collect([]);
        $todayClasses = $mySchedules->filter(function ($s) use ($todayIndo) {
            $day = strtolower($s['Day'] ?? $s['Day_Of_Week'] ?? '');

            return $day === strtolower($todayIndo) || $day === strtolower(date('l'));
        })->map(function ($s) {
            return [
                'time' => ($s['Start_Time'] ?? '').' - '.($s['End_Time'] ?? ''),
                'subject' => $s['Subject_ID'] ?? 'Subject',
                'room' => $s['Room'] ?? 'Room',
            ];
        })->values()->toArray();
        $todayClassCount = count($todayClasses);

        // === Outstanding Bills ===
        $outstandingBills = $myInvoices->whereIn('Status', ['Waiting Payment', 'Partial Paid', 'OVERDUE']);
        $totalOutstanding = $outstandingBills->sum('Remaining_Amount');
        $latestInvoice = $outstandingBills->sortByDesc('Created_At')->first();

        // === Canonical Education Payment Progress ===
        // Reuse the same H8.64 state as Student Billing. Invoice totals are
        // deliberately kept separate because education fee and verified
        // Bayar Mandiri remain valid even when the student has no invoice.
        $educationPaymentState = $this->invoiceService->getStudentEducationPaymentState(
            $studentId,
            $myInvoices,
            $myPayments,
            (array) $student,
        );
        $educationFee = (float) $educationPaymentState['tuition_fee'];
        $educationPaid = (float) $educationPaymentState['verified_paid'];
        $remainingEducationFee = (float) $educationPaymentState['remaining_verified'];
        $educationPaymentStatus = match ($educationPaymentState['status']) {
            'paid' => 'LUNAS',
            'partial' => 'CICILAN',
            'fee_unset' => 'BIAYA BELUM DITETAPKAN',
            default => 'BELUM BAYAR',
        };
        $invoiceRows = $myInvoices->filter(fn ($invoice) => in_array(strtolower(trim((string) ($invoice['Status'] ?? ''))), ['waiting payment', 'partial paid', 'paid', 'overdue'], true));
        $totalBilled = (float) $invoiceRows->sum('Amount');
        $totalPaid = (float) $invoiceRows->sum('Paid_Amount');
        $sisaTagihan = (float) $invoiceRows->sum('Remaining_Amount');
        $statusPembayaran = $invoiceRows->isEmpty() ? 'Belum ada tagihan' : ($sisaTagihan <= 0 ? 'LUNAS' : ($totalPaid > 0 ? 'DIBAYAR SEBAGIAN' : 'BELUM DIBAYAR'));
        $paymentProgress = $educationFee > 0 ? min(100, round(($educationPaid / $educationFee) * 100)) : 0;

        $lastPayment = $myPayments->filter(fn ($payment) => PaymentStatus::verified($payment['Status'] ?? null))->sortByDesc('Payment_Date')->first();
        $nextDueDate = $outstandingBills->whereNotNull('Due_Date')->sortBy('Due_Date')->first();
        $paymentHistory = $myPayments->sortByDesc('Created_At')->take(5)->values();

        // === Latest Score ===
        $latestScore = $myScores->sortByDesc('Created_At')->first();
        $avgScore = $myScores->count() > 0 ? round($myScores->avg(function ($s) {
            $val = $s['Score'] ?? $s['Score_Value'] ?? 0;

            return is_numeric($val) ? (float) $val : 0;
        }), 1) : 0;

        // === Attendance Percentage ===
        $totalMyAttendance = $myAttendances->count();
        $presentCount = $myAttendances->filter(function ($a) {
            return in_array(strtoupper(trim($a['Status'] ?? '')), ['PRESENT', 'LATE', 'HADIR', 'TERLAMBAT']);
        })->count();
        $attendancePercentage = $totalMyAttendance > 0 ? round(($presentCount / $totalMyAttendance) * 100) : 0;

        // === Certificate Status ===
        $allPassed = $myScores->count() > 0 && $myScores->where('Status', '!=', 'PASS')->count() === 0;
        $certificateStatus = $myScores->count() === 0 ? 'Belum Ada Data' : ($allPassed ? 'Memenuhi Syarat' : 'Dalam Proses');

        // === Language Progress ===
        $langProgress = 0;
        $jftScore = $myScores->firstWhere('Assessment_ID', 'JFT-001') ?? $myScores->firstWhere('Assignment_ID', 'JFT-001');
        if ($jftScore) {
            $scoreVal = $jftScore['Score'] ?? $jftScore['Score_Value'] ?? 0;
            $langProgress = $scoreVal >= config('assessment.passing_score', 65) ? 100 : 50;
        }

        $internals = $myScores->map(function ($s) {
            return [
                'name' => $s['Assessment_ID'] ?? $s['Assignment_ID'] ?? 'Task',
                'status' => $s['Status'] ?? 'Completed',
                'score' => $s['Score'] ?? $s['Score_Value'] ?? 0,
                'color' => ($s['Status'] ?? '') == 'PASS' ? 'emerald' : 'red',
            ];
        })->take(4)->toArray();

        // === Attendance Requests KPI ===
        $myRequests = $this->attendanceRequestService->getStudentRequests($studentId);
        $requestPendingCount = $myRequests->where('Status', 'PENDING')->count();
        $requestApprovedCount = $myRequests->where('Status', 'APPROVED')->count();
        $requestRejectedCount = $myRequests->where('Status', 'REJECTED')->count();

        // === KPI ===
        $kpi = [
            'today_class' => $todayClassCount,
            'biaya_pendidikan' => $educationFee,
            'sudah_dibayar' => $educationPaid,
            'sisa_biaya_pendidikan' => $remainingEducationFee,
            'status_biaya_pendidikan' => $educationPaymentStatus,
            'tagihan_master' => (float) $totalOutstanding,
            'total_tagihan' => $totalBilled,
            'tagihan_dibayar' => $totalPaid,
            'sisa_tagihan' => $sisaTagihan,
            'status_pembayaran' => $statusPembayaran,
            'outstanding_bills' => $totalOutstanding,
            'latest_score' => $latestScore['Score'] ?? $latestScore['Score_Value'] ?? 0,
            'attendance_percentage' => $attendancePercentage.'%',
            'certificate_status' => $certificateStatus,
            'request_pending' => $requestPendingCount,
            'request_approved' => $requestApprovedCount,
            'request_rejected' => $requestRejectedCount,
        ];

        // === Reminders (data riil) ===
        $reminders = [];

        if ($totalOutstanding > 0) {
            $reminders[] = [
                'title' => 'Tagihan Belum Lunas',
                'description' => 'Terdapat tagihan sebesar Rp '.number_format($totalOutstanding, 0, ',', '.').' yang belum dilunasi.',
                'action_url' => route('student.billing.index'),
            ];
        }

        foreach ($todayClasses as $cls) {
            $reminders[] = [
                'title' => 'Jadwal Hari Ini: '.($cls['subject'] ?? 'Tidak Diketahui'),
                'description' => ($cls['time'] ?? '').' di '.($cls['room'] ?? ''),
                'action_url' => route('student.schedule'),
            ];
        }

        if ($nextDueDate) {
            $reminders[] = [
                'title' => 'Tagihan Segera Jatuh Tempo',
                'description' => 'Tagihan jatuh tempo: '.($nextDueDate['Due_Date'] ?? '—'),
                'action_url' => route('student.billing.index'),
            ];
        }

        // === Assignments ===
        $pendingAssignmentsCount = 0;
        try {
            $assignmentService = app(AssignmentService::class);
            $allAssignments = collect($assignmentService->getAll());

            $publishedAssignments = $allAssignments->filter(function ($item) use ($studentClassId) {
                $status = strtoupper(! empty($item['Status']) ? $item['Status'] : 'PUBLISHED');

                return ($item['Class_ID'] ?? '') == $studentClassId && $status === 'PUBLISHED';
            });

            $myAssignments = $publishedAssignments->sortBy('Deadline')->take(5);
            $pendingAssignmentsCount = $publishedAssignments->count();

            foreach ($myAssignments as $assignment) {
                $reminders[] = [
                    'title' => 'Tugas: '.($assignment['Title'] ?? 'Untitled'),
                    'description' => 'Tenggat Waktu: '.($assignment['Deadline'] ?? '-'),
                    'action_url' => route('student.portal.assignments'),
                ];
            }
        } catch (\Exception $e) {
            // Ignore if AssignmentService is not available
        }
        $kpi['pending_assignments'] = $pendingAssignmentsCount;

        // === Recent Activity (Student's own, max 10) ===
        $myInvoiceIds = $myInvoices->pluck('Invoice_ID')->toArray();
        $myPaymentIds = $myPayments->pluck('Payment_ID')->toArray();
        $myScoreIds = $myScores->pluck('Score_ID')->toArray();
        $myAttendanceIds = $myAttendances->pluck('Attendance_ID')->toArray();

        $relatedIds = array_merge(
            [$studentId], // if Reference_ID is the Student_ID itself
            $myInvoiceIds,
            $myPaymentIds,
            $myScoreIds,
            $myAttendanceIds
        );
        $recentActivities = $this->getRecentActivity($userId, $relatedIds);

        // === Notification Count ===
        $unreadNotifications = 0;
        $billingPopup = ['current' => null, 'other_count' => 0];
        if ($userId) {
            try {
                $notificationSummary = $this->notificationService->summarizeForUser($userId, 'STUDENT', 100);
                $unreadNotifications = $notificationSummary['unreadCount'] ?? 0;
                $billingPopup = $this->billingNotificationService->popupFromSnapshots(
                    $notificationSummary['recent'] ?? collect(),
                    $myInvoices,
                    (array) $student,
                );
            } catch (\Exception $e) {
                $unreadNotifications = 0;
            }
        }

        return compact(
            'kpi', 'todayClasses', 'myScores', 'langProgress', 'internals',
            'totalOutstanding', 'latestInvoice', 'outstandingBills',
            'paymentProgress', 'lastPayment', 'nextDueDate', 'paymentHistory',
            'attendancePercentage', 'certificateStatus',
            'reminders', 'recentActivities', 'unreadNotifications', 'announcements', 'billingPopup', 'quizDashboard'
        );
    }

    private function formatAnnouncementDate($date): string
    {
        return $date ? $date->locale('id')->translatedFormat('j F Y, H.i').' WIB' : '-';
    }

    private function getTodayIndo()
    {
        $dayMap = [
            'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu',
        ];

        return $dayMap[date('l')] ?? date('l');
    }

    private function getRecentActivity($userId, $relatedIds = [])
    {
        try {
            $allowedModules = ['SYSTEM', 'STUDENT', 'FINANCE', 'INVOICE', 'PAYMENT', 'ACADEMIC', 'SCHEDULE', 'SCORE', 'ATTENDANCE', 'PROGRAM', 'CLASS'];
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
