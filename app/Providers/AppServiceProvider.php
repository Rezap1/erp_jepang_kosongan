<?php

namespace App\Providers;

use App\Contracts\ExternalGifProvider;
use App\Interfaces\GoogleSheets\AcademicYearRepositoryInterface;
use App\Interfaces\GoogleSheets\AccountRepositoryInterface;
use App\Interfaces\GoogleSheets\ActivityLogRepositoryInterface;
use App\Interfaces\GoogleSheets\AlumniRepositoryInterface;
use App\Interfaces\GoogleSheets\AnnouncementRepositoryInterface;
use App\Interfaces\GoogleSheets\ApprovalHistoryRepositoryInterface;
use App\Interfaces\GoogleSheets\ApprovalRepositoryInterface;
use App\Interfaces\GoogleSheets\AssessmentConfigRepositoryInterface;
use App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;
use App\Interfaces\GoogleSheets\AssignmentRepositoryInterface;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\AttendanceRequestRepositoryInterface;
use App\Interfaces\GoogleSheets\AuditLogRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassEnrollmentRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\DepartmentRepositoryInterface;
use App\Interfaces\GoogleSheets\DocumentRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\LeaveRepositoryInterface;
use App\Interfaces\GoogleSheets\ModuleRepositoryInterface;
use App\Interfaces\GoogleSheets\NotificationRepositoryInterface;
use App\Interfaces\GoogleSheets\OvertimeRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\PayrollRepositoryInterface;
use App\Interfaces\GoogleSheets\PermanentQrRepositoryInterface;
use App\Interfaces\GoogleSheets\PositionRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\QuizAttemptRepositoryInterface;
use App\Interfaces\GoogleSheets\QuizQuestionRepositoryInterface;
use App\Interfaces\GoogleSheets\QuizRepositoryInterface;
use App\Interfaces\GoogleSheets\QuizResultRepositoryInterface;
use App\Interfaces\GoogleSheets\RoleRepositoryInterface;
use App\Interfaces\GoogleSheets\SalaryComponentRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\ScoreRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\SubjectRepositoryInterface;
use App\Interfaces\GoogleSheets\SystemParameterRepositoryInterface;
use App\Interfaces\GoogleSheets\SystemSettingRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use App\Interfaces\GoogleSheets\WorkflowRepositoryInterface;
use App\Repositories\GoogleSheets\AcademicYearRepository;
use App\Repositories\GoogleSheets\AccountRepository;
use App\Repositories\GoogleSheets\ActivityLogRepository;
use App\Repositories\GoogleSheets\AlumniRepository;
use App\Repositories\GoogleSheets\AnnouncementRepository;
use App\Repositories\GoogleSheets\ApprovalHistoryRepository;
use App\Repositories\GoogleSheets\ApprovalRepository;
use App\Repositories\GoogleSheets\AssessmentConfigRepository;
use App\Repositories\GoogleSheets\AssessmentRepository;
use App\Repositories\GoogleSheets\AssignmentRepository;
use App\Repositories\GoogleSheets\AttendanceRepository;
use App\Repositories\GoogleSheets\AttendanceRequestRepository;
use App\Repositories\GoogleSheets\AuditLogRepository;
use App\Repositories\GoogleSheets\BatchRepository;
use App\Repositories\GoogleSheets\ClassEnrollmentRepository;
use App\Repositories\GoogleSheets\ClassRepository;
use App\Repositories\GoogleSheets\CompanyRepository;
use App\Repositories\GoogleSheets\DepartmentRepository;
use App\Repositories\GoogleSheets\DocumentRepository;
use App\Repositories\GoogleSheets\EmployeeRepository;
use App\Repositories\GoogleSheets\InvoiceRepository;
use App\Repositories\GoogleSheets\LeaveRepository;
// Phase 9.3 & 9.4 Bindings
use App\Repositories\GoogleSheets\ModuleRepository;
use App\Repositories\GoogleSheets\NotificationRepository;
use App\Repositories\GoogleSheets\OvertimeRepository;
use App\Repositories\GoogleSheets\PaymentRepository;
use App\Repositories\GoogleSheets\PayrollRepository;
use App\Repositories\GoogleSheets\PermanentQrRepository;
use App\Repositories\GoogleSheets\PositionRepository;
use App\Repositories\GoogleSheets\ProgramRepository;
use App\Repositories\GoogleSheets\QuizAttemptRepository;
use App\Repositories\GoogleSheets\QuizQuestionRepository;
use App\Repositories\GoogleSheets\QuizRepository;
use App\Repositories\GoogleSheets\QuizResultRepository;
use App\Repositories\GoogleSheets\RoleRepository;
use App\Repositories\GoogleSheets\SalaryComponentRepository;
use App\Repositories\GoogleSheets\ScheduleRepository;
use App\Repositories\GoogleSheets\ScoreRepository;
use App\Repositories\GoogleSheets\StudentRepository;
use App\Repositories\GoogleSheets\SubjectRepository;
use App\Repositories\GoogleSheets\SystemParameterRepository;
use App\Repositories\GoogleSheets\SystemSettingRepository;
use App\Repositories\GoogleSheets\TeacherRepository;
use App\Repositories\GoogleSheets\TransactionRepository;
use App\Repositories\GoogleSheets\UserRepository;
use App\Repositories\GoogleSheets\WorkflowRepository;
use App\Services\Academic\DisabledExternalGifProvider;
use App\Services\Core\EnterpriseAutomationService;
use App\Services\Core\SystemSettingService;
use App\Services\Core\UserService;
use App\Services\Dashboard\DashboardContextService;
use App\Support\LoginRateLimiter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
        $this->app->bind(ActivityLogRepositoryInterface::class, ActivityLogRepository::class);
        $this->app->bind(DepartmentRepositoryInterface::class, DepartmentRepository::class);
        $this->app->bind(PositionRepositoryInterface::class, PositionRepository::class);
        $this->app->bind(EmployeeRepositoryInterface::class, EmployeeRepository::class);
        $this->app->bind(TeacherRepositoryInterface::class, TeacherRepository::class);
        $this->app->bind(ProgramRepositoryInterface::class, ProgramRepository::class);
        $this->app->bind(BatchRepositoryInterface::class, BatchRepository::class);
        $this->app->bind(ClassRepositoryInterface::class, ClassRepository::class);
        $this->app->bind(StudentRepositoryInterface::class, StudentRepository::class);
        $this->app->bind(AlumniRepositoryInterface::class, AlumniRepository::class);
        $this->app->bind(CompanyRepositoryInterface::class, CompanyRepository::class);
        $this->app->bind(ModuleRepositoryInterface::class, ModuleRepository::class);
        $this->app->bind(DocumentRepositoryInterface::class, DocumentRepository::class);
        $this->app->bind(AcademicYearRepositoryInterface::class, AcademicYearRepository::class);
        $this->app->bind(ClassEnrollmentRepositoryInterface::class, ClassEnrollmentRepository::class);
        $this->app->bind(SubjectRepositoryInterface::class, SubjectRepository::class);
        $this->app->bind(ScheduleRepositoryInterface::class, ScheduleRepository::class);
        $this->app->bind(AttendanceRepositoryInterface::class, AttendanceRepository::class);
        $this->app->bind(AttendanceRequestRepositoryInterface::class, AttendanceRequestRepository::class);
        $this->app->bind(ScoreRepositoryInterface::class, ScoreRepository::class);
        $this->app->bind(AssignmentRepositoryInterface::class, AssignmentRepository::class);
        $this->app->bind(AnnouncementRepositoryInterface::class, AnnouncementRepository::class);
        $this->app->bind(NotificationRepositoryInterface::class, NotificationRepository::class);
        $this->app->bind(AssessmentRepositoryInterface::class, AssessmentRepository::class);
        $this->app->bind(InvoiceRepositoryInterface::class, InvoiceRepository::class);
        $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);
        $this->app->bind(AccountRepositoryInterface::class, AccountRepository::class);
        $this->app->bind(TransactionRepositoryInterface::class, TransactionRepository::class);
        $this->app->bind(PayrollRepositoryInterface::class, PayrollRepository::class);
        $this->app->bind(LeaveRepositoryInterface::class, LeaveRepository::class);
        $this->app->bind(OvertimeRepositoryInterface::class, OvertimeRepository::class);
        $this->app->bind(SalaryComponentRepositoryInterface::class, SalaryComponentRepository::class);
        $this->app->bind(WorkflowRepositoryInterface::class, WorkflowRepository::class);
        $this->app->bind(ApprovalRepositoryInterface::class, ApprovalRepository::class);
        $this->app->bind(ApprovalHistoryRepositoryInterface::class, ApprovalHistoryRepository::class);
        $this->app->bind(AuditLogRepositoryInterface::class, AuditLogRepository::class);
        $this->app->singleton(SystemSettingRepositoryInterface::class, SystemSettingRepository::class);
        $this->app->singleton(SystemParameterRepositoryInterface::class, SystemParameterRepository::class);

        $this->app->singleton(PermanentQrRepositoryInterface::class, PermanentQrRepository::class);
        $this->app->singleton(AssessmentConfigRepositoryInterface::class, AssessmentConfigRepository::class);
        $this->app->bind(QuizRepositoryInterface::class, QuizRepository::class);
        $this->app->bind(QuizQuestionRepositoryInterface::class, QuizQuestionRepository::class);
        $this->app->bind(QuizAttemptRepositoryInterface::class, QuizAttemptRepository::class);
        $this->app->bind(QuizResultRepositoryInterface::class, QuizResultRepository::class);
        $this->app->bind(ExternalGifProvider::class, DisabledExternalGifProvider::class);
        $this->app->singleton(EnterpriseAutomationService::class, function ($app) {
            return new EnterpriseAutomationService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        app()->setLocale('id');
        Carbon::setLocale('id');

        RateLimiter::for('login', function (Request $request) {
            return LoginRateLimiter::limits($request);
        });

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Auth::provider('wms_mysql', function ($app, array $config) {
            return new MySqlUserProvider($app->make(UserService::class));
        });

        view()->composer('*', function ($view) {
            try {
                static $brandingPayload = null;

                if ($brandingPayload === null) {
                    $settingService = app(SystemSettingService::class);
                    $brandingPayload = [
                        'themeTokens' => $settingService->getThemeTokens(),
                        'companyProfile' => $settingService->getCompanyProfile(),
                    ];
                }

                $view->with('themeTokens', $brandingPayload['themeTokens']);
                $view->with('companyProfile', $brandingPayload['companyProfile']);
            } catch (\Throwable $e) {
                // Safe fallback
            }
        });

        // Dashboard Context Composer
        view()->composer(['dashboard.*', 'components.dashboard-header', 'components.mobile-dashboard-hero'], function ($view) {
            try {
                $request = request();
                $preparedContext = $view->getData()['dashboardContext'] ?? null;
                if (is_array($preparedContext)) {
                    $request->attributes->set('wms.dashboard_context', $preparedContext);
                }

                $resolvedDashboardContext = $request->attributes->get('wms.dashboard_context');
                if ($resolvedDashboardContext === null) {
                    $contextService = app(DashboardContextService::class);
                    $resolvedDashboardContext = $contextService->getContext();
                    $request->attributes->set('wms.dashboard_context', $resolvedDashboardContext);
                }

                $view->with('dashboardContext', $resolvedDashboardContext);
            } catch (\Throwable $e) {
                // Safe fallback
                $view->with('dashboardContext', [
                    'time' => date('H:i:s'),
                    'date' => date('Y-m-d'),
                    'greeting' => 'Selamat datang',
                    'greeting_icon' => '👋',
                    'timezone' => 'Asia/Jakarta',
                    'user_name' => auth()->user()->Full_Name ?? 'User',
                    'role' => auth()->user()->Role ?? 'USER',
                    'timestamp' => time(),
                ]);
            }
        });

    }
}
