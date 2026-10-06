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
use Tests\TestCase;

class AnnouncementAudienceHotfixTest extends TestCase
{
    private AnnouncementVisibilityService $visibility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->visibility = $this->service();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_actual_hotfix_form_payload_is_canonicalized_for_multiple_roles_and_classes(): void
    {
        $canonical = $this->visibility->canonicalize([
            'Audience_Type' => 'SELECTED_ROLES',
            'Target_Roles' => ['ROLE-HR', 'ROLE-TEACHER', 'ROLE-STUDENT'],
            'Student_Scope' => 'BATCH_SELECTED_CLASSES',
            'Target_Batch_ID' => 'BAT-A',
            'Target_Class_IDs' => ['CLS-A1', 'CLS-A2'],
        ]);

        $this->assertSame(['ROLE-HR', 'ROLE-TEACHER', 'ROLE-STUDENT'], $canonical['Audience_Role_IDs']);
        $this->assertSame(['CLS-A1', 'CLS-A2'], $canonical['Audience_Class_IDs']);
        $this->assertSame('BATCH_SELECTED_CLASSES', $canonical['Student_Scope']);
    }

    public function test_batch_class_map_contains_only_classes_from_the_selected_batch(): void
    {
        $classes = $this->visibility->classesForBatch('BAT-A');

        $this->assertSame(['CLS-A1', 'CLS-A2', 'CLS-A3'], array_column($classes, 'Class_ID'));
        $this->assertNotContains('CLS-B1', array_column($classes, 'Class_ID'));
    }

    public function test_role_selection_supports_one_two_many_and_deduplicates_ids(): void
    {
        foreach ([
            ['ROLE-HR'],
            ['ROLE-HR', 'ROLE-TEACHER'],
            ['ROLE-HR', 'ROLE-TEACHER', 'ROLE-FINANCE', 'ROLE-HR'],
        ] as $roles) {
            $canonical = $this->visibility->canonicalize([
                'Audience_Type' => 'SELECTED_ROLES',
                'Target_Roles' => $roles,
            ]);
            $this->assertSame(array_values(array_unique($roles)), $canonical['Audience_Role_IDs']);
        }
    }

