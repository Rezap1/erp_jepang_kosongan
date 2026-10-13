<?php

namespace Tests\Unit;

use App\Http\Controllers\Academic\TeacherWorkspaceController;
use App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassEnrollmentRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Services\Academic\AssessmentConfigService;
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
use App\Support\Academic\TeacherIdentityResolver;
use App\Support\Academic\TeacherScopeResolver;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class HomeroomClassSynchronizationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_hafidz_and_dede_receive_only_master_class_homeroom_assignments(): void
    {
        $resolver = $this->scopeResolver();

        $hafidz = $resolver->resolveForUser($this->user('USR-H', 'EMP-H'));
        $dede = $resolver->resolveForUser($this->user('USR-D', 'EMP-D'));

        $this->assertSame('TCH-H', $hafidz['teacher_id']);
        $this->assertSame(['CLS-A', 'CLS-D'], $hafidz['managed_class_ids']);
        $this->assertSame(['CLS-A', 'CLS-C'], $hafidz['teaching_class_ids']);
        $this->assertEqualsCanonicalizing(['STU-A', 'STU-D'], $hafidz['managed_student_ids']);

        $this->assertSame('TCH-D', $dede['teacher_id']);
        $this->assertSame(['CLS-B', 'CLS-C'], $dede['managed_class_ids']);
        $this->assertSame(['CLS-B', 'CLS-D'], $dede['teaching_class_ids']);
        $this->assertEqualsCanonicalizing(['STU-B', 'STU-C', 'STU-MOVED'], $dede['managed_student_ids']);

        $this->assertNotContains('STU-MOVED', $hafidz['managed_student_ids']);
        $this->assertFalse($resolver->managedClassAllowed($hafidz, 'CLS-C'));
        $this->assertFalse($resolver->managedClassAllowed($dede, 'CLS-A'));
    }

    public function test_identity_mapping_fails_closed_for_mismatch_inactive_and_ambiguous_links(): void
    {
        $canonical = $this->identityResolver(
            [
                ['Teacher_ID' => 'TCH-H', 'Employee_ID' => 'EMP-H', 'User_ID' => 'USR-H', 'Is_Active' => 'TRUE'],
                ['Teacher_ID' => 'TCH-I', 'Employee_ID' => 'EMP-I', 'User_ID' => 'USR-I', 'Is_Active' => 'FALSE'],
            ],
            [
                ['Employee_ID' => 'EMP-H', 'User_ID' => 'USR-H', 'Is_Active' => 'TRUE'],
                ['Employee_ID' => 'EMP-I', 'User_ID' => 'USR-I', 'Is_Active' => 'TRUE'],
            ]
        );

        $this->assertSame('TCH-H', $canonical->resolveForUser($this->user('USR-H', 'EMP-H'))['teacher_id']);
        $this->assertNull($canonical->resolveForUser($this->user('USR-H', 'EMP-I')));
        $this->assertNull($canonical->resolveForUser($this->user('USR-I', 'EMP-I')));

        $ambiguousLegacy = $this->identityResolver([
            ['Teacher_ID' => 'TCH-1', 'Employee_ID' => '', 'User_ID' => 'USR-OLD', 'Is_Active' => 'TRUE'],
            ['Teacher_ID' => 'TCH-2', 'Employee_ID' => '', 'User_ID' => 'USR-OLD', 'Is_Active' => 'TRUE'],
        ], []);
        $this->assertNull($ambiguousLegacy->resolveForUser($this->user('USR-OLD')));
    }

    public function test_legacy_direct_mapping_is_unique_and_does_not_expand_null_or_inactive_classes(): void
    {
        $identity = $this->identityResolver([
            ['Teacher_ID' => 'TCH-OLD', 'Employee_ID' => '', 'User_ID' => 'USR-OLD', 'Is_Active' => 'TRUE'],
        ], []);
        $resolved = $identity->resolveForUser($this->user('USR-OLD'));

        $this->assertSame('TCH-OLD', $resolved['teacher_id']);
        $this->assertSame('legacy_user', $resolved['mapping_mode']);

        $scope = $this->scopeResolver(
            teachers: [['Teacher_ID' => 'TCH-OLD', 'Employee_ID' => '', 'User_ID' => 'USR-OLD', 'Is_Active' => 'TRUE']],
            employees: [],
            classes: [
                ['Class_ID' => 'CLS-NULL', 'Homeroom_Teacher_ID' => null, 'Is_Active' => 'TRUE'],
                ['Class_ID' => 'CLS-INACTIVE', 'Homeroom_Teacher_ID' => 'TCH-OLD', 'Is_Active' => 'FALSE'],
            ],
            schedules: [['Schedule_ID' => 'SCH-OLD', 'Teacher_ID' => 'TCH-OLD', 'Class_ID' => 'CLS-NULL', 'Is_Active' => 'TRUE']],
            students: []
        )->resolveForUser($this->user('USR-OLD'));

        $this->assertSame([], $scope['managed_class_ids']);
        $this->assertSame(['CLS-NULL'], $scope['teaching_class_ids']);
    }

    public function test_direct_url_for_another_teachers_homeroom_class_is_forbidden(): void
    {
        $scope = [
            'teacher_id' => 'TCH-D',
            'managed_class_ids' => ['CLS-B', 'CLS-C'],
            'managed_students_by_class' => ['CLS-B' => [], 'CLS-C' => []],
        ];
        $scopeResolver = Mockery::mock(TeacherScopeResolver::class);
        $scopeResolver->shouldReceive('resolveForUser')->zeroOrMoreTimes()->andReturn($scope);
        $scopeResolver->shouldReceive('resolveForTeacherId')->zeroOrMoreTimes()->with('TCH-D')->andReturn($scope);

        $controller = new TeacherWorkspaceController(
            Mockery::mock(TeacherService::class),
            Mockery::mock(ClassService::class),
            Mockery::mock(ScheduleService::class),
            Mockery::mock(StudentService::class),
            Mockery::mock(AttendanceService::class),
            Mockery::mock(AttendanceRequestService::class),
            Mockery::mock(SubjectService::class),
            Mockery::mock(BatchService::class),
            Mockery::mock(AssignmentService::class),
            Mockery::mock(ScoreService::class),
            Mockery::mock(AssessmentConfigService::class),
            $scopeResolver
        );

        $this->app->instance(TeacherWorkspaceController::class, $controller);
        $this->withoutMiddleware();
        $this->actingAs($this->user('USR-D', 'EMP-D'))
            ->get(route('teacher.workspace.classes.students', 'CLS-A'))
            ->assertForbidden();
    }

    public function test_assignment_cache_is_request_local_and_refreshes_from_master_class(): void
    {
        $teacherRepo = $this->repo(TeacherRepositoryInterface::class, [
            ['Teacher_ID' => 'TCH-H', 'Employee_ID' => 'EMP-H', 'User_ID' => 'USR-H', 'Is_Active' => 'TRUE'],
        ]);
        $employeeRepo = $this->repo(EmployeeRepositoryInterface::class, [
            ['Employee_ID' => 'EMP-H', 'User_ID' => 'USR-H', 'Is_Active' => 'TRUE'],
        ]);
        $classRepo = Mockery::mock(ClassRepositoryInterface::class);
        $classRepo->shouldReceive('fetchAll')->twice()->andReturn(
            collect([['Class_ID' => 'CLS-A', 'Homeroom_Teacher_ID' => 'TCH-H', 'Is_Active' => 'TRUE']]),
            collect([['Class_ID' => 'CLS-D', 'Homeroom_Teacher_ID' => 'TCH-H', 'Is_Active' => 'TRUE']])
        );
        $assessmentRepo = Mockery::mock(AssessmentRepositoryInterface::class);
        $assessmentRepo->shouldReceive('getAll')->twice()->andReturn(collect());
        $resolver = new TeacherScopeResolver(
            $teacherRepo,
            $this->repo(ScheduleRepositoryInterface::class, []),
            $classRepo,
            $this->repo(StudentRepositoryInterface::class, []),
            $assessmentRepo,
            new TeacherIdentityResolver($teacherRepo, $employeeRepo)
        );

        $this->app->instance('request', Request::create('/first'));
        $this->assertSame(['CLS-A'], $resolver->resolveForUser($this->user('USR-H', 'EMP-H'))['managed_class_ids']);
        $this->app->instance('request', Request::create('/second'));
        $this->assertSame(['CLS-D'], $resolver->resolveForUser($this->user('USR-H', 'EMP-H'))['managed_class_ids']);
    }

    private function scopeResolver(
        ?array $teachers = null,
        ?array $employees = null,
        ?array $classes = null,
        ?array $schedules = null,
        ?array $students = null
    ): TeacherScopeResolver {
        $teachers ??= [
            ['Teacher_ID' => 'TCH-H', 'Employee_ID' => 'EMP-H', 'User_ID' => 'USR-H', 'Is_Active' => 'TRUE'],
            ['Teacher_ID' => 'TCH-D', 'Employee_ID' => 'EMP-D', 'User_ID' => 'USR-D', 'Is_Active' => 'TRUE'],
        ];
        $employees ??= [
            ['Employee_ID' => 'EMP-H', 'User_ID' => 'USR-H', 'Is_Active' => 'TRUE'],
            ['Employee_ID' => 'EMP-D', 'User_ID' => 'USR-D', 'Is_Active' => 'TRUE'],
        ];
        $classes ??= [
            ['Class_ID' => 'CLS-A', 'Class_Name' => 'Kelas A', 'Homeroom_Teacher_ID' => 'TCH-H', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-B', 'Class_Name' => 'Kelas B', 'Homeroom_Teacher_ID' => 'TCH-D', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-C', 'Class_Name' => 'Kelas C', 'Homeroom_Teacher_ID' => 'TCH-D', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-D', 'Class_Name' => 'Kelas D', 'Homeroom_Teacher_ID' => 'TCH-H', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-NULL', 'Homeroom_Teacher_ID' => null, 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-INACTIVE', 'Homeroom_Teacher_ID' => 'TCH-H', 'Is_Active' => 'FALSE'],
        ];
        $schedules ??= [
            ['Schedule_ID' => 'SCH-H-A', 'Teacher_ID' => 'TCH-H', 'Class_ID' => 'CLS-A', 'Is_Active' => 'TRUE'],
            ['Schedule_ID' => 'SCH-H-C', 'Teacher_ID' => 'TCH-H', 'Class_ID' => 'CLS-C', 'Is_Active' => 'TRUE'],
            ['Schedule_ID' => 'SCH-D-B', 'Teacher_ID' => 'TCH-D', 'Class_ID' => 'CLS-B', 'Is_Active' => 'TRUE'],
            ['Schedule_ID' => 'SCH-D-D', 'Teacher_ID' => 'TCH-D', 'Class_ID' => 'CLS-D', 'Is_Active' => 'TRUE'],
        ];
        $students ??= [
            ['Student_ID' => 'STU-A', 'Class_ID' => 'CLS-A', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STU-B', 'Class_ID' => 'CLS-B', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STU-C', 'Class_ID' => 'CLS-C', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STU-D', 'Class_ID' => 'CLS-D', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STU-MOVED', 'Class_ID' => 'CLS-C', 'Is_Active' => 'TRUE'],
        ];

        $teacherRepo = $this->repo(TeacherRepositoryInterface::class, $teachers);
        $employeeRepo = $this->repo(EmployeeRepositoryInterface::class, $employees);
        $legacyEnrollmentRepo = Mockery::mock(ClassEnrollmentRepositoryInterface::class);
        $legacyEnrollmentRepo->shouldNotReceive('fetchAll');
        $this->app->instance(ClassEnrollmentRepositoryInterface::class, $legacyEnrollmentRepo);
        $assessmentRepo = Mockery::mock(AssessmentRepositoryInterface::class);
        $assessmentRepo->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect());

        return new TeacherScopeResolver(
            $teacherRepo,
            $this->repo(ScheduleRepositoryInterface::class, $schedules),
            $this->repo(ClassRepositoryInterface::class, $classes),
            $this->repo(StudentRepositoryInterface::class, $students),
            $assessmentRepo,
            new TeacherIdentityResolver($teacherRepo, $employeeRepo)
        );
    }

    private function identityResolver(array $teachers, array $employees): TeacherIdentityResolver
    {
        return new TeacherIdentityResolver(
            $this->repo(TeacherRepositoryInterface::class, $teachers),
            $this->repo(EmployeeRepositoryInterface::class, $employees)
        );
    }

    private function repo(string $interface, array $rows)
    {
        $repo = Mockery::mock($interface);
        $repo->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect($rows));

        return $repo;
    }

    private function user(string $userId, string $employeeId = ''): GenericUser
    {
        return new GenericUser([
            'id' => $userId,
            'User_ID' => $userId,
            'Employee_ID' => $employeeId,
            'Role' => 'TEACHER',
            'Is_Active' => 'TRUE',
        ]);
    }
}
