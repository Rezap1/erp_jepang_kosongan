<?php

namespace Tests\Feature;

use App\Interfaces\GoogleSheets\AnnouncementRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\RoleRepositoryInterface;
use App\Services\Academic\AnnouncementService;
use App\Services\Core\ClassService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\GenericUser;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class H871TeacherAnnouncementCreationTest extends TestCase
{
    private H871AnnouncementRepository $announcements;

    private H871ClassRepository $classes;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-21 10:00:00', 'Asia/Jakarta'));

        $this->announcements = new H871AnnouncementRepository;
        $this->classes = new H871ClassRepository([
            ['Class_ID' => 'CLS-A', 'Class_Code' => 'A', 'Class_Name' => 'Kelas A', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'CLS-B', 'Class_Code' => 'B', 'Class_Name' => 'Kelas B', 'Is_Active' => 'TRUE'],
        ]);
        $this->app->instance(AnnouncementRepositoryInterface::class, $this->announcements);
        $this->app->instance(ClassRepositoryInterface::class, $this->classes);
        $service = $this->app->make(AnnouncementService::class);
        $this->app->instance(AnnouncementService::class, $service);

        $classService = Mockery::mock(ClassService::class);
        $classService->shouldReceive('getAllClasses')->zeroOrMoreTimes()->andReturn(collect($this->classes->rows));
        $this->app->instance(ClassService::class, $classService);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_master_existing_all_students_and_class_creation_remain_supported(): void
    {
        $this->actingAsRole('MASTER', 'USR-MASTER');

        $this->post(route('announcements.store'), $this->payload('Master Semua', 'ALL_STUDENTS'))
            ->assertRedirect(route('announcements.index'));
        $this->post(route('announcements.store'), $this->payload('Master Kelas', 'CLASS', 'CLS-A'))
            ->assertRedirect(route('announcements.index'));

        $this->assertSame('USR-MASTER', $this->announcements->rows[0]['Created_By']);
        $this->assertSame('ALL_STUDENTS', $this->announcements->rows[0]['Audience_Type']);
        $this->assertSame('CLASS', $this->announcements->rows[1]['Audience_Type']);
        $this->assertSame('CLS-A', $this->announcements->rows[1]['Audience_ID']);
        $service = $this->app->make(AnnouncementService::class);
        $this->assertCount(2, $service->getActiveAnnouncements('STUDENT', 'CLS-A'));
        $this->assertCount(1, $service->getActiveAnnouncements('STUDENT', 'CLS-B'));
    }

    public function test_production_style_teacher_role_resolution_can_open_index_and_create(): void
    {
        $this->actingAsRole('TEACHER', 'USR-TEACHER-PRODUCTION');

        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Pusat Pengumuman');
        $this->get(route('announcements.create'))
            ->assertOk()
            ->assertSee('Buat Pengumuman Baru');
    }

    public function test_teacher_can_create_all_students_and_forged_creator_is_ignored(): void
    {
        $this->actingAsRole('TEACHER', 'USR-TEACHER-A');
        $payload = $this->payload('Libur Kegiatan Belajar', 'ALL_STUDENTS') + [
            'Created_By' => 'USR-MASTER',
            'Updated_By' => 'USR-MASTER',
        ];

        $this->post(route('announcements.store'), $payload)
            ->assertRedirect(route('announcements.index'));

        $row = $this->announcements->rows[0];
        $this->assertSame('USR-TEACHER-A', $row['Created_By']);
        $this->assertSame('USR-TEACHER-A', $row['Updated_By']);
        $this->assertSame('ALL_STUDENTS', $row['Audience_Type']);
        $this->assertSame('', $row['Audience_ID']);
    }

    public function test_teacher_can_target_any_existing_master_class_and_student_visibility_is_isolated(): void
    {
        $this->actingAsRole('TEACHER', 'USR-TEACHER-A');
        $this->post(route('announcements.store'), $this->payload('Persiapan Ujian Bahasa Jepang', 'CLASS', 'CLS-A'))
            ->assertRedirect(route('announcements.index'));

        $service = $this->app->make(AnnouncementService::class);
        $this->assertCount(1, $service->getActiveAnnouncements('STUDENT', 'CLS-A'));
        $this->assertCount(0, $service->getActiveAnnouncements('STUDENT', 'CLS-B'));
        $this->assertSame('CLS-A', $this->announcements->rows[0]['Audience_ID']);
    }

    public function test_scheduled_teacher_announcement_uses_shared_active_filter(): void
    {
        $this->actingAsRole('TEACHER', 'USR-TEACHER-A');
        $this->post(route('announcements.store'), $this->payload('Pengumuman Mendatang', 'CLASS', 'CLS-A', [
            'Start_At' => '2026-09-22 08:00:00',
            'Expires_At' => '2026-09-23 08:00:00',
        ]))->assertRedirect(route('announcements.index'));

        $service = $this->app->make(AnnouncementService::class);
        $this->assertCount(0, $service->getActiveAnnouncements('STUDENT', 'CLS-A', CarbonImmutable::parse('2026-09-22 07:59:59', 'Asia/Jakarta')));
        $this->assertCount(1, $service->getActiveAnnouncements('STUDENT', 'CLS-A', CarbonImmutable::parse('2026-09-22 08:00:00', 'Asia/Jakarta')));
        $this->assertCount(0, $service->getActiveAnnouncements('STUDENT', 'CLS-B', CarbonImmutable::parse('2026-09-22 08:00:00', 'Asia/Jakarta')));
    }

    public function test_teacher_can_edit_and_delete_only_own_announcement_with_audit_trail(): void
    {
        $this->announcements->rows[] = $this->row('ANC-OWN', 'USR-TEACHER-A');
        $this->actingAsRole('TEACHER', 'USR-TEACHER-A');

        $this->get(route('announcements.edit', 'ANC-OWN'))->assertOk()->assertSee('Edit Pengumuman');
        $this->put(route('announcements.update', 'ANC-OWN'), $this->payload('Judul Diperbarui', 'ALL_STUDENTS', null, ['Status' => 'PUBLISHED']))
            ->assertRedirect(route('announcements.index'));
        $this->assertSame('Judul Diperbarui', $this->announcements->rows[0]['Title']);
        $this->assertSame('USR-TEACHER-A', $this->announcements->rows[0]['Created_By']);
        $this->assertSame('USR-TEACHER-A', $this->announcements->rows[0]['Updated_By']);

        $this->delete(route('announcements.destroy', 'ANC-OWN'))->assertRedirect(route('announcements.index'));
        $this->assertSame('FALSE', $this->announcements->rows[0]['Is_Active']);
        $this->assertSame('INACTIVE', $this->announcements->rows[0]['Status']);
    }

    public function test_teacher_cannot_view_edit_update_or_delete_another_teacher_announcement(): void
    {
        $this->announcements->rows[] = $this->row('ANC-OTHER', 'USR-TEACHER-B');
        $this->actingAsRole('TEACHER', 'USR-TEACHER-A');

        $this->get(route('announcements.show', 'ANC-OTHER'))->assertForbidden();
        $this->get(route('announcements.edit', 'ANC-OTHER'))->assertForbidden();
        $this->put(route('announcements.update', 'ANC-OTHER'), $this->payload('Forged Update'))->assertForbidden();
        $this->delete(route('announcements.destroy', 'ANC-OTHER'))->assertForbidden();
        $this->post(route('announcements.deactivate', 'ANC-OTHER'))->assertForbidden();
        $this->assertSame('Pengumuman ANC-OTHER', $this->announcements->rows[0]['Title']);
        $this->assertSame('TRUE', $this->announcements->rows[0]['Is_Active']);
    }

    public function test_teacher_cannot_modify_master_announcement(): void
    {
        $this->announcements->rows[] = $this->row('ANC-MASTER', 'USR-MASTER');
        $this->actingAsRole('TEACHER', 'USR-TEACHER-A');

        $this->get(route('announcements.edit', 'ANC-MASTER'))->assertForbidden();
        $this->put(route('announcements.update', 'ANC-MASTER'), $this->payload('Forged Master Update'))->assertForbidden();
        $this->delete(route('announcements.destroy', 'ANC-MASTER'))->assertForbidden();
        $this->assertSame('Pengumuman ANC-MASTER', $this->announcements->rows[0]['Title']);
    }

    public function test_teacher_management_list_contains_only_own_announcements(): void
    {
        $this->announcements->rows = [
            $this->row('ANC-OWN', 'USR-TEACHER-A', 'Milik Guru A'),
            $this->row('ANC-OTHER', 'USR-TEACHER-B', 'Milik Guru B'),
            $this->row('ANC-MASTER', 'USR-MASTER', 'Milik Master'),
        ];
        $this->actingAsRole('TEACHER', 'USR-TEACHER-A');

        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Milik Guru A')
            ->assertDontSee('Milik Guru B')
            ->assertDontSee('Milik Master')
            ->assertSee('Buat Pengumuman Baru');
    }

    public function test_invalid_class_and_missing_audience_are_rejected_without_write(): void
    {
        $this->actingAsRole('TEACHER', 'USR-TEACHER-A');

        $this->post(route('announcements.store'), $this->payload('Kelas Palsu', 'CLASS', 'CLS-FORGED'))
            ->assertSessionHasErrors('Audience_ID');
        $payload = $this->payload('Tanpa Target');
        unset($payload['Audience_Type']);
        $this->post(route('announcements.store'), $payload)
            ->assertSessionHasErrors('Audience_Type');
        $this->assertCount(0, $this->announcements->rows);
    }

    #[DataProvider('deniedCreatorRoles')]
    public function test_students_and_unrelated_roles_cannot_create(string $role): void
    {
        $this->actingAsRole($role, 'USR-'.$role);
        $this->post(route('announcements.store'), $this->payload('Tidak Diizinkan'))->assertForbidden();
        $this->assertCount(0, $this->announcements->rows);
    }

    public static function deniedCreatorRoles(): array
    {
        return [['STUDENT'], ['FINANCE'], ['HR'], ['MARKETING'], ['DIRECTOR']];
    }

    public function test_administrator_retains_global_management_authority(): void
    {
        $this->announcements->rows[] = $this->row('ANC-TEACHER', 'USR-TEACHER-A');
        $this->actingAsRole('ADMINISTRATOR', 'USR-ADMIN');

        $this->put(route('announcements.update', 'ANC-TEACHER'), $this->payload('Admin Update'))->assertRedirect(route('announcements.index'));
        $this->assertSame('Admin Update', $this->announcements->rows[0]['Title']);
        $this->assertSame('USR-TEACHER-A', $this->announcements->rows[0]['Created_By']);
        $this->assertSame('USR-ADMIN', $this->announcements->rows[0]['Updated_By']);
    }

    public function test_teacher_navigation_and_announcement_views_are_mobile_and_desktop_usable(): void
    {
        $this->actingAsRole('TEACHER', 'USR-TEACHER-A');
        $bottom = view('components.mobile-bottom-nav', ['userRole' => 'TEACHER'])->render();
        $drawer = view('components.dashboard.sidebar', ['userRole' => 'TEACHER'])->render();
        $create = $this->get(route('announcements.create'))->assertOk()->getContent();

        $this->assertStringContainsString(route('announcements.index'), $bottom);
        $this->assertStringContainsString(route('announcements.index'), $drawer);
        $this->assertStringContainsString('grid-cols-1 sm:grid-cols-2', $create);
        $this->assertStringContainsString('px-6 md:px-12', $create);
    }

    public function test_student_dashboard_keeps_shared_three_announcement_limit_and_single_section(): void
    {
        $service = file_get_contents(app_path('Services/Dashboard/StudentDashboardService.php'));
        $view = file_get_contents(resource_path('views/dashboard/student.blade.php'));

        $this->assertSame(1, substr_count($service, "getActiveAnnouncements('STUDENT', \$studentClassId)->take(3)"));
        $this->assertSame(1, substr_count($view, 'id="student-announcements-heading"'));
        $this->assertStringNotContainsString('Pengumuman Guru', $view);
        $this->assertStringNotContainsString('Pengumuman Master', $view);
    }

    private function actingAsRole(string $role, string $userId): void
    {
        $roleId = $role === 'MASTER' ? 'MASTER' : 'ROLE-'.$role;
        $this->app->instance(RoleRepositoryInterface::class, new H871RoleRepository([
            ['Role_ID' => $roleId, 'Role_Name' => $role, 'Is_Active' => 'TRUE'],
        ]));
        $this->actingAs(new GenericUser([
            'id' => $userId, 'User_ID' => $userId, 'Role_ID' => $roleId, 'Username' => $userId,
        ]));
    }

    private function payload(string $title, string $audience = 'ALL_STUDENTS', ?string $classId = null, array $overrides = []): array
    {
        return array_merge([
            'Title' => $title,
            'Message' => 'Isi pengumuman untuk pengujian.',
            'Audience_Type' => $audience,
            'Audience_ID' => $classId,
            'Priority' => 'NORMAL',
            'Status' => 'PUBLISHED',
            'Start_At' => '2026-09-21 10:00:00',
            'Expires_At' => '2026-10-21 10:00:00',
        ], $overrides);
    }

    private function row(string $id, string $creator, ?string $title = null): array
    {
        return [
            'Announcement_ID' => $id,
            'Title' => $title ?? 'Pengumuman '.$id,
            'Message' => 'Isi',
            'Content' => 'Isi',
            'Priority' => 'NORMAL',
            'Audience_Type' => 'ALL_STUDENTS',
            'Audience_ID' => '',
            'Target_Role' => 'ALL_STUDENTS',
            'Target_ID' => '',
            'Status' => 'PUBLISHED',
            'Is_Active' => 'TRUE',
            'Start_At' => '2026-09-21 09:00:00',
            'Expires_At' => '2026-10-21 10:00:00',
            'Created_At' => '2026-09-21 09:00:00',
            'Created_By' => $creator,
            'Updated_At' => '2026-09-21 09:00:00',
            'Updated_By' => $creator,
        ];
    }
}

