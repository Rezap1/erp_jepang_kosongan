<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\RoleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Academic\AnnouncementVisibilityService;
use App\Services\Core\RoleService;
use Illuminate\Auth\GenericUser;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AnnouncementVisibilityServiceTest extends TestCase
{
    private AnnouncementVisibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $roles = collect(['ADMINISTRATOR', 'HR', 'ACADEMIC', 'MARKETING', 'FINANCE', 'DIRECTOR', 'TEACHER', 'STUDENT'])
            ->map(fn ($name) => ['Role_ID' => 'ROLE-'.$name, 'Role_Name' => $name, 'Is_Active' => 'TRUE'])->all();
        $roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $roleRepo->shouldReceive('fetchAll')->andReturn(collect($roles));
        $roleRepo->shouldReceive('findById')->andReturnUsing(fn ($id) => collect($roles)->firstWhere('Role_ID', $id));
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->andReturn(collect([
            ['Student_ID' => 'STD-A', 'User_ID' => 'USR-STUDENT-A', 'Batch_ID' => 'BAT-A', 'Class_ID' => 'CLS-A', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STD-B', 'User_ID' => 'USR-STUDENT-B', 'Batch_ID' => 'BAT-A', 'Class_ID' => 'CLS-B', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STD-C', 'User_ID' => 'USR-STUDENT-C', 'Batch_ID' => 'BAT-B', 'Class_ID' => 'CLS-C', 'Is_Active' => 'TRUE'],
        ]));
        $batches = Mockery::mock(BatchRepositoryInterface::class);
        $batches->shouldReceive('findById')->andReturnUsing(fn ($id) => in_array($id, ['BAT-A', 'BAT-B'], true) ? ['Batch_ID' => $id, 'Batch_Name' => $id, 'Is_Active' => 'TRUE'] : null);
        $classes = Mockery::mock(ClassRepositoryInterface::class);
        $classes->shouldReceive('findById')->andReturnUsing(fn ($id) => collect([
            ['Class_ID' => 'CLS-A', 'Batch_ID' => 'BAT-A', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-B', 'Batch_ID' => 'BAT-A', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-C', 'Batch_ID' => 'BAT-B', 'Is_Active' => 'TRUE'],
        ])->firstWhere('Class_ID', $id));
        $this->service = new AnnouncementVisibilityService(new RoleService($roleRepo), $studentRepo, $batches, $classes);
    }

    #[DataProvider('roles')]
    public function test_all_roles_is_visible_to_every_canonical_role(string $role): void
    {
        $this->assertTrue($this->service->canView($this->user($role), ['Audience_Type' => 'ALL_ROLES']));
    }

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], ['ADMINISTRATOR', 'HR', 'ACADEMIC', 'MARKETING', 'FINANCE', 'DIRECTOR', 'TEACHER', 'STUDENT']);
    }

    public function test_role_and_student_batch_class_are_isolated_and_unmapped_student_fails_closed(): void
    {
        $teacher = ['Audience_Type' => 'ROLE', 'Target_Role' => 'TEACHER'];
        $class = ['Audience_Type' => 'ROLE', 'Target_Role' => 'STUDENT', 'Target_Batch_ID' => 'BAT-A', 'Target_Class_ID' => 'CLS-A'];
        $this->assertTrue($this->service->canView($this->user('TEACHER'), $teacher));
        $this->assertFalse($this->service->canView($this->user('HR'), $teacher));
        $this->assertTrue($this->service->canView($this->user('STUDENT', 'USR-STUDENT-A'), $class));
        $this->assertFalse($this->service->canView($this->user('STUDENT', 'USR-STUDENT-B'), $class));
        $this->assertFalse($this->service->canView($this->user('STUDENT', 'USR-MISSING'), $class));
    }

    public function test_canonicalization_clears_stale_children_and_rejects_cross_batch_class(): void
    {
        $all = $this->service->canonicalize(['Audience_Type' => 'ALL_ROLES', 'Target_Role' => 'STUDENT', 'Target_Batch_ID' => 'BAT-A', 'Target_Class_ID' => 'CLS-A']);
        $this->assertNull($all['Target_Role']);
        $this->assertNull($all['Target_Batch_ID']);
        $this->assertNull($all['Target_Class_ID']);

        $nonStudent = $this->service->canonicalize(['Audience_Type' => 'ROLE', 'Target_Role' => 'TEACHER', 'Target_Batch_ID' => 'BAT-A', 'Target_Class_ID' => 'CLS-A']);
        $this->assertNull($nonStudent['Target_Batch_ID']);
        $this->assertNull($nonStudent['Target_Class_ID']);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->canonicalize(['Audience_Type' => 'ROLE', 'Target_Role' => 'STUDENT', 'Target_Batch_ID' => 'BAT-A', 'Target_Class_ID' => 'CLS-C']);
    }

    private function user(string $role, ?string $id = null): GenericUser
    {
        $id ??= 'USR-'.$role;

        return new GenericUser(['id' => $id, 'User_ID' => $id, 'Role_ID' => 'ROLE-'.$role]);
    }
}
