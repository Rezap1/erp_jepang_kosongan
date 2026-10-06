<?php

namespace Tests\Feature;

use App\Http\Controllers\Academic\AnnouncementController;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\RoleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Academic\AnnouncementVisibilityService;
use App\Services\Core\ClassService;
use App\Services\Core\RoleService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;
use Mockery;
use Tests\TestCase;

class AnnouncementAudienceValidationHotfixTest extends TestCase
{
    private AnnouncementVisibilityService $visibility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->visibility = $this->visibility();
        $this->app->instance(AnnouncementVisibilityService::class, $this->visibility);

        Route::middleware('web')->post('/_test/announcement-audience-validation', function (StoreAnnouncementRequest $request) {
            return response()->json($request->validated());
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_actual_create_form_payload_passes_and_preserves_multi_selection(): void
    {
        $this->post('/_test/announcement-audience-validation', $this->payload())
            ->assertOk()
            ->assertJsonPath('Audience_Type', 'SELECTED_ROLES')
            ->assertJsonPath('Target_Roles.0', 'ROLE-HR')
            ->assertJsonPath('Target_Roles.2', 'ROLE-STUDENT')
            ->assertJsonPath('Target_Class_IDs.0', 'CLS-A1')
            ->assertJsonPath('Target_Class_IDs.1', 'CLS-A2');
    }

    public function test_validation_failure_is_field_specific_and_preserves_old_input(): void
    {
        $payload = $this->payload();
        $payload['Target_Roles'] = [];

        $this->from('/academic/announcements/create')
            ->post('/_test/announcement-audience-validation', $payload)
            ->assertRedirect('/academic/announcements/create')
            ->assertSessionHasErrors(['Target_Roles' => 'Pilih minimal satu role penerima.']);

        $this->assertSame($payload['Title'], session()->getOldInput('Title'));
        $this->assertSame([], session()->getOldInput('Target_Roles'));
        $this->assertSame($payload['Target_Class_IDs'], session()->getOldInput('Target_Class_IDs'));
    }

    public function test_cross_batch_and_malformed_class_selection_have_specific_errors(): void
    {
        $crossBatch = $this->payload();
        $crossBatch['Target_Class_IDs'] = ['CLS-B1'];
        $this->post('/_test/announcement-audience-validation', $crossBatch)
            ->assertSessionHasErrors(['Target_Class_IDs' => 'Kelas yang dipilih tidak termasuk dalam batch tersebut.']);

        $malformed = $this->payload();
        $malformed['Target_Class_IDs'] = 'CLS-A1';
        $this->post('/_test/announcement-audience-validation', $malformed)
            ->assertSessionHasErrors(['Target_Class_IDs' => 'Pilihan kelas tidak valid.']);
    }

    public function test_student_role_requires_an_explicit_student_scope(): void
    {
        $payload = $this->payload();
        unset($payload['Student_Scope']);

        $this->post('/_test/announcement-audience-validation', $payload)
            ->assertSessionHasErrors(['Student_Scope' => 'Pilih target siswa.']);
    }

    public function test_selected_class_scope_without_a_browser_rendered_class_fails_on_the_exact_field(): void
    {
        $payload = $this->payload();
        unset($payload['Target_Class_IDs']);

        $this->from('/academic/announcements/create')
            ->post('/_test/announcement-audience-validation', $payload)
            ->assertRedirect('/academic/announcements/create')
            ->assertSessionHasErrors(['Target_Class_IDs' => 'Pilih minimal satu kelas.']);

        $this->assertSame('BAT-A', session()->getOldInput('Target_Batch_ID'));
        $this->assertSame('BATCH_SELECTED_CLASSES', session()->getOldInput('Student_Scope'));
    }

    public function test_form_has_server_hydrated_role_and_preloaded_batch_class_map(): void
    {
        view()->share('errors', new ViewErrorBag);
        $html = view('academic.announcements._form-fields', [
            'announcement' => [],
            'roles' => $this->visibility->availableRoles(),
            'batches' => collect([['Batch_ID' => 'BAT-A', 'Batch_Name' => 'Batch A']]),
            'classesByBatch' => ['BAT-A' => [['id' => 'CLS-A1', 'name' => 'Kelas A1']]],
            'studentRoleId' => 'ROLE-STUDENT',
            'audienceState' => [
                'audience' => 'SELECTED_ROLES', 'roles' => ['ROLE-STUDENT'],
                'student_scope' => 'BATCH_SELECTED_CLASSES', 'batch' => 'BAT-A', 'classes' => ['CLS-A1'],
            ],
        ])->render();

        $this->assertMatchesRegularExpression('/value="ROLE-STUDENT"[^>]*checked/', $html);
        $this->assertStringContainsString('"BAT-A"', $html);
        $this->assertStringContainsString('CLS-A1', $html);
        $this->assertStringNotContainsString('x-show="batch ===', $html);
        $this->assertStringContainsString('Target_Class_IDs[]', $html);
        $this->assertStringContainsString('batchChanged()', $html);
        $this->assertStringContainsString('this.classes=[]', $html);
        $this->assertStringContainsString('selectAllRoles(){this.roles=[];this.clearStudent()}', $html);
    }

    public function test_production_container_injects_the_authoritative_class_service_into_create_controller(): void
    {
        $controller = $this->app->make(AnnouncementController::class);
        $property = new \ReflectionProperty($controller, 'classService');

        $this->assertInstanceOf(ClassService::class, $property->getValue($controller));
    }

    private function payload(): array
    {
        return [
            'Title' => 'Pengumuman Hotfix',
            'Message' => 'Payload aktual dari form create.',
            'Audience_Type' => 'SELECTED_ROLES',
            'Target_Roles' => ['ROLE-HR', 'ROLE-TEACHER', 'ROLE-STUDENT'],
            'Student_Scope' => 'BATCH_SELECTED_CLASSES',
            'Target_Batch_ID' => 'BAT-A',
            'Target_Class_IDs' => ['CLS-A1', 'CLS-A2'],
            'Priority' => 'NORMAL',
            'Status' => 'PUBLISHED',
            'Start_At' => '2026-10-06T09:00',
            'Expires_At' => '2026-12-06T09:00',
        ];
    }

    private function visibility(): AnnouncementVisibilityService
    {
        $roles = collect([
            ['Role_ID' => 'ROLE-HR', 'Role_Name' => 'HR', 'Is_Active' => 'TRUE'],
            ['Role_ID' => 'ROLE-TEACHER', 'Role_Name' => 'TEACHER', 'Is_Active' => 'TRUE'],
            ['Role_ID' => 'ROLE-STUDENT', 'Role_Name' => 'STUDENT', 'Is_Active' => 'TRUE'],
        ]);
        $roleRepository = Mockery::mock(RoleRepositoryInterface::class);
        $roleRepository->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn($roles);
        $roleRepository->shouldReceive('findById')->zeroOrMoreTimes()->andReturnUsing(fn (string $id) => $roles->firstWhere('Role_ID', $id));
        $students = Mockery::mock(StudentRepositoryInterface::class);
        $students->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect());
        $batches = Mockery::mock(BatchRepositoryInterface::class);
        $batches->shouldReceive('findById')->zeroOrMoreTimes()->andReturnUsing(fn (string $id) => $id === 'BAT-A' ? ['Batch_ID' => $id, 'Is_Active' => 'TRUE'] : null);
        $classes = collect([
            ['Class_ID' => 'CLS-A1', 'Batch_ID' => 'BAT-A', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-A2', 'Batch_ID' => 'BAT-A', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-B1', 'Batch_ID' => 'BAT-B', 'Is_Active' => 'TRUE'],
        ]);
        $classRepository = Mockery::mock(ClassRepositoryInterface::class);
        $classRepository->shouldReceive('findById')->zeroOrMoreTimes()->andReturnUsing(fn (string $id) => $classes->firstWhere('Class_ID', $id));
        $classRepository->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn($classes);

        return new AnnouncementVisibilityService(new RoleService($roleRepository), $students, $batches, $classRepository);
    }
}