class H871AnnouncementRepository implements AnnouncementRepositoryInterface
{
    public array $rows = [];

    public function fetchAll()
    {
        return collect($this->rows);
    }

    public function findById(string $id)
    {
        return collect($this->rows)->firstWhere('Announcement_ID', $id);
    }

    public function generateNewId(string $prefix, int $padding = 6): string
    {
        return $prefix.str_pad((string) (count($this->rows) + 1), $padding, '0', STR_PAD_LEFT);
    }

    public function create(array $data)
    {
        $this->rows[] = $data;

        return $data;
    }

    public function update(string $id, array $data)
    {
        foreach ($this->rows as &$row) {
            if ($row['Announcement_ID'] === $id) {
                $row = array_merge($row, $data);

                return true;
            }
        }

        return false;
    }

    public function softDelete(string $id)
    {
        return $this->update($id, ['Is_Active' => 'FALSE', 'Status' => 'INACTIVE']);
    }
}

class H871ClassRepository implements ClassRepositoryInterface
{
    public function __construct(public array $rows) {}

    public function fetchAll()
    {
        return collect($this->rows);
    }

    public function findById(string $id)
    {
        return collect($this->rows)->firstWhere('Class_ID', $id);
    }

    public function findByCode(string $code)
    {
        return collect($this->rows)->firstWhere('Class_Code', $code);
    }

    public function generateNewId(string $prefix, int $padding = 6): string
    {
        return $prefix.str_pad('1', $padding, '0', STR_PAD_LEFT);
    }

    public function create(array $data)
    {
        return $data;
    }

    public function update(string $id, array $data)
    {
        return true;
    }

    public function softDelete(string $id)
    {
        return true;
    }

    public function clearCache() {}
}

class H871RoleRepository implements RoleRepositoryInterface
{
    public function __construct(public array $rows) {}

    public function fetchAll()
    {
        return collect($this->rows);
    }

    public function findById(string $id)
    {
        return collect($this->rows)->firstWhere('Role_ID', $id);
    }

    public function create(array $data)
    {
        return $data;
    }

    public function update(string $id, array $data)
    {
        return true;
    }
}
