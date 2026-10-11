<?php

use App\Http\Controllers\Academic\AcademicSettingsController;
use App\Http\Controllers\Academic\AlumniController;
use App\Http\Controllers\Academic\AnnouncementCommentController;
use App\Http\Controllers\Academic\AnnouncementController;
use App\Http\Controllers\Academic\AssessmentController;
use App\Http\Controllers\Academic\AssignmentController;
use App\Http\Controllers\Academic\AttendanceController;
use App\Http\Controllers\Academic\AttendanceReportController;
use App\Http\Controllers\Academic\AttendanceRequestController;
use App\Http\Controllers\Academic\BatchController;
use App\Http\Controllers\Academic\ScheduleController;
use App\Http\Controllers\Academic\ScoreController;
use App\Http\Controllers\Academic\StickerController;
use App\Http\Controllers\Academic\StudentQRAttendanceController;
use App\Http\Controllers\Academic\StudentWorkspaceController;
use App\Http\Controllers\Academic\SubjectController;
use App\Http\Controllers\Academic\TeacherWorkspaceController;
use App\Http\Controllers\Admin\StickerAdminController;
use App\Http\Controllers\Core\AcademicDashboardController;
use App\Http\Controllers\Core\ActivityController;
use App\Http\Controllers\Core\AnnouncementFeedController;
use App\Http\Controllers\Core\ApprovalController;
use App\Http\Controllers\Core\AuditLogController;
use App\Http\Controllers\Core\AuthController;
use App\Http\Controllers\Core\ClassController;
use App\Http\Controllers\Core\CompanyController;
use App\Http\Controllers\Core\DashboardController;
use App\Http\Controllers\Core\DepartmentController;
use App\Http\Controllers\Core\DeveloperPanelController;
use App\Http\Controllers\Core\DirectorDashboardController;
use App\Http\Controllers\Core\EmailDeliveryController;
use App\Http\Controllers\Core\EmployeeController;
use App\Http\Controllers\Core\FinanceDashboardController;
use App\Http\Controllers\Core\GlobalSearchController;
use App\Http\Controllers\Core\HrDashboardController;
use App\Http\Controllers\Core\MarketingDashboardController;
use App\Http\Controllers\Core\ModuleController;
use App\Http\Controllers\Core\NotificationController;
use App\Http\Controllers\Core\PermanentQrController;
use App\Http\Controllers\Core\PermissionController;
use App\Http\Controllers\Core\PositionController;
use App\Http\Controllers\Core\ProfileController;
use App\Http\Controllers\Core\ProgramController;
use App\Http\Controllers\Core\StudentAnnouncementController;
use App\Http\Controllers\Core\StudentController;
use App\Http\Controllers\Core\StudentDashboardController;
use App\Http\Controllers\Core\StudentPortalController;
use App\Http\Controllers\Core\SystemSettingController;
use App\Http\Controllers\Core\TeacherController;
use App\Http\Controllers\Core\TeacherDashboardController;
use App\Http\Controllers\Core\UserController;
use App\Http\Controllers\Dashboard\PersonalPayrollController;
use App\Http\Controllers\Finance\AccountController;
use App\Http\Controllers\Finance\EducationPaymentMonitoringController;
use App\Http\Controllers\Finance\FinanceSettingsController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Finance\ReportController;
use App\Http\Controllers\Finance\SmartGeneratorController;
use App\Http\Controllers\Finance\StudentBillingController;
use App\Http\Controllers\Finance\TransactionController;
use App\Http\Controllers\Hr\AttendanceMonitoringController;
use App\Http\Controllers\Hr\HrSettingsController;
use App\Http\Controllers\Hr\LeaveController;
use App\Http\Controllers\Hr\OvertimeController;
use App\Http\Controllers\Hr\PayrollController;
use App\Http\Controllers\Hr\QRAttendanceController;
use App\Http\Controllers\Quiz\StudentQuizController;
use App\Http\Controllers\Quiz\TeacherQuizController;
use App\Http\Controllers\Student\AttendanceHistoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/verify-receipt/{id}', [PaymentController::class, 'verifyReceiptPublic'])->middleware('signed')->name('payments.verify-receipt-public');
Route::get('/verify-invoice/{id}', [InvoiceController::class, 'verifyInvoicePublic'])->middleware('signed')->name('invoices.verify-public');
Route::get('/verify-payslip/{id}', [PayrollController::class, 'verifyPayslipPublic'])->middleware('signed')->name('payrolls.verify-public');
Route::get('/verify-leave/{id}', [LeaveController::class, 'verifyLeavePublic'])->middleware('signed')->name('leaves.verify-public');
Route::get('/verify-overtime/{id}', [OvertimeController::class, 'verifyOvertimePublic'])->middleware('signed')->name('overtimes.verify-public');

