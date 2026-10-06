<?php

namespace Tests\Feature;

use App\Http\Controllers\Academic\AnnouncementController;
use App\Interfaces\GoogleSheets\AnnouncementRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\RoleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Academic\AnnouncementService;
use App\Services\Academic\AnnouncementVisibilityService;
use App\Services\Core\ClassService;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class AnnouncementAudiencePersistenceHotfixTest extends TestCase
{
    private AnnouncementService $announcements;

    private AnnouncementVisibilityService $visibility;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['ANNOUNCEMENT_AUDIENCE_CLASS', 'ANNOUNCEMENT_AUDIENCE_ROLE', 'MASTER_ANNOUNCEMENT', 'MASTER_CLASS', 'MASTER_BATCH', 'MASTER_ROLE'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('MASTER_ROLE', function (Blueprint $table): void {
            $table->string('Role_ID')->primary();
            $table->string('Role_Name');
        });
        Schema::create('MASTER_BATCH', function (Blueprint $table): void {
            $table->string('Batch_ID')->primary();
            $table->string('Batch_Name');
        });
        Schema::create('MASTER_CLASS', function (Blueprint $table): void {
            $table->string('Class_ID')->primary();
            $table->string('Batch_ID');
        });
        Schema::create('MASTER_ANNOUNCEMENT', function (Blueprint $table): void {
            $table->string('Announcement_ID')->primary();
            $table->string('Audience_Type', 20)->nullable();
            $table->string('Target_Role')->nullable();
            $table->string('Target_Batch_ID')->nullable();
            $table->string('Target_Class_ID')->nullable();
        });
        $migration = require database_path('migrations/wms_mysql/2026_10_06_000002_add_announcement_multi_audience.php');
        $migration->up();

        $roles = collect([
            ['Role_ID' => 'ROLE-ADMINISTRATOR', 'Role_Name' => 'ADMINISTRATOR', 'Is_Active' => 'TRUE'],
            ['Role_ID' => 'ROLE-HR', 'Role_Name' => 'HR', 'Is_Active' => 'TRUE'],
            ['Role_ID' => 'ROLE-FINANCE', 'Role_Name' => 'FINANCE', 'Is_Active' => 'TRUE'],
            ['Role_ID' => 'ROLE-TEACHER', 'Role_Name' => 'TEACHER', 'Is_Active' => 'TRUE'],
            ['Role_ID' => 'ROLE-STUDENT', 'Role_Name' => 'STUDENT', 'Is_Active' => 'TRUE'],
        ]);
        DB::table('MASTER_ROLE')->insert($roles->map(fn (array $row): array => array_intersect_key($row, array_flip(['Role_ID', 'Role_Name'])))->all());
        DB::table('MASTER_BATCH')->insert([
            ['Batch_ID' => 'BAT000002', 'Batch_Name' => 'ANGKATAN 4'],
            ['Batch_ID' => 'BAT000003', 'Batch_Name' => 'ANGKATAN 3'],
            ['Batch_ID' => 'BAT000004', 'Batch_Name' => 'ANGKATAN 2'],
            ['Batch_ID' => 'BAT000005', 'Batch_Name' => 'ANGKATAN 1'],
        ]);
        DB::table('MASTER_CLASS')->insert([
            ['Class_ID' => 'CLS000003', 'Batch_ID' => 'BAT000002'],
            ['Class_ID' => 'CLS000004', 'Batch_ID' => 'BAT000003'],
            ['Class_ID' => 'CLS000005', 'Batch_ID' => 'BAT000004'],
            ['Class_ID' => 'CLS000006', 'Batch_ID' => 'BAT000005'],
            ['Class_ID' => 'CLS000007', 'Batch_ID' => 'BAT000002'],
            ['Class_ID' => 'CLS000008', 'Batch_ID' => 'BAT000002'],
        ]);

        $roleRepository = Mockery::mock(RoleRepositoryInterface::class);
        $roleRepository->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn($roles);
        $roleRepository->shouldReceive('findById')->zeroOrMoreTimes()->andReturnUsing(fn (string $id) => $roles->firstWhere('Role_ID', $id));
        $students = Mockery::mock(StudentRepositoryInterface::class);
        $students->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect([
            ['User_ID' => 'USR-D', 'Batch_ID' => 'BAT000002', 'Class_ID' => 'CLS000003', 'Is_Active' => 1],
            ['User_ID' => 'USR-D2', 'Batch_ID' => 'BAT000002', 'Class_ID' => 'CLS000007', 'Is_Active' => 1],
            ['User_ID' => 'USR-D-OTHER', 'Batch_ID' => 'BAT000002', 'Class_ID' => 'CLS000008', 'Is_Active' => 1],
            ['User_ID' => 'USR-C', 'Batch_ID' => 'BAT000003', 'Class_ID' => 'CLS000004', 'Is_Active' => 1],
        ]));
        $batchRows = collect([
            ['Batch_ID' => 'BAT000002', 'Batch_Code' => 'A4', 'Batch_Name' => 'ANGKATAN 4', 'Program_ID' => 'PRG000002', 'Batch_Status' => 'Berlangsung', 'Is_Active' => 1],
            ['Batch_ID' => 'BAT000003', 'Batch_Code' => 'A3', 'Batch_Name' => 'ANGKATAN 3', 'Program_ID' => 'PRG000002', 'Batch_Status' => 'Berlangsung', 'Is_Active' => 1],
            ['Batch_ID' => 'BAT000004', 'Batch_Code' => 'A2', 'Batch_Name' => 'ANGKATAN 2', 'Program_ID' => 'PRG000002', 'Batch_Status' => 'Berlangsung', 'Is_Active' => 1],
            ['Batch_ID' => 'BAT000005', 'Batch_Code' => 'A1', 'Batch_Name' => 'ANGKATAN 1', 'Program_ID' => 'PRG000002', 'Batch_Status' => 'Berlangsung', 'Is_Active' => 1],
        ]);
        $batches = Mockery::mock(BatchRepositoryInterface::class);
        $batches->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn($batchRows);
        $batches->shouldReceive('findById')->zeroOrMoreTimes()->andReturnUsing(fn (string $id) => $batchRows->firstWhere('Batch_ID', $id));
        $classRows = collect([
            ['Class_ID' => 'CLS000003', 'Class_Code' => 'KLS-D', 'Class_Name' => 'KELAS D', 'Batch_ID' => 'BAT000002', 'Program_ID' => 'PRG000002', 'Class_Status' => 'Aktif', 'Is_Active' => 1],
            ['Class_ID' => 'CLS000004', 'Class_Code' => 'KLS-C', 'Class_Name' => 'KELAS C', 'Batch_ID' => 'BAT000003', 'Program_ID' => 'PRG000002', 'Class_Status' => 'Aktif', 'Is_Active' => 1],
            ['Class_ID' => 'CLS000005', 'Class_Code' => 'KLS-B', 'Class_Name' => 'KELAS B', 'Batch_ID' => 'BAT000004', 'Program_ID' => 'PRG000002', 'Class_Status' => 'Aktif', 'Is_Active' => 1],
            ['Class_ID' => 'CLS000006', 'Class_Code' => 'KLS-A', 'Class_Name' => 'KELAS A', 'Batch_ID' => 'BAT000005', 'Program_ID' => 'PRG000002', 'Class_Status' => 'Aktif', 'Is_Active' => 1],
            ['Class_ID' => 'CLS000007', 'Class_Code' => 'KLS-D2', 'Class_Name' => 'KELAS D2', 'Batch_ID' => 'BAT000002', 'Program_ID' => 'PRG000002', 'Class_Status' => 'Aktif', 'Is_Active' => 1],
            ['Class_ID' => 'CLS000008', 'Class_Code' => 'KLS-D3', 'Class_Name' => 'KELAS D3', 'Batch_ID' => 'BAT000002', 'Program_ID' => 'PRG000002', 'Class_Status' => 'Aktif', 'Is_Active' => 1],
        ]);
        $classes = Mockery::mock(ClassRepositoryInterface::class);
        $classes->shouldReceive('findById')->zeroOrMoreTimes()->andReturnUsing(fn (string $id) => $classRows->firstWhere('Class_ID', $id));
        $classes->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn($classRows);

        $announcementRepository = new HotfixAnnouncementRepository;
        $classService = Mockery::mock(ClassService::class);
        $classService->shouldReceive('getAllClasses')->zeroOrMoreTimes()->andReturn($classRows);
        $this->app->instance(AnnouncementRepositoryInterface::class, $announcementRepository);
        $this->app->instance(RoleRepositoryInterface::class, $roleRepository);
        $this->app->instance(StudentRepositoryInterface::class, $students);
        $this->app->instance(BatchRepositoryInterface::class, $batches);
        $this->app->instance(ClassRepositoryInterface::class, $classes);
        $this->app->instance(ClassService::class, $classService);
        $this->announcements = $this->app->make(AnnouncementService::class);
        $this->visibility = $this->app->make(AnnouncementVisibilityService::class);
        $this->actingAs($this->user('USR-ADMIN', 'ROLE-ADMINISTRATOR'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_persists_normalized_relations_and_visibility_without_duplicates(): void
    {
        $this->announcements->create($this->payload(['ROLE-HR', 'ROLE-TEACHER', 'ROLE-STUDENT']));

        $this->assertSame(3, DB::table('ANNOUNCEMENT_AUDIENCE_ROLE')->where('Announcement_ID', 'ANC000001')->count());
        $this->assertSame(['CLS000003'], DB::table('ANNOUNCEMENT_AUDIENCE_CLASS')->where('Announcement_ID', 'ANC000001')->orderBy('Class_ID')->pluck('Class_ID')->all());
        $this->assertDatabaseHas('MASTER_ANNOUNCEMENT', [
            'Announcement_ID' => 'ANC000001', 'Audience_Type' => 'SELECTED_ROLES',
            'Target_Role' => 'HR', 'Student_Scope' => 'BATCH_SELECTED_CLASSES',
        ]);

        $this->assertCount(1, $this->announcements->getActiveForUser($this->user('USR-HR', 'ROLE-HR')));
        $this->assertCount(1, $this->announcements->getActiveForUser($this->user('USR-D', 'ROLE-STUDENT')));
        $this->assertCount(0, $this->announcements->getActiveForUser($this->user('USR-D-OTHER', 'ROLE-STUDENT')));
        $this->assertCount(0, $this->announcements->getActiveForUser($this->user('USR-C', 'ROLE-STUDENT')));
        $this->assertCount(0, $this->announcements->getActiveForUser($this->user('USR-HR', 'ROLE-FINANCE')));
        $this->assertNotNull($this->announcements->getVisibleForUser('ANC000001', $this->user('USR-HR', 'ROLE-HR')));
        $this->assertNull($this->announcements->getVisibleForUser('ANC000001', $this->user('USR-HR', 'ROLE-FINANCE')));
        $this->assertNull($this->announcements->getVisibleForUser('ANC000001', $this->user('USR-D-OTHER', 'ROLE-STUDENT')));
    }

    public function test_actual_create_page_and_post_use_verified_production_batch_class_contract(): void
    {
        $this->assertFalse($this->app->bound(AnnouncementVisibilityService::class));
        $resolvedService = $this->app->make(AnnouncementService::class);
        $this->assertInstanceOf(AnnouncementService::class, $resolvedService);
        $this->assertTrue($this->app->resolved(AnnouncementVisibilityService::class));

        $validated = $resolvedService->validatePayload($this->payload(['ROLE-TEACHER', 'ROLE-STUDENT']));
        $this->assertSame(AnnouncementVisibilityService::SELECTED_ROLES, $validated['canonical']['Audience_Type']);
        $this->assertEqualsCanonicalizing(
            ['ROLE-TEACHER', 'ROLE-STUDENT'],
            $validated['canonical']['Audience_Role_IDs'],
        );
        $this->assertSame(['CLS000003'], $validated['canonical']['Audience_Class_IDs']);

        $response = $this->get(route('announcements.create'))->assertOk();
        $html = $response->getContent();
        foreach ([
            ['BAT000002', 'CLS000003', 'KELAS D'],
            ['BAT000003', 'CLS000004', 'KELAS C'],
            ['BAT000004', 'CLS000005', 'KELAS B'],
            ['BAT000005', 'CLS000006', 'KELAS A'],
        ] as [$batchId, $classId, $className]) {
            $this->assertStringContainsString($batchId, $html);
            $this->assertStringContainsString($classId, $html);
            $this->assertStringContainsString($className, $html);
        }

        $view = $this->app->make(AnnouncementController::class)->create();
        $map = $view->getData()['classesByBatch'];
        $this->assertSame(['CLS000003', 'CLS000007', 'CLS000008'], array_column($map['BAT000002'], 'id'));
        $this->assertSame(['CLS000004'], array_column($map['BAT000003'], 'id'));
        $this->assertSame(['CLS000005'], array_column($map['BAT000004'], 'id'));
        $this->assertSame(['CLS000006'], array_column($map['BAT000005'], 'id'));

        $this->post(route('announcements.store'), $this->payload(['ROLE-TEACHER', 'ROLE-STUDENT']))
            ->assertRedirect(route('announcements.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');
        $this->assertDatabaseHas('MASTER_ANNOUNCEMENT', [
            'Announcement_ID' => 'ANC000001',
            'Audience_Type' => AnnouncementVisibilityService::SELECTED_ROLES,
            'Student_Scope' => AnnouncementVisibilityService::BATCH_SELECTED_CLASSES,
            'Target_Batch_ID' => 'BAT000002',
            'Target_Class_ID' => 'CLS000003',
        ]);
        $this->assertDatabaseHas('ANNOUNCEMENT_AUDIENCE_ROLE', ['Announcement_ID' => 'ANC000001', 'Role_ID' => 'ROLE-TEACHER']);
        $this->assertDatabaseHas('ANNOUNCEMENT_AUDIENCE_ROLE', ['Announcement_ID' => 'ANC000001', 'Role_ID' => 'ROLE-STUDENT']);
        $this->assertDatabaseHas('ANNOUNCEMENT_AUDIENCE_CLASS', [
            'Announcement_ID' => 'ANC000001', 'Batch_ID' => 'BAT000002', 'Class_ID' => 'CLS000003',
        ]);
    }

    public function test_actual_post_supports_multiple_classes_and_isolates_other_class(): void
    {
        $payload = $this->payload(['ROLE-HR', 'ROLE-TEACHER', 'ROLE-STUDENT']);
        $payload['Target_Class_IDs'] = ['CLS000003', 'CLS000007'];

        $this->post(route('announcements.store'), $payload)
            ->assertRedirect(route('announcements.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(['CLS000003', 'CLS000007'], DB::table('ANNOUNCEMENT_AUDIENCE_CLASS')
            ->where('Announcement_ID', 'ANC000001')->orderBy('Class_ID')->pluck('Class_ID')->all());
        $this->assertCount(1, $this->announcements->getActiveForUser($this->user('USR-D', 'ROLE-STUDENT')));
        $this->assertCount(1, $this->announcements->getActiveForUser($this->user('USR-D2', 'ROLE-STUDENT')));
        $this->assertCount(0, $this->announcements->getActiveForUser($this->user('USR-D-OTHER', 'ROLE-STUDENT')));
    }

    public function test_edit_hydrates_relations_and_clears_student_then_all_role_state(): void
    {
        $this->announcements->create($this->payload(['ROLE-TEACHER', 'ROLE-STUDENT']));
        $state = $this->visibility->formState($this->announcements->getById('ANC000001'));
        $this->assertEqualsCanonicalizing(['ROLE-TEACHER', 'ROLE-STUDENT'], $state['roles']);
        $this->assertSame(['CLS000003'], $state['classes']);

        $this->get(route('announcements.edit', 'ANC000001'))
            ->assertOk()
            ->assertSee('CLS000003')
            ->assertSee('BAT000002');

        $this->put(route('announcements.update', 'ANC000001'), $this->payload(['ROLE-HR', 'ROLE-FINANCE'], null, null, []))
            ->assertRedirect(route('announcements.index'))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, DB::table('ANNOUNCEMENT_AUDIENCE_ROLE')->where('Announcement_ID', 'ANC000001')->count());
        $this->assertSame(0, DB::table('ANNOUNCEMENT_AUDIENCE_CLASS')->where('Announcement_ID', 'ANC000001')->count());
        $this->assertDatabaseHas('MASTER_ANNOUNCEMENT', [
            'Announcement_ID' => 'ANC000001', 'Student_Scope' => null,
            'Target_Batch_ID' => null, 'Target_Class_ID' => null,
        ]);

        $allRoles = $this->payload([], null, null, []);
        $allRoles['Audience_Type'] = 'ALL_ROLES';
        $this->announcements->update('ANC000001', $allRoles);
        $this->assertSame(0, DB::table('ANNOUNCEMENT_AUDIENCE_ROLE')->where('Announcement_ID', 'ANC000001')->count());
        $this->assertSame(0, DB::table('ANNOUNCEMENT_AUDIENCE_CLASS')->where('Announcement_ID', 'ANC000001')->count());
        $this->assertDatabaseHas('MASTER_ANNOUNCEMENT', [
            'Announcement_ID' => 'ANC000001', 'Audience_Type' => 'ALL_ROLES',
            'Target_Role' => null, 'Student_Scope' => null, 'Target_Batch_ID' => null,
        ]);
    }

    private function payload(array $roles, ?string $scope = 'BATCH_SELECTED_CLASSES', ?string $batch = 'BAT000002', array $classes = ['CLS000003']): array
    {
        return [
            'Title' => 'Hotfix Audience', 'Message' => 'Isi pengumuman.',
            'Audience_Type' => 'SELECTED_ROLES', 'Target_Roles' => $roles,
            'Student_Scope' => $scope, 'Target_Batch_ID' => $batch, 'Target_Class_IDs' => $classes,
            'Priority' => 'NORMAL', 'Status' => 'PUBLISHED',
            'Start_At' => '2026-10-06 09:00:00', 'Expires_At' => '2026-12-06 09:00:00',
        ];
    }

    private function user(string $id, string $roleId): GenericUser
    {
        return new GenericUser(['id' => $id, 'User_ID' => $id, 'Role_ID' => $roleId]);
    }
}

class HotfixAnnouncementRepository implements AnnouncementRepositoryInterface
{
    private array $rows = [];

    public function fetchAll()
    {
        return collect(array_values($this->rows));
    }

    public function findById(string $id)
    {
        return $this->rows[$id] ?? null;
    }

    public function findByIdFresh(string $id)
    {
        return $this->findById($id);
    }

    public function generateNewId(string $prefix, int $padding = 6): string
    {
        return $prefix.str_pad((string) (count($this->rows) + 1), $padding, '0', STR_PAD_LEFT);
    }

    public function create(array $data)
    {
        $this->rows[$data['Announcement_ID']] = $data;
        DB::table('MASTER_ANNOUNCEMENT')->insert($this->databaseAudience($data));

        return true;
    }

    public function update(string $id, array $data)
    {
        $this->rows[$id] = array_merge($this->rows[$id], $data);
        DB::table('MASTER_ANNOUNCEMENT')->where('Announcement_ID', $id)->update($this->databaseAudience($this->rows[$id], false));

        return true;
    }

    public function softDelete(string $id)
    {
        return $this->update($id, ['Is_Active' => 'FALSE']);
    }

    public function clearCache(): void {}

    private function databaseAudience(array $row, bool $includeId = true): array
    {
        $data = array_intersect_key($row, array_flip([
            'Announcement_ID', 'Audience_Type', 'Target_Role', 'Target_Batch_ID', 'Target_Class_ID', 'Student_Scope',
        ]));
        if (! $includeId) {
            unset($data['Announcement_ID']);
        }

        return $data;
    }
}
