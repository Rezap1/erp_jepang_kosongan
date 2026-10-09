<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Core\AvatarResolver;
use App\Services\Core\EmployeeService;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AvatarResolverTest extends TestCase
{
    public function test_student_photo_is_resolved_from_exact_user_mapping_and_rendered(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profiles/student_STD-A.jpg', 'image-a');

        $resolver = $this->resolver(
            students: [['User_ID' => 'USR-A', 'Student_ID' => 'STD-A']],
        );

        $user = new GenericUser(['User_ID' => 'USR-A', 'Full_Name' => 'Student A']);
        $url = $resolver->resolve($user);

        $this->assertNotNull($url);
        $this->assertStringContainsString('/storage/profiles/student_STD-A.jpg', $url);
        $this->app->instance(AvatarResolver::class, $resolver);
        $html = view('components.user-avatar', ['user' => $user])->render();
        $this->assertStringContainsString('src="'.e($url).'"', $html);
    }

    public function test_missing_or_malformed_photo_safely_falls_back_without_external_url(): void
    {
        Storage::fake('public');

        $resolver = $this->resolver(
            students: [[
                'User_ID' => 'USR-A',
                'Student_ID' => 'STD-A',
                'Profile_Photo' => '../student_STD-B.jpg',
            ]],
        );

        $this->assertNull($resolver->resolve(new GenericUser([
            'User_ID' => 'USR-A',
            'Full_Name' => 'Student A',
        ])));
    }

    public function test_two_students_and_two_teachers_cannot_receive_each_others_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profiles/student_STD-A.jpg', 'a');
        Storage::disk('public')->put('profiles/student_STD-B.jpg', 'b');
        Storage::disk('public')->put('profiles/employee_EMP-A.jpg', 'teacher-a');
        Storage::disk('public')->put('profiles/employee_EMP-B.jpg', 'teacher-b');

        $employeeRepo = Mockery::mock(EmployeeRepositoryInterface::class);
        $employeeRepo->shouldReceive('fetchAll')->andReturn([
            ['User_ID' => 'USR-TA', 'Employee_ID' => 'EMP-A'],
            ['User_ID' => 'USR-TB', 'Employee_ID' => 'EMP-B'],
        ]);
        $employeeService = Mockery::mock(EmployeeService::class);
        $employeeService->shouldReceive('getProfilePhotoPath')->andReturnUsing(
            fn (string $id) => 'storage/profiles/employee_'.$id.'.jpg'
        );
        $resolver = new AvatarResolver(
            $employeeRepo,
            Mockery::mock(StudentRepositoryInterface::class),
            $employeeService,
        );

        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->andReturn([
            ['User_ID' => 'USR-A', 'Student_ID' => 'STD-A'],
            ['User_ID' => 'USR-B', 'Student_ID' => 'STD-B'],
        ]);
        $resolver = new AvatarResolver($employeeRepo, $studentRepo, $employeeService);

        $studentA = $resolver->resolve(new GenericUser(['User_ID' => 'USR-A']));
        $studentB = $resolver->resolve(new GenericUser(['User_ID' => 'USR-B']));
        $teacherA = $resolver->resolve(new GenericUser(['User_ID' => 'USR-TA']));
        $teacherB = $resolver->resolve(new GenericUser(['User_ID' => 'USR-TB']));

        $this->assertStringContainsString('student_STD-A.jpg', $studentA);
        $this->assertStringContainsString('student_STD-B.jpg', $studentB);
        $this->assertStringContainsString('employee_EMP-A.jpg', $teacherA);
        $this->assertStringContainsString('employee_EMP-B.jpg', $teacherB);
        $this->assertNotSame($studentA, $studentB);
        $this->assertNotSame($teacherA, $teacherB);
    }

    public function test_unresolved_identity_never_uses_first_student_or_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profiles/student_STD-A.jpg', 'a');

        $resolver = $this->resolver(
            students: [['User_ID' => 'USR-A', 'Student_ID' => 'STD-A']],
        );

        $this->assertNull($resolver->resolve(new GenericUser([
            'id' => 'USR-UNKNOWN',
            'Full_Name' => 'Student A',
        ])));
    }

    private function resolver(array $students = []): AvatarResolver
    {
        $employeeRepo = Mockery::mock(EmployeeRepositoryInterface::class);
        $employeeRepo->shouldReceive('fetchAll')->andReturn([]);
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->andReturn($students);
        $employeeService = Mockery::mock(EmployeeService::class);
        $employeeService->shouldReceive('getProfilePhotoPath')->andReturn(null);

        return new AvatarResolver($employeeRepo, $studentRepo, $employeeService);
    }
}