// Requires authentication
Route::middleware('auth')->group(function () {
    // Audience-facing Announcement V2. Visibility is resolved server-side for
    // every authenticated role; management routes remain separately scoped.
    Route::get('/announcements', [AnnouncementFeedController::class, 'index'])->name('announcement-feed.index');
    Route::get('/announcements/{id}', [AnnouncementFeedController::class, 'show'])->name('announcement-feed.show');
    Route::post('/announcements/{announcementId}/comments', [AnnouncementCommentController::class, 'store'])
        ->middleware('throttle:10,1')->name('announcement-comments.store');
    Route::get('/announcements/{announcementId}/mentions', [AnnouncementCommentController::class, 'mentions'])
        ->middleware('throttle:30,1')->name('announcement-comments.mentions');
    Route::get('/announcements/{announcementId}/gifs', [AnnouncementCommentController::class, 'gifs'])
        ->middleware('throttle:20,1')->name('announcement-comments.gifs');
    Route::delete('/announcements/{announcementId}/comments/{commentId}', [AnnouncementCommentController::class, 'destroy'])
        ->name('announcement-comments.destroy');
    Route::get('/announcement-stickers', [StickerController::class, 'index'])->name('announcement-stickers.index');
    Route::post('/announcement-stickers/{id}/favorite', [StickerController::class, 'favorite'])->name('announcement-stickers.favorite');
    Route::delete('/announcement-stickers/{id}/favorite', [StickerController::class, 'unfavorite'])->name('announcement-stickers.unfavorite');

    Route::prefix('admin/stickers')->name('admin.stickers.')->middleware('role:ADMINISTRATOR')->group(function () {
        Route::get('/', [StickerAdminController::class, 'index'])->name('index');
        Route::post('/packs', [StickerAdminController::class, 'storePack'])->name('packs.store');
        Route::put('/packs/{id}', [StickerAdminController::class, 'updatePack'])->name('packs.update');
        Route::post('/', [StickerAdminController::class, 'storeSticker'])->middleware('throttle:10,1')->name('store');
        Route::put('/{id}', [StickerAdminController::class, 'updateSticker'])->name('update');
    });

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/personal-payroll', [PersonalPayrollController::class, 'index'])->name('dashboard.personal-payroll');
    Route::get('/dashboard/personal-payroll/{id}/proof', [PersonalPayrollController::class, 'downloadProof'])->name('dashboard.personal-payroll.proof');

    // Role-specific Dashboards
    Route::middleware('role:HR,ADMINISTRATOR')->get('/dashboard/hr', [HrDashboardController::class, 'index'])->name('dashboard.hr');
    Route::middleware('role:ACADEMIC,ADMINISTRATOR')->get('/dashboard/academic', [AcademicDashboardController::class, 'index'])->name('dashboard.academic');
    Route::middleware('role:MARKETING,ADMINISTRATOR')->get('/dashboard/marketing', [MarketingDashboardController::class, 'index'])->name('dashboard.marketing');
    Route::middleware('role:FINANCE,ADMINISTRATOR')->get('/dashboard/finance', [FinanceDashboardController::class, 'index'])->name('dashboard.finance');
    Route::middleware('role:DIRECTOR,ADMINISTRATOR')->get('/dashboard/director', [DirectorDashboardController::class, 'index'])->name('dashboard.director');
    Route::middleware('role:TEACHER')->get('/dashboard/teacher', [TeacherDashboardController::class, 'index'])->name('dashboard.teacher');
    Route::middleware('role:STUDENT')->get('/dashboard/student', [StudentDashboardController::class, 'index'])->name('dashboard.student');
    Route::middleware('role:ADMINISTRATOR')->get('/dashboard/administrator', [DashboardController::class, 'index'])->name('dashboard.administrator');

    Route::prefix('attendance/reports')->name('attendance.reports.')->middleware('role:TEACHER,ADMINISTRATOR,MASTER')->group(function () {
        Route::get('/', [AttendanceReportController::class, 'index'])->name('index');
        Route::get('/pdf', [AttendanceReportController::class, 'pdf'])->name('pdf');
    });

    // Developer Panel
    Route::middleware('role:ADMINISTRATOR')->get('/developer-panel', [DeveloperPanelController::class, 'index'])->name('developer-panel.index');

    // User Management
    Route::prefix('users')->name('users.')->middleware('role:ADMINISTRATOR')->group(function () {
        Route::get('/preview-pdf', [UserController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [UserController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [UserController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [UserController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [UserController::class, 'print'])->name('print');
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('/{id}', [UserController::class, 'update'])->name('update');
        Route::delete('/{id}', [UserController::class, 'destroy'])->name('destroy');
    });

    // Department Management
    Route::prefix('departments')->name('departments.')->middleware('role:ADMINISTRATOR,HR')->group(function () {
        Route::get('/preview-pdf', [DepartmentController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [DepartmentController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [DepartmentController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [DepartmentController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [DepartmentController::class, 'print'])->name('print');
        Route::get('/', [DepartmentController::class, 'index'])->name('index');
        Route::get('/create', [DepartmentController::class, 'create'])->name('create');
        Route::post('/', [DepartmentController::class, 'store'])->name('store');
        Route::get('/{id}', [DepartmentController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [DepartmentController::class, 'edit'])->name('edit');
        Route::put('/{id}', [DepartmentController::class, 'update'])->name('update');
        Route::delete('/{id}', [DepartmentController::class, 'destroy'])->name('destroy');
    });

    // Position Management
    Route::prefix('positions')->name('positions.')->middleware('role:ADMINISTRATOR,HR')->group(function () {
        Route::get('/preview-pdf', [PositionController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [PositionController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [PositionController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [PositionController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [PositionController::class, 'print'])->name('print');
        Route::get('/', [PositionController::class, 'index'])->name('index');
        Route::get('/create', [PositionController::class, 'create'])->name('create');
        Route::post('/', [PositionController::class, 'store'])->name('store');
        Route::get('/{id}', [PositionController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [PositionController::class, 'edit'])->name('edit');
        Route::put('/{id}', [PositionController::class, 'update'])->name('update');
        Route::delete('/{id}', [PositionController::class, 'destroy'])->name('destroy');
    });

    // Employee Management
    Route::prefix('employees')->name('employees.')->middleware('role:ADMINISTRATOR,HR')->group(function () {
        Route::get('/preview-pdf', [EmployeeController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [EmployeeController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [EmployeeController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [EmployeeController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [EmployeeController::class, 'print'])->name('print');
        Route::get('/', [EmployeeController::class, 'index'])->name('index');
        Route::get('/create', [EmployeeController::class, 'create'])->name('create');
        Route::post('/', [EmployeeController::class, 'store'])->name('store');
        Route::get('/{id}', [EmployeeController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [EmployeeController::class, 'edit'])->name('edit');
        Route::put('/{id}', [EmployeeController::class, 'update'])->name('update');
        Route::delete('/{id}', [EmployeeController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/send-email', [EmployeeController::class, 'sendEmail'])->name('send-email');
    });

    // Teacher Management
    Route::prefix('teachers')->name('teachers.')->middleware('role:ADMINISTRATOR,ACADEMIC,HR')->group(function () {
        Route::get('/preview-pdf', [TeacherController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [TeacherController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [TeacherController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [TeacherController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [TeacherController::class, 'print'])->name('print');
        Route::get('/', [TeacherController::class, 'index'])->name('index');
        Route::get('/create', [TeacherController::class, 'create'])->name('create');
        Route::post('/', [TeacherController::class, 'store'])->name('store');
        Route::get('/{id}', [TeacherController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [TeacherController::class, 'edit'])->name('edit');
        Route::put('/{id}', [TeacherController::class, 'update'])->name('update');
        Route::delete('/{id}', [TeacherController::class, 'destroy'])->name('destroy');
    });

    // Program Management
    Route::prefix('programs')->name('programs.')->middleware('role:ADMINISTRATOR,ACADEMIC')->group(function () {
        Route::get('/preview-pdf', [ProgramController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [ProgramController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [ProgramController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [ProgramController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [ProgramController::class, 'print'])->name('print');
        Route::get('/', [ProgramController::class, 'index'])->name('index');
        Route::get('/create', [ProgramController::class, 'create'])->name('create');
        Route::post('/', [ProgramController::class, 'store'])->name('store');
        Route::get('/{id}', [ProgramController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [ProgramController::class, 'edit'])->name('edit');
        Route::put('/{id}', [ProgramController::class, 'update'])->name('update');
        Route::delete('/{id}', [ProgramController::class, 'destroy'])->name('destroy');
    });

    // Student Management
    Route::prefix('students')->name('students.')->middleware('role:ADMINISTRATOR,ACADEMIC')->group(function () {
        Route::get('/preview-pdf', [StudentController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [StudentController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [StudentController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [StudentController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [StudentController::class, 'print'])->name('print');
        Route::get('/', [StudentController::class, 'index'])->name('index');
        Route::get('/create', [StudentController::class, 'create'])->name('create');
        Route::post('/', [StudentController::class, 'store'])->name('store');
        Route::get('/{id}', [StudentController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [StudentController::class, 'edit'])->name('edit');
        Route::post('/{id}/graduate', [StudentController::class, 'graduate'])->name('graduate');
        Route::put('/{id}', [StudentController::class, 'update'])->name('update');
        Route::delete('/{id}', [StudentController::class, 'destroy'])->name('destroy');
    });

    // Alumni Management
    Route::prefix('academic/alumni')->name('alumni.')->middleware('role:ADMINISTRATOR,ACADEMIC')->group(function () {
        Route::get('/', [AlumniController::class, 'index'])->name('index');
        Route::get('/export-csv', [AlumniController::class, 'exportCsv'])->name('export-csv');
        Route::post('/export-selected-pdf', [AlumniController::class, 'exportSelectedPdf'])->name('export-selected-pdf');
        Route::get('/create', [AlumniController::class, 'create'])->name('create');
        Route::post('/', [AlumniController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [AlumniController::class, 'edit'])->name('edit');
        Route::put('/{id}', [AlumniController::class, 'update'])->name('update');
        Route::delete('/{id}', [AlumniController::class, 'destroy'])->name('destroy');
        Route::get('/{id}', [AlumniController::class, 'show'])->name('show');
    });

    // Company Management
    Route::prefix('companies')->name('companies.')->middleware('role:ADMINISTRATOR,MARKETING')->group(function () {
        Route::get('/preview-pdf', [CompanyController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [CompanyController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [CompanyController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [CompanyController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [CompanyController::class, 'print'])->name('print');
        Route::get('/', [CompanyController::class, 'index'])->name('index');
        Route::get('/create', [CompanyController::class, 'create'])->name('create');
        Route::post('/', [CompanyController::class, 'store'])->name('store');
        Route::get('/{id}', [CompanyController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [CompanyController::class, 'edit'])->name('edit');
        Route::put('/{id}', [CompanyController::class, 'update'])->name('update');
        Route::delete('/{id}', [CompanyController::class, 'destroy'])->name('destroy');
    });

    // Module Management
    Route::prefix('modules')->name('modules.')->middleware('role:ADMINISTRATOR')->group(function () {
        Route::get('/preview-pdf', [ModuleController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [ModuleController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [ModuleController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [ModuleController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [ModuleController::class, 'print'])->name('print');
        Route::get('/', [ModuleController::class, 'index'])->name('index');
        Route::get('/create', [ModuleController::class, 'create'])->name('create');
        Route::post('/', [ModuleController::class, 'store'])->name('store');
        Route::get('/{id}', [ModuleController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [ModuleController::class, 'edit'])->name('edit');
        Route::put('/{id}', [ModuleController::class, 'update'])->name('update');
        Route::delete('/{id}', [ModuleController::class, 'destroy'])->name('destroy');
    });

    // Finance - Accounts
    Route::prefix('finance/accounts')->name('accounts.')->middleware('role:ADMINISTRATOR,FINANCE')->group(function () {
        Route::get('/preview-pdf', [AccountController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [AccountController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [AccountController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [AccountController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [AccountController::class, 'print'])->name('print');
        Route::get('/', [AccountController::class, 'index'])->name('index');
        Route::get('/create', [AccountController::class, 'create'])->name('create');
        Route::post('/', [AccountController::class, 'store'])->name('store');
        Route::get('/{id}', [AccountController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [AccountController::class, 'edit'])->name('edit');
        Route::put('/{id}', [AccountController::class, 'update'])->name('update');
        Route::delete('/{id}', [AccountController::class, 'destroy'])->name('destroy');
    });

    // Finance - Transactions
    Route::prefix('finance/transactions')->name('transactions.')->middleware('role:ADMINISTRATOR,FINANCE,DIRECTOR')->group(function () {
        Route::get('/preview-pdf', [TransactionController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [TransactionController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [TransactionController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [TransactionController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [TransactionController::class, 'print'])->name('print');
        Route::get('/', [TransactionController::class, 'index'])->name('index');
        Route::get('/create', [TransactionController::class, 'create'])->name('create')->middleware('role:ADMINISTRATOR,FINANCE');
        Route::post('/', [TransactionController::class, 'store'])->name('store')->middleware('role:ADMINISTRATOR,FINANCE');
        Route::get('/{id}', [TransactionController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [TransactionController::class, 'edit'])->name('edit')->middleware('role:ADMINISTRATOR,FINANCE');
        Route::put('/{id}', [TransactionController::class, 'update'])->name('update')->middleware('role:ADMINISTRATOR,FINANCE');
        Route::delete('/{id}', [TransactionController::class, 'destroy'])->name('destroy')->middleware('role:ADMINISTRATOR,FINANCE');
    });

    // Smart Generator Invoice & Kwitansi Pro V3
    Route::prefix('finance/smart-generator')->name('finance.smart_generator.')->middleware('role:ADMINISTRATOR,FINANCE')->group(function () {
        Route::get('/', [SmartGeneratorController::class, 'index'])->name('index');
        Route::get('/student-invoices/search', [SmartGeneratorController::class, 'searchStudentInvoices'])->name('search_student_invoices');
        Route::post('/pdf', [SmartGeneratorController::class, 'exportPdf'])->name('pdf');
        Route::post('/save', [SmartGeneratorController::class, 'saveHistory'])->name('save');
        Route::get('/history-api', [SmartGeneratorController::class, 'getHistoryApi'])->name('history_api');
        Route::delete('/history/{id}', [SmartGeneratorController::class, 'deleteHistory'])->name('delete_history');
        Route::post('/send-email', [SmartGeneratorController::class, 'sendEmail'])->name('send_email');
    });

    // Finance - Invoices
    Route::get('/finance/education-payments', [EducationPaymentMonitoringController::class, 'index'])
        ->middleware('role:ADMINISTRATOR,FINANCE')
        ->name('finance.education-payments.index');
    Route::get('/finance/education-payments/{studentId}', [EducationPaymentMonitoringController::class, 'show'])
        ->middleware('role:ADMINISTRATOR,FINANCE')
        ->name('finance.education-payments.show');

    Route::prefix('finance/invoices')->name('invoices.')->middleware('role:ADMINISTRATOR,FINANCE')->group(function () {
        Route::get('/preview-pdf', [InvoiceController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [InvoiceController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [InvoiceController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [InvoiceController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [InvoiceController::class, 'print'])->name('print');
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::get('/create', [InvoiceController::class, 'create'])->name('create');
        Route::post('/', [InvoiceController::class, 'store'])->name('store');
        Route::post('/{id}/publish', [InvoiceController::class, 'publish'])->name('publish');
        Route::post('/{id}/cancel', [InvoiceController::class, 'cancel'])->name('cancel');
        Route::post('/{id}/notify', [InvoiceController::class, 'notify'])->name('notify');
        Route::get('/{id}/pdf', [InvoiceController::class, 'downloadInvoicePdf'])->name('pdf');
        Route::get('/{id}', [InvoiceController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [InvoiceController::class, 'edit'])->name('edit');
        Route::put('/{id}', [InvoiceController::class, 'update'])->name('update');
        Route::delete('/{id}', [InvoiceController::class, 'destroy'])->name('destroy');
    });

    // Finance - Payments
    Route::prefix('finance/payments')->name('payments.')->middleware('role:ADMINISTRATOR,FINANCE')->group(function () {
        Route::get('/preview-pdf', [PaymentController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [PaymentController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [PaymentController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [PaymentController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [PaymentController::class, 'print'])->name('print');
        Route::get('/', [PaymentController::class, 'index'])->name('index');
        Route::get('/create', [PaymentController::class, 'create'])->name('create');
        Route::post('/', [PaymentController::class, 'store'])->name('store');
        Route::post('/{id}/verify', [PaymentController::class, 'verify'])->name('verify');
        Route::post('/{id}/reverse', [PaymentController::class, 'reverse'])->name('reverse');
        Route::post('/{id}/reconcile-ledger', [PaymentController::class, 'reconcileLedger'])->name('reconcile-ledger');
        Route::post('/{id}/reconcile-reversal', [PaymentController::class, 'reconcileReversal'])->name('reconcile-reversal');
        Route::post('/{id}/link-invoice', [PaymentController::class, 'linkInvoice'])->name('link-invoice');
        Route::get('/{id}/receipt', [PaymentController::class, 'downloadReceiptPdf'])->name('receipt');
        Route::get('/{id}/proof', [PaymentController::class, 'downloadProof'])->name('proof');
        Route::get('/{id}', [PaymentController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [PaymentController::class, 'edit'])->name('edit');
        Route::put('/{id}', [PaymentController::class, 'update'])->name('update');
        Route::delete('/{id}', [PaymentController::class, 'destroy'])->name('destroy');
    });

    // Finance - Reports
    Route::prefix('finance/reports')->name('reports.finance.')->middleware('role:ADMINISTRATOR,FINANCE,DIRECTOR')->group(function () {
        Route::get('/preview-pdf', [ReportController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [ReportController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [ReportController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [ReportController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [ReportController::class, 'print'])->name('print');
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/cash-flow', [ReportController::class, 'cashFlow'])->name('cash_flow');
        Route::get('/outstanding', [ReportController::class, 'outstandingInvoices'])->name('outstanding');
    });

    // HR - Payrolls
    Route::prefix('hr/payrolls')->name('payrolls.')->middleware('role:ADMINISTRATOR,HR,FINANCE')->group(function () {
        Route::get('/preview-pdf', [PayrollController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [PayrollController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [PayrollController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [PayrollController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [PayrollController::class, 'print'])->name('print');
        Route::get('/', [PayrollController::class, 'index'])->name('index');
        Route::get('/create', [PayrollController::class, 'create'])->name('create');
        Route::post('/', [PayrollController::class, 'store'])->name('store');
        Route::post('/batch-generate', [PayrollController::class, 'batchGenerate'])->name('batch-generate');
        Route::get('/{id}/pdf', [PayrollController::class, 'downloadPayslipPdf'])->name('pdf');
        Route::post('/{id}/submit', [PayrollController::class, 'submit'])->name('submit');
        Route::post('/{id}/approve', [PayrollController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [PayrollController::class, 'reject'])->name('reject');
        Route::post('/{id}/pay', [PayrollController::class, 'pay'])->name('pay');
        Route::get('/{id}/slip', [PayrollController::class, 'downloadPayslipPdf'])->name('slip');
        Route::get('/{id}/proof', [PayrollController::class, 'downloadProof'])->name('proof');
        Route::get('/{id}', [PayrollController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [PayrollController::class, 'edit'])->name('edit');
        Route::put('/{id}', [PayrollController::class, 'update'])->name('update');
        Route::delete('/{id}', [PayrollController::class, 'destroy'])->name('destroy');
    });

    // HR - Dynamic QR Attendance Engine
    Route::prefix('hr/attendance/qr')->name('hr.attendance.qr.')->middleware('role:ADMINISTRATOR,HR')->group(function () {
        Route::get('/', [QRAttendanceController::class, 'index'])->name('index');
        Route::post('/session', [QRAttendanceController::class, 'storeSession'])->name('session.store');
        Route::get('/session/{sessionId}/display', [QRAttendanceController::class, 'displaySession'])->name('display');
        Route::get('/session/{sessionId}/token', [QRAttendanceController::class, 'getDynamicToken'])->name('token');
        Route::post('/session/{sessionId}/close', [QRAttendanceController::class, 'closeSession'])->name('close');
        Route::get('/session/{sessionId}/summary', [QRAttendanceController::class, 'sessionSummary'])->name('summary');
    });
    Route::get('/hr/attendance/qr/scanner', [QRAttendanceController::class, 'scanner'])->name('hr.attendance.qr.scanner');
    Route::post('/hr/attendance/qr/scan', [QRAttendanceController::class, 'scan'])
        ->middleware('throttle:20,1')
        ->name('hr.attendance.qr.scan');

    // HR - Leave Management Engine
    Route::prefix('hr/leaves')->name('hr.leaves.')->middleware('role:ADMINISTRATOR,HR')->group(function () {
        Route::get('/preview-pdf', [LeaveController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [LeaveController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [LeaveController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [LeaveController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [LeaveController::class, 'print'])->name('print');
        Route::get('/', [LeaveController::class, 'index'])->name('index');
        Route::get('/create', [LeaveController::class, 'create'])->name('create');
        Route::post('/', [LeaveController::class, 'store'])->name('store');
        Route::get('/{id}/pdf', [LeaveController::class, 'downloadLeavePdf'])->name('pdf');
        Route::post('/{id}/approve', [LeaveController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [LeaveController::class, 'reject'])->name('reject');
        Route::post('/{id}/cancel', [LeaveController::class, 'cancel'])->name('cancel');
        Route::get('/{id}', [LeaveController::class, 'show'])->name('show');
    });
    Route::middleware('role:ADMINISTRATOR,HR')->get('/hr/leaves/{id}/pdf', [LeaveController::class, 'downloadLeavePdf'])->name('leaves.pdf');

    // HR - Overtime Management Engine
    Route::prefix('hr/overtimes')->name('hr.overtimes.')->middleware('role:ADMINISTRATOR,HR')->group(function () {
        Route::get('/preview-pdf', [OvertimeController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [OvertimeController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [OvertimeController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [OvertimeController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [OvertimeController::class, 'print'])->name('print');
        Route::get('/', [OvertimeController::class, 'index'])->name('index');
        Route::get('/create', [OvertimeController::class, 'create'])->name('create');
        Route::post('/', [OvertimeController::class, 'store'])->name('store');
        Route::get('/{id}/pdf', [OvertimeController::class, 'downloadOvertimePdf'])->name('pdf');
        Route::post('/{id}/approve', [OvertimeController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [OvertimeController::class, 'reject'])->name('reject');
        Route::get('/{id}', [OvertimeController::class, 'show'])->name('show');
    });
    Route::middleware('role:ADMINISTRATOR,HR')->get('/hr/overtimes/{id}/pdf', [OvertimeController::class, 'downloadOvertimePdf'])->name('overtimes.pdf');

    // Permission Management (Under Construction)
    /* Route::prefix('permissions')->name('permissions.')->group(function () {
        Route::get('/', [PermissionController::class, 'index'])->name('index');
        Route::get('/create', [PermissionController::class, 'create'])->name('create');
        Route::post('/', [PermissionController::class, 'store'])->name('store');
        Route::get('/{id}', [PermissionController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [PermissionController::class, 'edit'])->name('edit');
        Route::put('/{id}', [PermissionController::class, 'update'])->name('update');
        Route::delete('/{id}', [PermissionController::class, 'destroy'])->name('destroy');
    }); */

    // Approval / Workflow Management
    Route::prefix('approvals')->name('approvals.')->middleware('role:ADMINISTRATOR,DIRECTOR')->group(function () {
        Route::get('/', [ApprovalController::class, 'index'])->name('index');
        Route::get('/{id}', [ApprovalController::class, 'show'])->name('show');
        Route::post('/{id}/approve', [ApprovalController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [ApprovalController::class, 'reject'])->name('reject');
    });

    // Audit Log Management
    Route::prefix('audit')->name('audit.')->middleware('role:ADMINISTRATOR')->group(function () {
        Route::get('/preview-pdf', [AuditLogController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [AuditLogController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [AuditLogController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [AuditLogController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [AuditLogController::class, 'print'])->name('print');
        Route::get('/', [AuditLogController::class, 'index'])->name('index');
        Route::get('/statistics', [AuditLogController::class, 'statistics'])->name('statistics');
        Route::get('/{id}', [AuditLogController::class, 'show'])->name('show');
    });

    // Student Portal
    Route::prefix('student/portal')->name('student.portal.')->middleware('role:STUDENT')->group(function () {
        Route::get('/announcements', [StudentAnnouncementController::class, 'index'])->name('announcements');
        Route::get('/announcements/{id}', [StudentAnnouncementController::class, 'show'])->name('announcements.show');
        Route::get('/assignments', [StudentPortalController::class, 'assignments'])->name('assignments');
        Route::get('/assignments/{id}', [StudentPortalController::class, 'showAssignment'])->name('assignments.show');
        Route::get('/materials', [StudentPortalController::class, 'materials'])->name('materials');
    });

    // Student Billing (Invoices)
    Route::prefix('student/billing')->name('student.billing.')->middleware('role:STUDENT')->group(function () {
        Route::get('/', [StudentBillingController::class, 'index'])->name('index');
        Route::get('/self-service', [StudentBillingController::class, 'selfService'])->name('self-service');
        Route::post('/self-service', [StudentBillingController::class, 'selfServicePay'])->name('self-service.pay');
        Route::get('/payments/{paymentId}/proof', [StudentBillingController::class, 'downloadPaymentProof'])->name('payment-proof');
        Route::post('/payments/{paymentId}/proof', [StudentBillingController::class, 'replacePaymentProof'])->name('payment-proof.replace');
        Route::get('/payments/{id}/receipt', [PaymentController::class, 'downloadReceiptPdf'])->name('payment-receipt');
        Route::get('/{id}/pdf', [StudentBillingController::class, 'downloadInvoicePdf'])->name('invoice-pdf');
        Route::get('/{id}', [StudentBillingController::class, 'show'])->name('show');
        Route::post('/{id}/pay', [StudentBillingController::class, 'pay'])->name('pay');
        Route::get('/{id}/proof', [StudentBillingController::class, 'downloadProof'])->name('proof');
    });

    // API routes for dynamic UI
    Route::prefix('api')->group(function () {
        Route::get('/classes/{id}/students', [ClassController::class, 'getStudents'])->middleware('role:ADMINISTRATOR,ACADEMIC,TEACHER');
        Route::get('/employees/{id}', [EmployeeController::class, 'lookup'])->middleware('role:ADMINISTRATOR,HR,FINANCE');
        Route::get('/students/{id}', [StudentController::class, 'lookup'])->middleware('role:ADMINISTRATOR,ACADEMIC');
    });

    // System Settings Management — ADMINISTRATOR ONLY
    Route::prefix('settings')->name('settings.')->middleware('role:ADMINISTRATOR')->group(function () {
        Route::get('/', [SystemSettingController::class, 'index'])->name('index');
        Route::post('/update', [SystemSettingController::class, 'update'])->name('update');
        Route::post('/test-email', [SystemSettingController::class, 'sendTestEmail'])->name('test_email');
        Route::post('/clear-cache', [SystemSettingController::class, 'clearCache'])->name('clear_cache');
        Route::post('/reset-branding', [SystemSettingController::class, 'resetBranding'])->name('reset_branding');

        // Email Delivery Connection Center (EPS Rev.4.1)
        Route::prefix('email')->name('email.')->group(function () {
            Route::get('/connect/{provider}', [EmailDeliveryController::class, 'connectProvider'])->name('connect');
            Route::get('/callback/{provider}', [EmailDeliveryController::class, 'oauthCallback'])->name('callback');
            Route::post('/confirm', [EmailDeliveryController::class, 'confirmConnection'])->name('confirm');
            Route::get('/cancel', [EmailDeliveryController::class, 'cancelPreview'])->name('cancel');
            Route::post('/smtp/connect', [EmailDeliveryController::class, 'connectSmtp'])->name('smtp_connect');
            Route::post('/disconnect', [EmailDeliveryController::class, 'disconnect'])->name('disconnect');
            Route::post('/reconnect', [EmailDeliveryController::class, 'reconnect'])->name('reconnect');
            Route::post('/sender', [EmailDeliveryController::class, 'updateSender'])->name('sender');
            Route::post('/test', [EmailDeliveryController::class, 'sendTestEmail'])->name('test');
        });
    });

    // Finance Module Settings — FINANCE + ADMINISTRATOR
    Route::prefix('finance/settings')->name('finance.settings.')->middleware('role:FINANCE,ADMINISTRATOR')->group(function () {
        Route::get('/', [FinanceSettingsController::class, 'index'])->name('index');
        Route::post('/update', [FinanceSettingsController::class, 'update'])->name('update');
    });

    // HR Module Settings — HR + ADMINISTRATOR
    Route::prefix('hr/settings')->name('hr.settings.')->middleware('role:HR,ADMINISTRATOR')->group(function () {
        Route::get('/', [HrSettingsController::class, 'index'])->name('index');
        Route::post('/update', [HrSettingsController::class, 'update'])->name('update');
        Route::get('/attendance', [HrSettingsController::class, 'attendance'])->name('attendance');
    });

    // Academic Module Settings — ACADEMIC + ADMINISTRATOR
    Route::prefix('academic/settings')->name('academic.settings.')->middleware('role:ACADEMIC,ADMINISTRATOR')->group(function () {
        Route::get('/', [AcademicSettingsController::class, 'index'])->name('index');
        Route::post('/update', [AcademicSettingsController::class, 'update'])->name('update');
    });

    Route::prefix('academic')->middleware('role:ACADEMIC,ADMINISTRATOR')->group(function () {
        $academicControllers = [
            'batches' => BatchController::class,
            'classes' => App\Http\Controllers\Academic\ClassController::class,
            'assessments' => AssessmentController::class,
            'attendances' => AttendanceController::class,
            'schedules' => ScheduleController::class,
            'scores' => ScoreController::class,
            'subjects' => SubjectController::class,
        ];

        foreach ($academicControllers as $prefix => $controller) {
            Route::prefix($prefix)->name($prefix.'.')->group(function () use ($controller) {
                Route::get('/preview-pdf', [$controller, 'previewPdf'])->name('preview-pdf');
                Route::get('/export-pdf', [$controller, 'exportPdf'])->name('export-pdf');
                Route::get('/export-excel', [$controller, 'exportExcel'])->name('export-excel');
                Route::get('/export-csv', [$controller, 'exportCsv'])->name('export-csv');
                Route::get('/print', [$controller, 'print'])->name('print');

                Route::get('/', [$controller, 'index'])->name('index');
                Route::get('/create', [$controller, 'create'])->name('create');
                Route::post('/', [$controller, 'store'])->name('store');
                Route::get('/{id}', [$controller, 'show'])->name('show');
                Route::get('/{id}/edit', [$controller, 'edit'])->name('edit');
                Route::put('/{id}', [$controller, 'update'])->name('update');
                Route::delete('/{id}', [$controller, 'destroy'])->name('destroy');
            });
        }
    });

    // Shared announcement engine: existing Academic/Administrator/Master access,
    // extended to Teacher without exposing any other academic management module.
    Route::prefix('academic/announcements')->name('announcements.')->middleware('role:ACADEMIC,ADMINISTRATOR,TEACHER')->group(function () {
        $controller = AnnouncementController::class;
        Route::get('/preview-pdf', [$controller, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [$controller, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [$controller, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [$controller, 'exportCsv'])->name('export-csv');
        Route::get('/print', [$controller, 'print'])->name('print');
        Route::get('/', [$controller, 'index'])->name('index');
        Route::get('/create', [$controller, 'create'])->name('create');
        Route::post('/', [$controller, 'store'])->name('store');
        Route::post('/{id}/deactivate', [$controller, 'deactivate'])->name('deactivate');
        Route::get('/{id}', [$controller, 'show'])->name('show');
        Route::get('/{id}/edit', [$controller, 'edit'])->name('edit');
        Route::put('/{id}', [$controller, 'update'])->name('update');
        Route::delete('/{id}', [$controller, 'destroy'])->name('destroy');
    });

    // Assignments route that allows TEACHER access as well
    Route::prefix('academic')->middleware('role:ACADEMIC,ADMINISTRATOR,TEACHER')->group(function () {
        $assignmentController = AssignmentController::class;
        Route::prefix('assignments')->name('assignments.')->group(function () use ($assignmentController) {
            Route::get('/preview-pdf', [$assignmentController, 'previewPdf'])->name('preview-pdf');
            Route::get('/export-pdf', [$assignmentController, 'exportPdf'])->name('export-pdf');
            Route::get('/export-excel', [$assignmentController, 'exportExcel'])->name('export-excel');
            Route::get('/export-csv', [$assignmentController, 'exportCsv'])->name('export-csv');
            Route::get('/print', [$assignmentController, 'print'])->name('print');

            Route::get('/', [$assignmentController, 'index'])->name('index');
            Route::get('/create', [$assignmentController, 'create'])->name('create');
            Route::post('/', [$assignmentController, 'store'])->name('store');
            Route::get('/{id}', [$assignmentController, 'show'])->name('show');
            Route::get('/{id}/edit', [$assignmentController, 'edit'])->name('edit');
            Route::put('/{id}', [$assignmentController, 'update'])->name('update');
            Route::delete('/{id}', [$assignmentController, 'destroy'])->name('destroy');
        });
    });

    // Teacher Academic Workspace
    Route::prefix('teacher/workspace')->name('teacher.workspace.')->middleware('role:TEACHER')->group(function () {
        Route::get('/schedule', [TeacherWorkspaceController::class, 'schedule'])->name('schedule');
        Route::get('/classes', [TeacherWorkspaceController::class, 'myClasses'])->name('classes');
        Route::get('/classes/{classId}/students', [TeacherWorkspaceController::class, 'classStudents'])->name('classes.students');
        Route::get('/classes/{classId}/attendance', [TeacherWorkspaceController::class, 'classAttendance'])->name('classes.attendance');

        // New Operational Routes
        Route::get('/students', [TeacherWorkspaceController::class, 'students'])->name('students');
        Route::get('/attendances', [TeacherWorkspaceController::class, 'attendances'])->name('attendances');
        Route::get('/attendance-requests', [TeacherWorkspaceController::class, 'attendanceRequests'])->name('attendance-requests');
        Route::get('/attendance-requests/{id}', [AttendanceRequestController::class, 'show'])->name('attendance-requests.show');
        Route::post('/attendance-requests/{id}/approve', [AttendanceRequestController::class, 'approve'])->name('attendance-requests.approve');
        Route::post('/attendance-requests/{id}/reject', [AttendanceRequestController::class, 'reject'])->name('attendance-requests.reject');
        Route::get('/attendance-requests/{id}/evidence', [AttendanceRequestController::class, 'downloadEvidence'])->name('attendance-requests.evidence');
        Route::get('/scores', [TeacherWorkspaceController::class, 'scores'])->name('scores');
        Route::get('/scores/create', [TeacherWorkspaceController::class, 'scoresCreate'])->name('scores.create');
        Route::post('/scores', [TeacherWorkspaceController::class, 'scoresStore'])->name('scores.store');
        Route::get('/scores/{id}/edit', [TeacherWorkspaceController::class, 'scoresEdit'])->name('scores.edit');
        Route::put('/scores/{id}', [TeacherWorkspaceController::class, 'scoresUpdate'])->name('scores.update');
        Route::get('/assignments', [TeacherWorkspaceController::class, 'assignments'])->name('assignments');

        Route::get('/calendar', [TeacherWorkspaceController::class, 'calendar'])->name('calendar');
        Route::get('/reports', [TeacherWorkspaceController::class, 'reports'])->name('reports');
        Route::get('/reports/attendances.csv', [TeacherWorkspaceController::class, 'exportAttendancesCsv'])->name('reports.attendances-csv');
        Route::get('/reports/attendances.pdf', [TeacherWorkspaceController::class, 'exportAttendancesPdf'])->name('reports.attendances-pdf');
        Route::get('/reports/attendances/print', [TeacherWorkspaceController::class, 'printAttendances'])->name('reports.attendances-print');
        Route::get('/reports/scores.csv', [TeacherWorkspaceController::class, 'exportScoresCsv'])->name('reports.scores-csv');
        Route::get('/reports/scores.pdf', [TeacherWorkspaceController::class, 'exportScoresPdf'])->name('reports.scores-pdf');
        Route::get('/reports/scores/print', [TeacherWorkspaceController::class, 'printScores'])->name('reports.scores-print');
    });

    // Student Academic Workspace (Progress, Schedule)
    Route::prefix('student')->name('student.')->middleware('role:STUDENT')->group(function () {
        Route::get('/schedule', [StudentWorkspaceController::class, 'mySchedule'])->name('schedule');
        Route::get('/progress', [StudentWorkspaceController::class, 'progress'])->name('progress');
        Route::get('/subjects', [StudentWorkspaceController::class, 'mySubjects'])->name('subjects');
        Route::get('/calendar', [StudentWorkspaceController::class, 'calendar'])->name('calendar');

        Route::get('/export-scores', [StudentWorkspaceController::class, 'exportScoresCsv'])->name('export-scores');
        Route::get('/export-scores-pdf', [StudentWorkspaceController::class, 'exportScoresPdf'])->name('export-scores-pdf');
        Route::get('/print-scores', [StudentWorkspaceController::class, 'printScores'])->name('print-scores');
        Route::get('/export-attendances', [StudentWorkspaceController::class, 'exportAttendancesCsv'])->name('export-attendances');
        Route::get('/export-attendances-pdf', [StudentWorkspaceController::class, 'exportAttendancesPdf'])->name('export-attendances-pdf');
        Route::get('/print-attendances', [StudentWorkspaceController::class, 'printAttendances'])->name('print-attendances');
    });

    // Student Geo-Fenced QR Attendance
    Route::prefix('attendance/student')->name('attendances.student.')->middleware('role:STUDENT')->group(function () {
        Route::get('/scanner', [StudentQRAttendanceController::class, 'scanner'])->name('scanner');
        Route::post('/scan', [StudentQRAttendanceController::class, 'scan'])
            ->middleware('throttle:20,1')
            ->name('scan');
    });
    Route::get('/attendance/student/token', [StudentQRAttendanceController::class, 'getDynamicToken'])
        ->middleware('role:ACADEMIC,ADMINISTRATOR')
        ->name('attendances.student.token');

    // Profile Route
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    // Notification Management
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/preview-pdf', [NotificationController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [NotificationController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [NotificationController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [NotificationController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [NotificationController::class, 'print'])->name('print');
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllRead'])->name('markAllRead');
        Route::post('/{id}/read', [NotificationController::class, 'readAndRedirect'])->name('read');
        Route::post('/{id}/mark-read', [NotificationController::class, 'markRead'])->name('markRead');
        Route::post('/{id}/archive', [NotificationController::class, 'archive'])->name('archive');
        Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('destroy');
        Route::get('/{id}', [NotificationController::class, 'show'])->name('show');
    });

    Route::prefix('search')->name('search.')->group(function () {
        Route::get('/', [GlobalSearchController::class, 'index'])->name('index');
        Route::get('/overlay', [GlobalSearchController::class, 'overlay'])->name('overlay');
        Route::post('/clear-history', [GlobalSearchController::class, 'clearHistory'])->name('clearHistory');
    });

    Route::prefix('activity')->name('activity.')->group(function () {
        Route::get('/preview-pdf', [ActivityController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [ActivityController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [ActivityController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [ActivityController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [ActivityController::class, 'print'])->name('print');
        Route::get('/', [ActivityController::class, 'index'])->name('index');
        Route::get('/export', [ActivityController::class, 'export'])->name('export');
    });

    // HR Attendance Monitoring
    Route::get('/hr/attendance/monitoring', [AttendanceMonitoringController::class, 'index'])
        ->name('hr.attendance.monitoring')
        ->middleware('role:HR,ADMINISTRATOR');

    // Student Attendance History
    Route::get('/attendance/my-history', [AttendanceHistoryController::class, 'index'])
        ->name('attendances.my-history')
        ->middleware('role:STUDENT');

    // Student Attendance Requests
    Route::prefix('student/attendance/requests')->name('student.attendance.requests.')->middleware('role:STUDENT')->group(function () {
        Route::get('/', [App\Http\Controllers\Student\AttendanceRequestController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\Student\AttendanceRequestController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\Student\AttendanceRequestController::class, 'store'])->name('store');
        Route::get('/{id}/evidence', [App\Http\Controllers\Student\AttendanceRequestController::class, 'downloadEvidence'])->name('evidence');
    });

    // Academic Attendance Requests
    Route::prefix('academic/attendance/requests')->name('academic.attendance.requests.')->middleware('role:ACADEMIC,ADMINISTRATOR')->group(function () {
        Route::get('/', [AttendanceRequestController::class, 'index'])->name('index');
        Route::get('/{id}', [AttendanceRequestController::class, 'show'])->name('show');
        Route::post('/{id}/approve', [AttendanceRequestController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [AttendanceRequestController::class, 'reject'])->name('reject');
        Route::get('/{id}/evidence', [AttendanceRequestController::class, 'downloadEvidence'])->name('evidence');
    });

    // Permanent Attendance QR Management
    Route::prefix('attendance/qr')->name('attendance.qr.')->middleware('role:ADMINISTRATOR,MASTER')->group(function () {
        Route::get('/', [PermanentQrController::class, 'index'])->name('index');
        Route::post('/', [PermanentQrController::class, 'store'])->name('store');
        Route::post('/student-settings', [PermanentQrController::class, 'updateStudentAttendanceSettings'])->name('student-settings.update');
        Route::get('/{id}/preview', [PermanentQrController::class, 'preview'])->name('preview');
        Route::get('/{id}/print', [PermanentQrController::class, 'printView'])->name('print');
        Route::get('/{id}/pdf', [PermanentQrController::class, 'downloadPdf'])->name('pdf');
        Route::patch('/{id}', [PermanentQrController::class, 'update'])->name('update');
        Route::post('/{id}/deactivate', [PermanentQrController::class, 'deactivate'])->name('deactivate');
        Route::delete('/{id}', [PermanentQrController::class, 'destroy'])->name('destroy');
    });

    // Permanent Attendance QR Scanning Flow
    Route::get('/attendance/scan/{type}/{identifier}', [PermanentQrController::class, 'scanEntry'])->name('attendance.scan.entry');
    Route::post('/attendance/scan/{type}/{identifier}/verify', [PermanentQrController::class, 'scanVerify'])
        ->middleware('throttle:20,1')
        ->name('attendance.scan.verify');

    Route::prefix('teacher/quizzes')->name('teacher.quizzes.')->middleware('role:TEACHER')->group(function () {
        Route::get('/', [TeacherQuizController::class, 'index'])->name('index');
        Route::get('/create', [TeacherQuizController::class, 'create'])->name('create');
        Route::post('/', [TeacherQuizController::class, 'store'])->name('store');
        Route::get('/classes/{class}', [TeacherQuizController::class, 'classHub'])->name('class');
        Route::get('/classes/{class}/create', [TeacherQuizController::class, 'createForClass'])->name('class.create');
        Route::post('/classes/{class}', [TeacherQuizController::class, 'storeForClass'])->name('class.store');
        Route::get('/results', [TeacherQuizController::class, 'results'])->name('results');
        Route::get('/results/{result}', [TeacherQuizController::class, 'result'])->name('result');
        Route::get('/leaderboard/{class}', [TeacherQuizController::class, 'leaderboard'])->name('leaderboard');
        Route::get('/{quiz}', [TeacherQuizController::class, 'show'])->name('show');
        Route::get('/{quiz}/edit', [TeacherQuizController::class, 'edit'])->name('edit');
        Route::put('/{quiz}', [TeacherQuizController::class, 'update'])->name('update');
        Route::delete('/{quiz}', [TeacherQuizController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('student/quizzes')->name('student.quizzes.')->middleware('role:STUDENT')->group(function () {
        Route::get('/', [StudentQuizController::class, 'index'])->name('index');
        Route::get('/leaderboard', [StudentQuizController::class, 'leaderboard'])->name('leaderboard');
        Route::post('/{quiz}/start', [StudentQuizController::class, 'start'])->middleware('throttle:20,1')->name('start');
        Route::get('/attempts/{attempt}', [StudentQuizController::class, 'play'])->name('play');
        Route::post('/attempts/{attempt}/submit', [StudentQuizController::class, 'submit'])->middleware('throttle:10,1')->name('submit');
        Route::get('/results/{result}', [StudentQuizController::class, 'result'])->name('result');
    });

});
