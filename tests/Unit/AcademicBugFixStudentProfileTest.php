<?php

namespace Tests\Unit;

use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassEnrollmentRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Repositories\GoogleSheets\BatchRepository;
use App\Repositories\GoogleSheets\ClassRepository;
use App\Repositories\GoogleSheets\StudentRepository;
use App\Services\Academic\AssessmentService;
use App\Services\Academic\AttendanceService;
use App\Services\Academic\PlacementService;
use App\Services\Academic\ScheduleService;
use App\Services\Academic\ScoreService;
use App\Services\Core\ActivityLogService;
use App\Services\Core\ClassService;
use App\Services\Core\NotificationService;
use App\Services\Core\StudentService;
use App\Services\Core\TeacherService;
use App\Services\Dashboard\TeacherDashboardService;
use App\Support\Academic\TeacherScopeResolver;
use App\Support\Presentation\IndonesianPresentation;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Mockery;
use Tests\TestCase;

class AcademicBugFixStudentProfileTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (['MASTER_STUDENT', 'MASTER_CLASS', 'MASTER_BATCH'] as $table) {
            Schema::dropIfExists($table);
        }

        Mockery::close();
        parent::tearDown();
    }

    public function test_explicit_homeroom_assignment_overrides_stale_schedule_class_assignment(): void
    {
        $classes = [
            ['Class_ID' => 'CLS-A', 'Homeroom_Teacher_ID' => 'TCH-H', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-B', 'Homeroom_Teacher_ID' => 'TCH-D', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-C', 'Homeroom_Teacher_ID' => 'TCH-D', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-D', 'Homeroom_Teacher_ID' => 'TCH-H', 'Is_Active' => 'TRUE'],
        ];
        $schedules = [
            ['Schedule_ID' => 'SCH-STALE-A', 'Teacher_ID' => 'TCH-D', 'Class_ID' => 'CLS-A', 'Is_Active' => 'TRUE'],
            ['Schedule_ID' => 'SCH-STALE-B', 'Teacher_ID' => 'TCH-H', 'Class_ID' => 'CLS-B', 'Is_Active' => 'TRUE'],
        ];

        $dScope = $this->scopeResolver($classes, $schedules)->resolveForTeacherId('TCH-D');
        $hScope = $this->scopeResolver($classes, $schedules)->resolveForTeacherId('TCH-H');

        $this->assertSame(['CLS-B', 'CLS-C'], $dScope['managed_class_ids']);
        $this->assertSame(['CLS-A', 'CLS-D'], $hScope['managed_class_ids']);
        $this->assertSame(['SCH-STALE-A'], $dScope['schedule_ids']);
        $this->assertSame(['SCH-STALE-B'], $hScope['schedule_ids']);
    }

    public function test_student_move_rolls_back_class_counts_when_student_write_fails(): void
    {
        config()->set('wms_runtime.connection', config('database.default'));
        config()->set('wms_runtime.allow_missing_test_schema', true);

        Schema::create('MASTER_BATCH', function ($table): void {
            $table->string('Batch_ID')->primary();
            $table->string('Is_Active')->nullable();
        });
        Schema::create('MASTER_CLASS', function ($table): void {
            $table->string('Class_ID')->primary();
            $table->string('Batch_ID')->nullable();
            $table->integer('Capacity')->nullable();
            $table->integer('Current_Student')->nullable();
            $table->string('Is_Active')->nullable();
            $table->dateTime('Updated_At')->nullable();
        });
        Schema::create('MASTER_STUDENT', function ($table): void {
            $table->string('Student_ID')->primary();
            $table->string('Batch_ID')->nullable();
            $table->string('Class_ID')->nullable();
            $table->dateTime('Updated_At')->nullable();
            $table->string('Updated_By')->nullable();
        });

        DB::table('MASTER_BATCH')->insert(['Batch_ID' => 'BAT-1', 'Is_Active' => 1]);
        DB::table('MASTER_CLASS')->insert([
            ['Class_ID' => 'CLS-OLD', 'Batch_ID' => 'BAT-1', 'Capacity' => 10, 'Current_Student' => 1, 'Is_Active' => 1],
            ['Class_ID' => 'CLS-NEW', 'Batch_ID' => 'BAT-1', 'Capacity' => 10, 'Current_Student' => 0, 'Is_Active' => 1],
        ]);
        DB::table('MASTER_STUDENT')->insert([
            'Student_ID' => 'STU-1', 'Batch_ID' => 'BAT-1', 'Class_ID' => 'CLS-OLD',
        ]);
        DB::statement("CREATE TRIGGER reject_student_move BEFORE UPDATE OF Class_ID ON MASTER_STUDENT WHEN NEW.Class_ID = 'CLS-NEW' BEGIN SELECT RAISE(ABORT, 'forced failure'); END");

        $service = new PlacementService(
            new StudentRepository,
            new ClassRepository,
            new BatchRepository,
            Mockery::mock(ActivityLogService::class)
        );

        try {
            $service->placeStudent('STU-1', 'BAT-1', 'CLS-NEW', 'USR-TEST');
            $this->fail('Write siswa seharusnya dipaksa gagal.');
        } catch (QueryException) {
            $this->assertSame('CLS-OLD', DB::table('MASTER_STUDENT')->where('Student_ID', 'STU-1')->value('Class_ID'));
            $this->assertSame(1, (int) DB::table('MASTER_CLASS')->where('Class_ID', 'CLS-OLD')->value('Current_Student'));
            $this->assertSame(0, (int) DB::table('MASTER_CLASS')->where('Class_ID', 'CLS-NEW')->value('Current_Student'));
        }
    }

    public function test_teacher_dashboard_student_count_follows_current_homeroom_assignments(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-D', 'User_ID' => 'USR-D', 'Role' => 'TEACHER']));
        $service = new TeacherDashboardService(
            Mockery::mock(ScheduleService::class)->shouldReceive('getAll')->andReturn(collect([
                ['Schedule_ID' => 'SCH-STALE', 'Teacher_ID' => 'TCH-D', 'Class_ID' => 'CLS-A', 'Is_Active' => 'TRUE'],
            ]))->getMock(),
            Mockery::mock(AttendanceService::class)->shouldReceive('getAll')->andReturn(collect())->getMock(),
            Mockery::mock(AssessmentService::class)->shouldReceive('getAll')->andReturn(collect())->getMock(),
            Mockery::mock(ScoreService::class)->shouldReceive('getAll')->andReturn(collect())->getMock(),
            Mockery::mock(TeacherService::class)->shouldReceive('getAllTeachers')->andReturn(collect([
                ['Teacher_ID' => 'TCH-D', 'User_ID' => 'USR-D', 'Full_Name' => 'Guru D'],
            ]))->getMock(),
            Mockery::mock(StudentService::class)->shouldReceive('getAllStudents')->andReturn(collect([
                ['Student_ID' => 'STU-A', 'Class_ID' => 'CLS-A', 'Is_Active' => 'TRUE'],
                ['Student_ID' => 'STU-B', 'Class_ID' => 'CLS-B', 'Is_Active' => 'TRUE'],
                ['Student_ID' => 'STU-C', 'Class_ID' => 'CLS-C', 'Is_Active' => 'TRUE'],
            ]))->getMock(),
            Mockery::mock(ClassService::class)->shouldReceive('getAllClasses')->andReturn(collect([
                ['Class_ID' => 'CLS-A', 'Homeroom_Teacher_ID' => 'TCH-H', 'Is_Active' => 'TRUE'],
                ['Class_ID' => 'CLS-B', 'Homeroom_Teacher_ID' => 'TCH-D', 'Is_Active' => 'TRUE'],
                ['Class_ID' => 'CLS-C', 'Homeroom_Teacher_ID' => 'TCH-D', 'Is_Active' => 'TRUE'],
            ]))->getMock(),
            Mockery::mock(ActivityLogService::class)->shouldReceive('getAllLogs')->andReturn(collect())->getMock(),
            Mockery::mock(NotificationService::class)->shouldReceive('UnreadCount')->andReturn(0)->getMock()
        );

        $dashboard = $service->getDashboardData();

        $this->assertSame(2, $dashboard['kpi']['my_students']);
    }

    public function test_schedule_update_route_remains_limited_to_academic_and_administrator(): void
    {
        $middleware = Route::getRoutes()->getByName('schedules.update')->gatherMiddleware();

        $this->assertContains('auth', $middleware);
        $this->assertContains('role:ACADEMIC,ADMINISTRATOR', $middleware);
        $this->assertNotContains('role:TEACHER', $middleware);
    }

    public function test_student_profile_renders_gender_complete_fields_and_clear_empty_labels(): void
    {
        View::share('errors', new ViewErrorBag);
        $html = view('students.show', [
            'student' => [
                'Student_ID' => 'STU-1',
                'Student_Number' => 'NIS-001',
                'Full_Name' => 'Siswa Uji',
                'Gender' => 'P',
                'Birth_Place' => '',
                'Birth_Date' => '',
                'National_ID' => '',
                'Phone_Number' => '',
                'Email' => '',
                'Address' => '',
                'Education' => 'SMA / SMK Sederajat',
                'Program_Name' => 'Program Jepang',
                'Batch_Name' => 'Angkatan 2026',
                'Class_Name' => 'Kelas B',
                'Registration_Date' => '2026-07-01',
                'Enrollment_Status' => 'Aktif Belajar',
                'Graduation_Status' => 'Belum Lulus',
                'Is_Active' => 'TRUE',
                'Created_At' => '',
                'Updated_At' => '',
            ],
            'alumni' => null,
        ])->render();

        $this->assertStringContainsString('Jenis Kelamin', $html);
        $this->assertStringContainsString('Perempuan', $html);
        $this->assertStringContainsString('Tanggal Pendaftaran', $html);
        $this->assertStringContainsString('Belum diisi', $html);
        $this->assertStringContainsString('sm:grid-cols-2', $html);
        $this->assertStringNotContainsString('User ID (SSOT)', $html);
    }

    public function test_gender_presentation_normalizes_supported_legacy_values(): void
    {
        $this->assertSame('Laki-laki', IndonesianPresentation::gender('Male'));
        $this->assertSame('Perempuan', IndonesianPresentation::gender('P'));
        $this->assertSame('Belum diisi', IndonesianPresentation::gender(''));
    }

    public function test_student_create_and_update_require_canonical_gender_selection(): void
    {
        $storeRules = (new StoreStudentRequest)->rules();
        $updateRules = (new UpdateStudentRequest)->rules();

        $this->assertSame('required|in:Laki-laki,Perempuan', $storeRules['Gender']);
        $this->assertSame('required|in:Laki-laki,Perempuan', $updateRules['Gender']);

        View::share('errors', new ViewErrorBag);
        $html = view('students.edit', [
            'student' => [
                'Student_ID' => 'STU-1', 'Student_Number' => 'NIS-001', 'User_ID' => 'USR-1',
                'Full_Name' => 'Siswa Uji', 'Email' => '', 'Phone_Number' => '', 'Gender' => 'Perempuan',
                'Program_ID' => 'PRG-1', 'Batch_ID' => 'BAT-1', 'Class_ID' => 'CLS-1',
                'Registration_Date' => '2026-07-01', 'Enrollment_Status' => 'Aktif Belajar',
                'Graduation_Status' => 'Belum Lulus', 'Education' => 'SMA / SMK Sederajat',
                'Birth_Place' => '', 'Birth_Date' => '', 'National_ID' => '', 'Address' => '',
                'Is_Active' => 'TRUE', 'Notes' => '',
            ],
            'users' => [['User_ID' => 'USR-1', 'Full_Name' => 'Siswa Uji', 'Email' => '', 'Phone_Number' => '', 'Role_ID' => '']],
            'programs' => [['Program_ID' => 'PRG-1', 'Program_Name' => 'Program Jepang', 'Is_Active' => 'TRUE']],
            'batches' => [['Batch_ID' => 'BAT-1', 'Batch_Name' => 'Angkatan 2026', 'Program_ID' => 'PRG-1', 'Is_Active' => 'TRUE']],
            'classes' => [['Class_ID' => 'CLS-1', 'Class_Name' => 'Kelas B', 'Batch_ID' => 'BAT-1', 'Is_Active' => 'TRUE']],
        ])->render();

        $this->assertStringContainsString('name="Gender"', $html);
        $this->assertStringContainsString('Jenis Kelamin', $html);
        $this->assertStringContainsString('value="Perempuan"', $html);
    }

    private function scopeResolver(array $classes, array $schedules): TeacherScopeResolver
    {
        $teachers = $this->repositoryMock(TeacherRepositoryInterface::class, []);
        $scheduleRepository = $this->repositoryMock(ScheduleRepositoryInterface::class, $schedules);
        $classRepository = $this->repositoryMock(ClassRepositoryInterface::class, $classes);
        $studentRepository = $this->repositoryMock(StudentRepositoryInterface::class, []);
        $enrollmentRepository = $this->repositoryMock(ClassEnrollmentRepositoryInterface::class, []);
        $assessments = Mockery::mock(AssessmentRepositoryInterface::class);
        $assessments->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect());

        return new TeacherScopeResolver(
            $teachers,
            $scheduleRepository,
            $classRepository,
            $studentRepository,
            $enrollmentRepository,
            $assessments
        );
    }

    private function repositoryMock(string $interface, array $rows)
    {
        $repository = Mockery::mock($interface);
        $repository->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect($rows));

        return $repository;
    }
}
