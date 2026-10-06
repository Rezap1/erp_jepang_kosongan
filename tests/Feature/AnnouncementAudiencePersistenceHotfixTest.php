<?php

namespace Tests\Feature;

use App\Interfaces\GoogleSheets\AnnouncementRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\RoleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Academic\AnnouncementService;
use App\Services\Academic\AnnouncementVisibilityService;
use App\Services\Core\RoleService;
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
            ['Role_ID' => 'ROLE-HR', 'Role_Name' => 'HR', 'Is_Active' => 'TRUE'],
            ['Role_ID' => 'ROLE-FINANCE', 'Role_Name' => 'FINANCE', 'Is_Active' => 'TRUE'],
            ['Role_ID' => 'ROLE-TEACHER', 'Role_Name' => 'TEACHER', 'Is_Active' => 'TRUE'],
            ['Role_ID' => 'ROLE-STUDENT', 'Role_Name' => 'STUDENT', 'Is_Active' => 'TRUE'],
        ]);
        DB::table('MASTER_ROLE')->insert($roles->map(fn (array $row): array => array_intersect_key($row, array_flip(['Role_ID', 'Role_Name'])))->all());
        DB::table('MASTER_BATCH')->insert([
            ['Batch_ID' => 'BAT-A', 'Batch_Name' => 'Batch A'],
            ['Batch_ID' => 'BAT-B', 'Batch_Name' => 'Batch B'],
        ]);
        DB::table('MASTER_CLASS')->insert([
            ['Class_ID' => 'CLS-A1', 'Batch_ID' => 'BAT-A'],
            ['Class_ID' => 'CLS-A2', 'Batch_ID' => 'BAT-A'],
            ['Class_ID' => 'CLS-A3', 'Batch_ID' => 'BAT-A'],
            ['Class_ID' => 'CLS-B1', 'Batch_ID' => 'BAT-B'],
        ]);

        $roleRepository = Mockery::mock(RoleRepositoryInterface::class);
        $roleRepository->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn($roles);
        $roleRepository->shouldReceive('findById')->zeroOrMoreTimes()->andReturnUsing(fn (string $id) => $roles->firstWhere('Role_ID', $id));
        $students = Mockery::mock(StudentRepositoryInterface::class);
        $students->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect([
            ['User_ID' => 'USR-A1', 'Batch_ID' => 'BAT-A', 'Class_ID' => 'CLS-A1', 'Is_Active' => 'TRUE'],
            ['User_ID' => 'USR-A3', 'Batch_ID' => 'BAT-A', 'Class_ID' => 'CLS-A3', 'Is_Active' => 'TRUE'],
            ['User_ID' => 'USR-B1', 'Batch_ID' => 'BAT-B', 'Class_ID' => 'CLS-B1', 'Is_Active' => 'TRUE'],
        ]));
        $batches = Mockery::mock(BatchRepositoryInterface::class);
        $batches->shouldReceive('findById')->zeroOrMoreTimes()->andReturnUsing(fn (string $id) => in_array($id, ['BAT-A', 'BAT-B'], true) ? ['Batch_ID' => $id, 'Batch_Name' => $id, 'Is_Active' => 'TRUE'] : null);
        $classRows = collect([
            ['Class_ID' => 'CLS-A1', 'Batch_ID' => 'BAT-A', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-A2', 'Batch_ID' => 'BAT-A', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-A3', 'Batch_ID' => 'BAT-A', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-B1', 'Batch_ID' => 'BAT-B', 'Is_Active' => 'TRUE'],
        ]);
        $classes = Mockery::mock(ClassRepositoryInterface::class);
        $classes->shouldReceive('findById')->zeroOrMoreTimes()->andReturnUsing(fn (string $id) => $classRows->firstWhere('Class_ID', $id));
        $classes->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn($classRows);

        $this->visibility = new AnnouncementVisibilityService(new RoleService($roleRepository), $students, $batches, $classes);
        $this->announcements = new AnnouncementService(new HotfixAnnouncementRepository, $classes, $this->visibility);
        $this->actingAs($this->user('USR-HR', 'ROLE-HR'));
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
        $this->assertSame(['CLS-A1', 'CLS-A2'], DB::table('ANNOUNCEMENT_AUDIENCE_CLASS')->where('Announcement_ID', 'ANC000001')->orderBy('Class_ID')->pluck('Class_ID')->all());
        $this->assertDatabaseHas('MASTER_ANNOUNCEMENT', [
            'Announcement_ID' => 'ANC000001', 'Audience_Type' => 'SELECTED_ROLES',
            'Target_Role' => 'HR', 'Student_Scope' => 'BATCH_SELECTED_CLASSES',
        ]);

        $this->assertCount(1, $this->announcements->getActiveForUser($this->user('USR-HR', 'ROLE-HR')));
        $this->assertCount(1, $this->announcements->getActiveForUser($this->user('USR-A1', 'ROLE-STUDENT')));
        $this->assertCount(0, $this->announcements->getActiveForUser($this->user('USR-A3', 'ROLE-STUDENT')));
        $this->assertCount(0, $this->announcements->getActiveForUser($this->user('USR-B1', 'ROLE-STUDENT')));
        $this->assertCount(0, $this->announcements->getActiveForUser($this->user('USR-HR', 'ROLE-FINANCE')));
        $this->assertNotNull($this->announcements->getVisibleForUser('ANC000001', $this->user('USR-HR', 'ROLE-HR')));
        $this->assertNull($this->announcements->getVisibleForUser('ANC000001', $this->user('USR-HR', 'ROLE-FINANCE')));
        $this->assertNull($this->announcements->getVisibleForUser('ANC000001', $this->user('USR-A3', 'ROLE-STUDENT')));
    }

    public function test_edit_hydrates_relations_and_clears_student_then_all_role_state(): void
    {
        $this->announcements->create($this->payload(['ROLE-TEACHER', 'ROLE-STUDENT']));
        $state = $this->visibility->formState($this->announcements->getById('ANC000001'));
        $this->assertEqualsCanonicalizing(['ROLE-TEACHER', 'ROLE-STUDENT'], $state['roles']);
        $this->assertSame(['CLS-A1', 'CLS-A2'], $state['classes']);

        $this->announcements->update('ANC000001', $this->payload(['ROLE-HR', 'ROLE-FINANCE'], null, null, []));
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

    private function payload(array $roles, ?string $scope = 'BATCH_SELECTED_CLASSES', ?string $batch = 'BAT-A', array $classes = ['CLS-A1', 'CLS-A2']): array
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