    public function test_student_scopes_validate_batch_and_multiple_classes(): void
    {
        $all = $this->visibility->canonicalize($this->studentPayload('ALL_STUDENTS'));
        $batch = $this->visibility->canonicalize($this->studentPayload('BATCH_ALL_CLASSES', 'BAT-A'));
        $classes = $this->visibility->canonicalize($this->studentPayload('BATCH_SELECTED_CLASSES', 'BAT-A', ['CLS-A1', 'CLS-A2']));

        $this->assertNull($all['Target_Batch_ID']);
        $this->assertSame('BAT-A', $batch['Target_Batch_ID']);
        $this->assertSame(['CLS-A1', 'CLS-A2'], $classes['Audience_Class_IDs']);

        try {
            $this->visibility->canonicalize($this->studentPayload('BATCH_SELECTED_CLASSES', 'BAT-A', ['CLS-B1']));
            $this->fail('Cross-batch class was accepted.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('tidak termasuk', $e->getMessage());
        }
    }

    public function test_unknown_role_batch_and_class_are_rejected_fail_closed(): void
    {
        foreach ([
            ['Audience_Type' => 'SELECTED_ROLES', 'Target_Roles' => ['ROLE-UNKNOWN']],
            $this->studentPayload('BATCH_ALL_CLASSES', 'BAT-UNKNOWN'),
            $this->studentPayload('BATCH_SELECTED_CLASSES', 'BAT-A', ['CLS-UNKNOWN']),
        ] as $payload) {
            try {
                $this->visibility->canonicalize($payload);
                $this->fail('Tampered audience identifier was accepted.');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_mixed_hr_teacher_student_audience_isolated_exactly(): void
    {
        $announcement = [
            'Announcement_ID' => 'ANC-MIXED',
            'Audience_Type' => 'SELECTED_ROLES',
            'Student_Scope' => 'BATCH_SELECTED_CLASSES',
            'Target_Batch_ID' => 'BAT-A',
            '_Audience_Roles' => [
                ['Role_ID' => 'ROLE-HR', 'Role_Name' => 'HR'],
                ['Role_ID' => 'ROLE-TEACHER', 'Role_Name' => 'TEACHER'],
                ['Role_ID' => 'ROLE-STUDENT', 'Role_Name' => 'STUDENT'],
            ],
            '_Audience_Classes' => [
                ['Class_ID' => 'CLS-A1'],
                ['Class_ID' => 'CLS-A2'],
            ],
        ];

        $this->assertTrue($this->visibility->canView($this->user('USR-HR', 'ROLE-HR'), $announcement));
        $this->assertTrue($this->visibility->canView($this->user('USR-TEACHER', 'ROLE-TEACHER'), $announcement));
        $this->assertTrue($this->visibility->canView($this->user('USR-A1', 'ROLE-STUDENT'), $announcement));
        $this->assertTrue($this->visibility->canView($this->user('USR-A2', 'ROLE-STUDENT'), $announcement));
        $this->assertFalse($this->visibility->canView($this->user('USR-A3', 'ROLE-STUDENT'), $announcement));
        $this->assertFalse($this->visibility->canView($this->user('USR-B1', 'ROLE-STUDENT'), $announcement));
        $this->assertFalse($this->visibility->canView($this->user('USR-FINANCE', 'ROLE-FINANCE'), $announcement));
        $this->assertCount(1, $this->visibility->visibleTo($this->user('USR-HR', 'ROLE-HR'), [$announcement]));
    }

    public function test_student_all_and_batch_scopes_are_enforced_exactly(): void
    {
        $announcement = [
            'Announcement_ID' => 'ANC-STUDENT-SCOPE',
            'Audience_Type' => 'SELECTED_ROLES',
            '_Audience_Roles' => [['Role_ID' => 'ROLE-STUDENT', 'Role_Name' => 'STUDENT']],
            '_Audience_Classes' => [],
        ];

        $announcement['Student_Scope'] = 'ALL_STUDENTS';
        $this->assertTrue($this->visibility->canView($this->user('USR-A1', 'ROLE-STUDENT'), $announcement));
        $this->assertTrue($this->visibility->canView($this->user('USR-B1', 'ROLE-STUDENT'), $announcement));

        $announcement['Student_Scope'] = 'BATCH_ALL_CLASSES';
        $announcement['Target_Batch_ID'] = 'BAT-A';
        $this->assertTrue($this->visibility->canView($this->user('USR-A3', 'ROLE-STUDENT'), $announcement));
        $this->assertFalse($this->visibility->canView($this->user('USR-B1', 'ROLE-STUDENT'), $announcement));
        $this->assertFalse($this->visibility->canView($this->user('USR-HR', 'ROLE-HR'), $announcement));
    }

    private function studentPayload(string $scope, ?string $batch = null, array $classes = []): array
    {
        return [
            'Audience_Type' => 'SELECTED_ROLES',
            'Target_Roles' => ['ROLE-STUDENT'],
            'Student_Scope' => $scope,
            'Target_Batch_ID' => $batch,
            'Target_Class_IDs' => $classes,
        ];
    }

    private function user(string $userId, string $roleId): GenericUser
    {
        return new GenericUser(['id' => $userId, 'User_ID' => $userId, 'Role_ID' => $roleId]);
    }

    private function service(): AnnouncementVisibilityService
    {
        $roleRepository = Mockery::mock(RoleRepositoryInterface::class);
        $roleRows = collect([
            ['Role_ID' => 'ROLE-HR', 'Role_Name' => 'HR', 'Is_Active' => 'TRUE'],
            ['Role_ID' => 'ROLE-TEACHER', 'Role_Name' => 'TEACHER', 'Is_Active' => 'TRUE'],
            ['Role_ID' => 'ROLE-STUDENT', 'Role_Name' => 'STUDENT', 'Is_Active' => 'TRUE'],
            ['Role_ID' => 'ROLE-FINANCE', 'Role_Name' => 'FINANCE', 'Is_Active' => 'TRUE'],
        ]);
        $roleRepository->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn($roleRows);
        $roleRepository->shouldReceive('findById')->zeroOrMoreTimes()->andReturnUsing(fn (string $id) => $roleRows->firstWhere('Role_ID', $id));

        $students = Mockery::mock(StudentRepositoryInterface::class);
        $students->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect([
            ['User_ID' => 'USR-A1', 'Batch_ID' => 'BAT-A', 'Class_ID' => 'CLS-A1', 'Is_Active' => 'TRUE'],
            ['User_ID' => 'USR-A2', 'Batch_ID' => 'BAT-A', 'Class_ID' => 'CLS-A2', 'Is_Active' => 'TRUE'],
            ['User_ID' => 'USR-A3', 'Batch_ID' => 'BAT-A', 'Class_ID' => 'CLS-A3', 'Is_Active' => 'TRUE'],
            ['User_ID' => 'USR-B1', 'Batch_ID' => 'BAT-B', 'Class_ID' => 'CLS-B1', 'Is_Active' => 'TRUE'],
        ]));
        $batches = Mockery::mock(BatchRepositoryInterface::class);
        $batches->shouldReceive('findById')->zeroOrMoreTimes()->andReturnUsing(
            fn (string $id) => $id === 'BAT-A' ? ['Batch_ID' => 'BAT-A', 'Is_Active' => 'TRUE'] : null
        );
        $classRows = collect([
            ['Class_ID' => 'CLS-A1', 'Batch_ID' => 'BAT-A', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-A2', 'Batch_ID' => 'BAT-A', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-A3', 'Batch_ID' => 'BAT-A', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-B1', 'Batch_ID' => 'BAT-B', 'Is_Active' => 'TRUE'],
        ]);
        $classes = Mockery::mock(ClassRepositoryInterface::class);
        $classes->shouldReceive('findById')->zeroOrMoreTimes()->andReturnUsing(fn (string $id) => $classRows->firstWhere('Class_ID', $id));
        $classes->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn($classRows);

        return new AnnouncementVisibilityService(new RoleService($roleRepository), $students, $batches, $classes);
    }
}
