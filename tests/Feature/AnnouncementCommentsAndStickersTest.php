<?php

namespace Tests\Feature;

use App\Interfaces\GoogleSheets\AnnouncementRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\RoleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Academic\AnnouncementCommentService;
use App\Services\Academic\AnnouncementService;
use App\Services\Academic\AnnouncementVisibilityService;
use App\Services\Academic\StickerLibraryService;
use App\Services\Core\AvatarResolver;
use App\Services\Core\ClassService;
use App\Services\Core\EmployeeService;
use App\Services\Core\GlobalSearchService;
use App\Services\Core\RoleService;
use App\Services\Dashboard\StudentDashboardService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Mockery;
use Tests\TestCase;

class AnnouncementCommentsAndStickersTest extends TestCase
{
    private AnnouncementCommentService $comments;

    private StickerLibraryService $stickers;

    private AnnouncementService $announcements;

    private GenericUser $student;

    private GenericUser $outsider;

    private GenericUser $admin;

    private GenericUser $secondStudent;

    private GenericUser $teacher;

    private GenericUser $otherTeacher;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['ANNOUNCEMENT_AUDIENCE_CLASS', 'ANNOUNCEMENT_AUDIENCE_ROLE', 'USER_STICKER_FAVORITE', 'ANNOUNCEMENT_COMMENT', 'MASTER_STICKER', 'MASTER_STICKER_PACK', 'MASTER_ANNOUNCEMENT', 'MASTER_CLASS', 'MASTER_BATCH', 'MASTER_USER', 'MASTER_ROLE'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('MASTER_ROLE', function (Blueprint $table): void {
            $table->string('Role_ID')->primary();
            $table->string('Role_Name');
        });
        Schema::create('MASTER_USER', function (Blueprint $table): void {
            $table->string('User_ID')->primary();
            $table->string('Role_ID');
            $table->string('Full_Name')->nullable();
            $table->string('Username')->nullable();
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
            $table->string('Target_Role')->nullable();
            $table->string('Target_ID')->nullable();
        });
        (require database_path('migrations/wms_mysql/2026_10_06_000001_add_announcement_v2.php'))->up();
        (require database_path('migrations/wms_mysql/2026_10_06_000002_add_announcement_multi_audience.php'))->up();
        DB::table('MASTER_ROLE')->insert([
            ['Role_ID' => 'ROLE-STUDENT', 'Role_Name' => 'STUDENT'], ['Role_ID' => 'ROLE-HR', 'Role_Name' => 'HR'], ['Role_ID' => 'ROLE-ADMINISTRATOR', 'Role_Name' => 'ADMINISTRATOR'], ['Role_ID' => 'ROLE-TEACHER', 'Role_Name' => 'TEACHER'],
        ]);
        DB::table('MASTER_USER')->insert([
            ['User_ID' => 'USR-STUDENT', 'Role_ID' => 'ROLE-STUDENT', 'Full_Name' => 'Siswa A', 'Username' => 'siswa'],
            ['User_ID' => 'USR-STUDENT-2', 'Role_ID' => 'ROLE-STUDENT', 'Full_Name' => 'Siswa B', 'Username' => 'siswa-b'],
            ['User_ID' => 'USR-HR', 'Role_ID' => 'ROLE-HR', 'Full_Name' => 'HR A', 'Username' => 'hr'],
            ['User_ID' => 'USR-ADMIN', 'Role_ID' => 'ROLE-ADMINISTRATOR', 'Full_Name' => 'Admin', 'Username' => 'admin'],
            ['User_ID' => 'USR-TEACHER', 'Role_ID' => 'ROLE-TEACHER', 'Full_Name' => 'Guru Pemilik', 'Username' => 'guru'],
            ['User_ID' => 'USR-TEACHER-2', 'Role_ID' => 'ROLE-TEACHER', 'Full_Name' => 'Guru Lain', 'Username' => 'guru-lain'],
        ]);
        DB::table('MASTER_ANNOUNCEMENT')->insert([
            ['Announcement_ID' => 'ANC-1', 'Audience_Type' => 'ROLE', 'Target_Role' => 'STUDENT'],
            ['Announcement_ID' => 'ANC-LEGACY', 'Audience_Type' => null, 'Target_Role' => 'STUDENT'],
            ['Announcement_ID' => 'ANC-INACTIVE', 'Audience_Type' => 'ROLE', 'Target_Role' => 'STUDENT'],
            ['Announcement_ID' => 'ANC-CREATOR', 'Audience_Type' => 'ROLE', 'Target_Role' => 'STUDENT'],
            ['Announcement_ID' => 'ANC-OTHER-CREATOR', 'Audience_Type' => 'ROLE', 'Target_Role' => 'STUDENT'],
        ]);
        DB::table('MASTER_STICKER_PACK')->insert(['Sticker_Pack_ID' => 'PACK-1', 'Name' => 'Reaksi', 'Is_Active' => true, 'Sort_Order' => 0]);
        DB::table('MASTER_STICKER')->insert([
            ['Sticker_ID' => 'STK-1', 'Sticker_Pack_ID' => 'PACK-1', 'Name' => 'Semangat', 'File_Path' => 'stickers/test.webp', 'Mime_Type' => 'image/webp', 'File_Size' => 100, 'Is_Active' => true, 'Sort_Order' => 0],
            ['Sticker_ID' => 'STK-OFF', 'Sticker_Pack_ID' => 'PACK-1', 'Name' => 'Lama', 'File_Path' => 'stickers/off.webp', 'Mime_Type' => 'image/webp', 'File_Size' => 100, 'Is_Active' => false, 'Sort_Order' => 1],
        ]);

        $roles = Mockery::mock(RoleRepositoryInterface::class);
        $roles->shouldReceive('fetchAll')->andReturn(collect([
            ['Role_ID' => 'ROLE-STUDENT', 'Role_Name' => 'STUDENT', 'Is_Active' => 'TRUE'], ['Role_ID' => 'ROLE-HR', 'Role_Name' => 'HR', 'Is_Active' => 'TRUE'], ['Role_ID' => 'ROLE-ADMINISTRATOR', 'Role_Name' => 'ADMINISTRATOR', 'Is_Active' => 'TRUE'], ['Role_ID' => 'ROLE-TEACHER', 'Role_Name' => 'TEACHER', 'Is_Active' => 'TRUE'],
        ]));
        $roles->shouldReceive('findById')->andReturnUsing(fn ($id) => ['Role_Name' => str_replace('ROLE-', '', $id), 'Is_Active' => 'TRUE']);
        $students = Mockery::mock(StudentRepositoryInterface::class);
        $students->shouldReceive('fetchAll')->andReturn(collect([
            ['Student_ID' => 'STD-1', 'User_ID' => 'USR-STUDENT', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'STD-2', 'User_ID' => 'USR-STUDENT-2', 'Is_Active' => 'TRUE'],
        ]));
        $batches = Mockery::mock(BatchRepositoryInterface::class);
        $batches->shouldReceive('findById')->andReturnNull();
        $classes = Mockery::mock(ClassRepositoryInterface::class);
        $classes->shouldReceive('findById')->andReturnNull();
        $roleService = new RoleService($roles);
        $this->app->instance(RoleService::class, $roleService);
        $visibility = new AnnouncementVisibilityService($roleService, $students, $batches, $classes);
        $this->app->instance(AnnouncementVisibilityService::class, $visibility);
        $repo = new class implements AnnouncementRepositoryInterface
        {
            public function fetchAll()
            {
                return collect([$this->findById('ANC-1'), $this->findById('ANC-LEGACY'), $this->findById('ANC-INACTIVE'), $this->findById('ANC-CREATOR'), $this->findById('ANC-OTHER-CREATOR')]);
            }

            public function findById(string $id)
            {
                return match ($id) {
                    'ANC-1' => ['Announcement_ID' => 'ANC-1', 'Audience_Type' => 'ROLE', 'Target_Role' => 'STUDENT', 'Title' => 'Tes', 'Content' => 'Isi', 'Is_Active' => 'TRUE', 'Status' => 'PUBLISHED', 'Publish_Date' => '2020-01-01', 'Expired_Date' => '2099-01-01'],
                    'ANC-LEGACY' => ['Announcement_ID' => 'ANC-LEGACY', 'Audience_Type' => null, 'Target_Role' => 'STUDENT', 'Target_ID' => null, 'Title' => 'Pengumuman Lama Admin', 'Content' => 'Isi lama', 'Is_Active' => 'TRUE', 'Publish_Date' => '2020-01-01', 'Expired_Date' => null, 'Created_By' => 'USR-ADMIN'],
                    'ANC-INACTIVE' => ['Announcement_ID' => 'ANC-INACTIVE', 'Audience_Type' => 'ROLE', 'Target_Role' => 'STUDENT', 'Title' => 'Nonaktif', 'Content' => 'Isi', 'Is_Active' => 'FALSE', 'Status' => 'INACTIVE', 'Publish_Date' => '2020-01-01', 'Expired_Date' => '2099-01-01'],
                    'ANC-CREATOR' => ['Announcement_ID' => 'ANC-CREATOR', 'Audience_Type' => 'ROLE', 'Target_Role' => 'STUDENT', 'Title' => 'Pengumuman Guru Pemilik', 'Content' => 'Isi untuk siswa', 'Category' => 'Akademik', 'Priority' => 'HIGH', 'Is_Active' => 'TRUE', 'Status' => 'PUBLISHED', 'Publish_Date' => '2020-01-01', 'Expired_Date' => '2099-01-01', 'Created_At' => '2026-10-10 08:00:00', 'Created_By' => 'USR-TEACHER'],
                    'ANC-OTHER-CREATOR' => ['Announcement_ID' => 'ANC-OTHER-CREATOR', 'Audience_Type' => 'ROLE', 'Target_Role' => 'STUDENT', 'Title' => 'Pengumuman Guru Lain', 'Content' => 'Rahasia pengelola lain', 'Is_Active' => 'TRUE', 'Status' => 'PUBLISHED', 'Publish_Date' => '2020-01-01', 'Expired_Date' => '2099-01-01', 'Created_By' => 'USR-TEACHER-2'],
                    default => null,
                };
            }

            public function generateNewId(string $prefix, int $padding = 6): string
            {
                return 'ANC-2';
            }

            public function create(array $data)
            {
                return true;
            }

            public function update(string $id, array $data)
            {
                return true;
            }

            public function softDelete(string $id)
            {
                return true;
            }
        };
        $this->announcements = new AnnouncementService($repo, $visibility);
        $employees = Mockery::mock(EmployeeRepositoryInterface::class);
        $employees->shouldReceive('fetchAll')->andReturn(collect([
            ['Employee_ID' => 'EMP-TEACHER', 'User_ID' => 'USR-TEACHER'],
            ['Employee_ID' => 'EMP-TEACHER-2', 'User_ID' => 'USR-TEACHER-2'],
        ]));
        $employeeService = Mockery::mock(EmployeeService::class);
        $employeeService->shouldReceive('getProfilePhotoPath')->andReturnNull();
        $avatars = new AvatarResolver($employees, $students, $employeeService);
        $this->comments = new AnnouncementCommentService($this->announcements, $visibility, $avatars);
        $this->stickers = new StickerLibraryService;
        $this->app->instance(AvatarResolver::class, $avatars);
        $this->app->instance(ClassService::class, Mockery::mock(ClassService::class));
        $this->app->instance(AnnouncementService::class, $this->announcements);
        $this->app->instance(AnnouncementCommentService::class, $this->comments);
        $this->app->instance(StickerLibraryService::class, $this->stickers);
        $this->student = new GenericUser(['id' => 'USR-STUDENT', 'User_ID' => 'USR-STUDENT', 'Role_ID' => 'ROLE-STUDENT']);
        $this->outsider = new GenericUser(['id' => 'USR-HR', 'User_ID' => 'USR-HR', 'Role_ID' => 'ROLE-HR']);
        $this->admin = new GenericUser(['id' => 'USR-ADMIN', 'User_ID' => 'USR-ADMIN', 'Role_ID' => 'ROLE-ADMINISTRATOR']);
        $this->secondStudent = new GenericUser(['id' => 'USR-STUDENT-2', 'User_ID' => 'USR-STUDENT-2', 'Role_ID' => 'ROLE-STUDENT']);
        $this->teacher = new GenericUser(['id' => 'USR-TEACHER', 'User_ID' => 'USR-TEACHER', 'Role_ID' => 'ROLE-TEACHER']);
        $this->otherTeacher = new GenericUser(['id' => 'USR-TEACHER-2', 'User_ID' => 'USR-TEACHER-2', 'Role_ID' => 'ROLE-TEACHER']);
    }

    public function test_audience_can_send_text_and_sticker_while_non_audience_is_denied(): void
    {
        $this->comments->createText('ANC-1', $this->student, 'Halo 👋');
        $this->comments->createSticker('ANC-1', $this->student, 'STK-1');
        $this->assertSame(2, $this->comments->counts(['ANC-1'])['ANC-1']);
        $this->assertSame(['STICKER', 'TEXT'], DB::table('ANNOUNCEMENT_COMMENT')->pluck('Comment_Type')->sort()->values()->all());

        $this->expectException(AuthorizationException::class);
        $this->comments->createText('ANC-1', $this->outsider, 'Tidak boleh');
    }

    public function test_pre_v2_admin_announcement_without_relation_rows_or_expiry_uses_historical_audience_for_comments(): void
    {
        $this->assertNotNull($this->announcements->getVisibleForUser('ANC-LEGACY', $this->student));
        $this->assertCount(1, $this->announcements->getActiveForUser($this->student)->where('Announcement_ID', 'ANC-LEGACY'));

        $this->actingAs($this->student)
            ->get(route('announcement-feed.show', 'ANC-LEGACY'))
            ->assertOk()
            ->assertSee('Pengumuman Lama Admin');
        $search = $this->app->make(GlobalSearchService::class)
            ->search('Pengumuman Lama Admin', 'STUDENT', 'USR-STUDENT');
        $this->assertSame('Pengumuman Lama Admin', $search['Pengumuman'][0]['title'] ?? null);
        $this->post(route('announcement-comments.store', 'ANC-LEGACY'), [
            'Comment_Type' => 'TEXT', 'Comment_Text' => 'Komentar pada pengumuman lama.',
        ])->assertRedirect();
        $this->post(route('announcement-comments.store', 'ANC-LEGACY'), [
            'Comment_Type' => 'STICKER', 'Sticker_ID' => 'STK-1',
        ])->assertRedirect();
        $this->assertSame(2, $this->comments->counts(['ANC-LEGACY'])['ANC-LEGACY']);
        $legacyTextId = (string) DB::table('ANNOUNCEMENT_COMMENT')
            ->where('Announcement_ID', 'ANC-LEGACY')->where('Comment_Type', 'TEXT')->value('Comment_ID');
        $this->delete(route('announcement-comments.destroy', ['ANC-LEGACY', $legacyTextId]))->assertRedirect();
        $this->assertSame(1, $this->comments->counts(['ANC-LEGACY'])['ANC-LEGACY']);

        $this->actingAs($this->outsider)
            ->get(route('announcement-feed.show', 'ANC-LEGACY'))
            ->assertForbidden();
        $this->post(route('announcement-comments.store', 'ANC-LEGACY'), [
            'Comment_Type' => 'TEXT', 'Comment_Text' => 'Tidak boleh.',
        ])->assertForbidden();
        $this->assertSame(1, DB::table('ANNOUNCEMENT_COMMENT')->where('Announcement_ID', 'ANC-LEGACY')->whereNull('Deleted_At')->count());
    }

    public function test_comment_integrity_delete_policy_and_soft_delete_count(): void
    {
        foreach (['   ', str_repeat('a', 2001)] as $invalid) {
            try {
                $this->comments->createText('ANC-1', $this->student, $invalid);
                $this->fail('Invalid text accepted');
            } catch (\InvalidArgumentException) {
            }
        }
        try {
            $this->comments->createSticker('ANC-1', $this->student, 'STK-OFF');
            $this->fail('Inactive sticker accepted');
        } catch (\InvalidArgumentException) {
        }
        try {
            $this->comments->createText('ANC-INACTIVE', $this->student, 'Tidak boleh');
            $this->fail('Inactive announcement accepted a comment');
        } catch (AuthorizationException) {
        }
        $id = $this->comments->createText('ANC-1', $this->student, '<script>alert(1)</script>');
        $this->actingAs($this->student);
        view()->share('errors', new ViewErrorBag);
        $rendered = view('announcements.feed.show', [
            'announcement' => ['Announcement_ID' => 'ANC-1', 'Title' => 'Tes', 'Creator_Name' => 'Admin', 'Start_Label' => '-', 'Expiry_Label' => '-', 'Audience_Label' => 'Student', 'Content' => 'Isi'],
            'comments' => $this->comments->paginate('ANC-1', $this->student), 'library' => $this->stickers->picker('USR-STUDENT'),
        ])->render();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $rendered);
        try {
            $this->comments->delete('ANC-1', $id, $this->outsider);
            $this->fail('Other user delete accepted');
        } catch (AuthorizationException) {
        }
        $this->comments->delete('ANC-1', $id, $this->admin);
        $this->assertSame([], $this->comments->counts(['ANC-1']));
    }

    public function test_comment_http_validation_owner_delete_and_exactly_once_insert(): void
    {
        $this->actingAs($this->student);
        $this->from(route('announcement-feed.show', 'ANC-1'))->post(route('announcement-comments.store', 'ANC-1'), [
            'Comment_Type' => 'TEXT', 'Comment_Text' => '   ',
        ])->assertRedirect(route('announcement-feed.show', 'ANC-1'))
            ->assertSessionHasErrors(['Comment_Text' => 'Komentar tidak boleh kosong.']);
        $this->get(route('announcement-feed.show', 'ANC-1'))
            ->assertOk()->assertSee('Komentar tidak boleh kosong.');
        $this->post(route('announcement-comments.store', 'ANC-1'), [
            'Comment_Type' => 'TEXT', 'Comment_Text' => str_repeat('a', 2001),
        ])->assertSessionHasErrors(['Comment_Text' => 'Komentar maksimal 2000 karakter.']);
        $this->post(route('announcement-comments.store', 'ANC-1'), [
            'Comment_Type' => 'STICKER', 'Sticker_ID' => '',
        ])->assertSessionHasErrors(['Sticker_ID' => 'Pilih stiker yang akan dikirim.']);
        $this->assertSame(0, DB::table('ANNOUNCEMENT_COMMENT')->count());

        $this->post(route('announcement-comments.store', 'ANC-1'), [
            'Comment_Type' => 'TEXT', 'Comment_Text' => 'Tersimpan tepat sekali.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('ANNOUNCEMENT_COMMENT')->count());
        $commentId = (string) DB::table('ANNOUNCEMENT_COMMENT')->value('Comment_ID');

        $this->delete(route('announcement-comments.destroy', ['ANC-1', $commentId]))->assertRedirect();
        $this->assertSame([], $this->comments->counts(['ANC-1']));
        $this->assertNotNull(DB::table('ANNOUNCEMENT_COMMENT')->where('Comment_ID', $commentId)->value('Deleted_At'));
    }

    public function test_comment_pagination_and_inactive_historical_sticker_remain_safe(): void
    {
        for ($index = 1; $index <= 26; $index++) {
            $this->comments->createText('ANC-1', $this->student, 'Komentar '.$index);
        }
        DB::table('ANNOUNCEMENT_COMMENT')->insert([
            'Comment_ID' => 'CMT-HISTORICAL-STICKER', 'Announcement_ID' => 'ANC-1', 'User_ID' => 'USR-STUDENT',
            'Comment_Type' => 'STICKER', 'Comment_Text' => null, 'Sticker_ID' => 'STK-OFF',
            'Created_At' => '2098-01-01 00:00:00', 'Updated_At' => '2098-01-01 00:00:00', 'Deleted_At' => null,
        ]);

        $firstPage = $this->comments->paginate('ANC-1', $this->student);
        $this->assertSame(27, $firstPage->total());
        $this->assertCount(25, $firstPage->items());
        request()->query->set('page', 2);
        $secondPage = $this->comments->paginate('ANC-1', $this->student);
        $this->assertCount(2, $secondPage->items());
        $this->assertTrue(collect($secondPage->items())->contains(fn ($row): bool => $row->Comment_ID === 'CMT-HISTORICAL-STICKER' && str_contains((string) $row->Sticker_URL, 'stickers/off.webp')));
    }

    public function test_comment_avatar_uses_each_commenters_canonical_profile_and_safe_fallback(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profiles/student_std-2_avatar.WEBP', 'student-b');
        $this->comments->createText('ANC-1', $this->student, 'Tanpa foto profil');
        $this->comments->createText('ANC-1', $this->secondStudent, 'Dengan foto profil');

        $comments = $this->comments->paginate('ANC-1', $this->student);
        $rows = $comments->getCollection()->keyBy('User_ID');
        $this->assertNull($rows['USR-STUDENT']->Avatar_URL);
        $this->assertStringContainsString('student_std-2_avatar.WEBP', $rows['USR-STUDENT-2']->Avatar_URL);

        view()->share('errors', new ViewErrorBag);
        $html = view('announcements.comments.thread', [
            'mode' => 'full',
            'announcement' => ['Announcement_ID' => 'ANC-1'],
            'comments' => $comments,
            'showComposer' => false,
        ])->render();
        $this->assertStringContainsString('Foto profil Siswa A tidak tersedia', $html);
        $this->assertStringContainsString('student_std-2_avatar.WEBP', $html);
        $this->assertStringContainsString('onerror=', $html);
    }

    public function test_creator_detail_shows_paginated_active_thread_without_granting_global_moderation(): void
    {
        $ids = [];
        for ($index = 1; $index <= 27; $index++) {
            $id = $this->comments->createText('ANC-CREATOR', $this->student, sprintf('Komentar creator [%02d]', $index));
            DB::table('ANNOUNCEMENT_COMMENT')->where('Comment_ID', $id)->update([
                'Created_At' => sprintf('2026-10-10 09:%02d:00', $index),
            ]);
            $ids[] = $id;
        }
        $this->comments->delete('ANC-CREATOR', $ids[0], $this->student);

        $firstPage = $this->actingAs($this->teacher)
            ->get(route('announcements.show', 'ANC-CREATOR'));
        $firstPage->assertOk()
            ->assertSee('Pengumuman Guru Pemilik')
            ->assertSee('Guru Pemilik')
            ->assertSee('Akademik')
            ->assertSee('26 komentar aktif')
            ->assertSee('Komentar creator [02]')
            ->assertSee('Komentar creator [26]')
            ->assertDontSee('Komentar creator [01]')
            ->assertDontSee('Komentar creator [27]')
            ->assertDontSee('Tulis komentar...')
            ->assertDontSee('Hapus komentar');

        $this->get(route('announcements.show', ['id' => 'ANC-CREATOR', 'page' => 2]))
            ->assertOk()
            ->assertSee('Komentar creator [27]')
            ->assertDontSee('Komentar creator [01]');
        $this->assertSame(26, $this->comments->paginateForManager('ANC-CREATOR', $this->teacher)->total());
    }

    public function test_teacher_creator_cannot_read_another_creators_thread_but_administrator_can(): void
    {
        $this->comments->createText('ANC-OTHER-CREATOR', $this->student, 'Komentar privat sesuai policy');

        $this->actingAs($this->teacher)
            ->get(route('announcements.show', 'ANC-OTHER-CREATOR'))
            ->assertForbidden();
        try {
            $this->comments->paginateForManager('ANC-OTHER-CREATOR', $this->teacher);
            $this->fail('Teacher accessed another creator thread.');
        } catch (AuthorizationException) {
        }

        $this->actingAs($this->admin)
            ->get(route('announcements.show', 'ANC-OTHER-CREATOR'))
            ->assertOk()
            ->assertSee('Komentar privat sesuai policy');
    }

    public function test_favorites_are_idempotent_isolated_and_reversible(): void
    {
        $this->stickers->favorite('USR-STUDENT', 'STK-1');
        $this->stickers->favorite('USR-STUDENT', 'STK-1');
        $this->assertSame(1, DB::table('USER_STICKER_FAVORITE')->count());
        $this->assertSame(1, $this->stickers->picker('USR-STUDENT', favorites: true)['stickers']->total());
        $this->assertSame(0, $this->stickers->picker('USR-HR', favorites: true)['stickers']->total());
        $this->stickers->unfavorite('USR-STUDENT', 'STK-1');
        $this->assertSame(0, DB::table('USER_STICKER_FAVORITE')->count());
    }

    public function test_only_administrator_can_create_pack_and_upload_static_sticker(): void
    {
        Storage::fake('public');
        $this->actingAs($this->student)
            ->post(route('admin.stickers.packs.store'), ['Name' => 'Ditolak'])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('admin.stickers.packs.store'), ['Name' => 'Ekspresi', 'Sort_Order' => 2, 'Is_Active' => 1])
            ->assertRedirect();
        $packId = DB::table('MASTER_STICKER_PACK')->where('Name', 'Ekspresi')->value('Sticker_Pack_ID');
        $this->assertNotEmpty($packId);

        $this->post(route('admin.stickers.store'), [
            'Sticker_Pack_ID' => $packId, 'Name' => 'Senyum', 'Sort_Order' => 1, 'Is_Active' => 1,
            'Sticker_File' => UploadedFile::fake()->image('senyum.png', 128, 128),
        ])->assertRedirect();
        $this->assertDatabaseHas('MASTER_STICKER', ['Name' => 'Senyum', 'Mime_Type' => 'image/png']);

        $webpPath = tempnam(sys_get_temp_dir(), 'wms-sticker-');
        $image = imagecreatetruecolor(128, 128);
        imagewebp($image, $webpPath);
        imagedestroy($image);
        $this->post(route('admin.stickers.store'), [
            'Sticker_Pack_ID' => $packId, 'Name' => 'Semangat WEBP', 'Sort_Order' => 2, 'Is_Active' => 1,
            'Sticker_File' => new UploadedFile($webpPath, 'semangat.webp', 'image/webp', null, true),
        ])->assertRedirect();
        $this->assertDatabaseHas('MASTER_STICKER', ['Name' => 'Semangat WEBP', 'Mime_Type' => 'image/webp']);
    }

    public function test_gif_executable_and_oversize_sticker_uploads_are_rejected(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin);
        foreach ([
            UploadedFile::fake()->image('animasi.gif', 64, 64),
            UploadedFile::fake()->create('bahaya.exe', 10, 'application/x-msdownload'),
            UploadedFile::fake()->create('besar.png', 2050, 'image/png'),
        ] as $file) {
            $this->post(route('admin.stickers.store'), [
                'Sticker_Pack_ID' => 'PACK-1', 'Name' => 'Ditolak', 'Sticker_File' => $file,
            ])->assertSessionHasErrors('Sticker_File');
        }
        $this->assertDatabaseMissing('MASTER_STICKER', ['Name' => 'Ditolak']);
    }

    public function test_comment_endpoint_is_rate_limited_and_has_no_media_inputs(): void
    {
        $this->actingAs($this->student);
        for ($index = 1; $index <= 10; $index++) {
            $this->post(route('announcement-comments.store', 'ANC-1'), [
                'Comment_Type' => 'TEXT', 'Comment_Text' => 'Pesan '.$index,
            ])->assertRedirect();
        }
        $this->post(route('announcement-comments.store', 'ANC-1'), [
            'Comment_Type' => 'TEXT', 'Comment_Text' => 'Terlalu cepat',
        ])->assertStatus(429);

        $composer = file_get_contents(resource_path('views/announcements/feed/show.blade.php'));
        $this->assertStringNotContainsString('type="file"', $composer);
        $this->assertStringNotContainsString('external_url', $composer);
    }

    public function test_student_detail_renders_shared_comment_ui_with_empty_sticker_library(): void
    {
        DB::table('MASTER_STICKER')->delete();
        DB::table('MASTER_STICKER_PACK')->delete();

        $this->actingAs($this->student)
            ->get(route('student.portal.announcements.show', 'ANC-1'))
            ->assertOk()
            ->assertSee('0 Komentar')
            ->assertSee('Belum ada komentar.')
            ->assertSee('Jadilah yang pertama berkomentar.')
            ->assertSee('Tulis komentar...')
            ->assertSee('Stiker')
            ->assertSee('Kirim')
            ->assertSee('Belum ada stiker tersedia.');
    }

    public function test_compact_thread_loads_only_three_latest_and_uses_full_thread_count(): void
    {
        $ids = [];
        for ($index = 1; $index <= 5; $index++) {
            $ids[] = $this->comments->createText('ANC-1', $this->student, 'Komentar dashboard '.$index);
        }
        $this->comments->delete('ANC-1', $ids[0], $this->student);

        $latest = $this->comments->latestForAnnouncements(['ANC-1'], $this->student);
        $this->assertCount(3, $latest['ANC-1']);
        $this->assertSame(
            ['Komentar dashboard 3', 'Komentar dashboard 4', 'Komentar dashboard 5'],
            $latest['ANC-1']->pluck('Comment_Text')->all(),
        );
        $this->assertSame(4, $this->comments->counts(['ANC-1'])['ANC-1']);
        $this->assertSame(4, $this->comments->paginate('ANC-1', $this->student)->total());

        view()->share('errors', new ViewErrorBag);
        $html = view('announcements.comments.thread', [
            'mode' => 'compact',
            'announcement' => ['Announcement_ID' => 'ANC-1'],
            'comments' => $latest['ANC-1'],
            'commentCount' => 4,
            'detailUrl' => route('student.portal.announcements.show', 'ANC-1'),
            'currentUserAvatar' => null,
        ])->render();
        $this->assertStringContainsString('4 Komentar', $html);
        $this->assertStringContainsString('Lihat 1 komentar lainnya', $html);
        $this->assertStringNotContainsString('Komentar dashboard 2', $html);
    }

    public function test_dashboard_and_student_detail_share_the_same_comment_thread(): void
    {
        $this->actingAs($this->student)
            ->from(route('dashboard.student'))
            ->post(route('announcement-comments.store', 'ANC-1'), [
                'Comment_Type' => 'TEXT', 'Comment_Text' => 'Dikirim dari dashboard.',
            ])->assertRedirect(route('dashboard.student'));

        $this->get(route('student.portal.announcements.show', 'ANC-1'))
            ->assertOk()
            ->assertSee('Dikirim dari dashboard.');

        $this->from(route('student.portal.announcements.show', 'ANC-1'))
            ->post(route('announcement-comments.store', 'ANC-1'), [
                'Comment_Type' => 'TEXT', 'Comment_Text' => 'Dikirim dari detail.',
            ])->assertRedirect(route('student.portal.announcements.show', 'ANC-1'));

        $latest = $this->comments->latestForAnnouncements(['ANC-1'], $this->student)['ANC-1'];
        $this->assertTrue($latest->contains('Comment_Text', 'Dikirim dari dashboard.'));
        $this->assertTrue($latest->contains('Comment_Text', 'Dikirim dari detail.'));
        $this->assertSame($this->comments->paginate('ANC-1', $this->student)->total(), $this->comments->counts(['ANC-1'])['ANC-1']);
    }

    public function test_unmapped_student_identity_fails_closed_for_detail_and_forged_comment(): void
    {
        DB::table('MASTER_USER')->insert([
            'User_ID' => 'USR-UNMAPPED', 'Role_ID' => 'ROLE-STUDENT', 'Full_Name' => 'Tanpa Profil', 'Username' => 'unmapped',
        ]);
        $unmapped = new GenericUser(['id' => 'USR-UNMAPPED', 'User_ID' => 'USR-UNMAPPED', 'Role_ID' => 'ROLE-STUDENT']);

        $allRoles = $this->announcements->getById('ANC-1');
        $allRoles['Audience_Type'] = AnnouncementVisibilityService::ALL_ROLES;
        $this->assertFalse($this->app->make(AnnouncementVisibilityService::class)->canView($unmapped, $allRoles));
        $this->actingAs($unmapped)
            ->get(route('student.portal.announcements.show', 'ANC-1'))
            ->assertForbidden();
        $this->post(route('announcement-comments.store', 'ANC-1'), [
            'Comment_Type' => 'TEXT', 'Comment_Text' => 'Tidak boleh tersimpan.',
        ])->assertForbidden();
        $this->assertDatabaseMissing('ANNOUNCEMENT_COMMENT', ['Comment_Text' => 'Tidak boleh tersimpan.']);
    }

    public function test_student_dashboard_renders_social_comment_card_from_batched_thread_data(): void
    {
        for ($index = 1; $index <= 4; $index++) {
            $this->comments->createText('ANC-1', $this->student, 'Komentar sosial '.$index);
        }
        $dashboard = Mockery::mock(StudentDashboardService::class);
        $dashboard->shouldReceive('getDashboardData')->once()->andReturn([
            'announcements' => collect([array_merge($this->announcements->getById('ANC-1'), [
                'Priority_Label' => 'Informasi',
                'Start_At_Label' => '1 Oktober 2026, 08.00 WIB',
                'Expires_At_Label' => '31 Oktober 2026, 08.00 WIB',
                'Created_By' => 'USR-ADMIN',
            ])]),
            'kpi' => [],
            'langProgress' => 0,
            'reminders' => [],
            'recentActivities' => [],
        ]);
        $this->app->instance(StudentDashboardService::class, $dashboard);

        $this->actingAs($this->student)
            ->get(route('dashboard.student'))
            ->assertOk()
            ->assertSee('4 Komentar')
            ->assertSee('Komentar sosial 2')
            ->assertSee('Komentar sosial 3')
            ->assertSee('Komentar sosial 4')
            ->assertDontSee('Komentar sosial 1')
            ->assertSee('Lihat 1 komentar lainnya')
            ->assertSee('Tulis komentar...')
            ->assertSee('Lihat Semua Pengumuman')
            ->assertSee('Lihat Detail');
    }

    public function test_batched_feed_preview_does_not_add_database_queries_per_commenter(): void
    {
        for ($index = 1; $index <= 4; $index++) {
            $actor = $index % 2 === 0 ? $this->secondStudent : $this->student;
            $this->comments->createText('ANC-1', $actor, 'Komentar batch '.$index);
            $this->comments->createText('ANC-CREATOR', $actor, 'Komentar creator batch '.$index);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $threads = $this->comments->latestForAnnouncements(['ANC-1', 'ANC-CREATOR'], $this->student);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(2, $threads);
        $this->assertCount(3, $threads['ANC-1']);
        $this->assertCount(3, $threads['ANC-CREATOR']);
        $this->assertCount(1, $queries, 'Feed comments must be loaded with one windowed query.');
    }

    public function test_shared_social_feed_views_keep_mobile_avatar_composer_and_bottom_nav_contracts(): void
    {
        $thread = file_get_contents(resource_path('views/announcements/comments/thread.blade.php'));
        $composer = file_get_contents(resource_path('views/announcements/comments/composer.blade.php'));
        $avatar = file_get_contents(resource_path('views/components/resolved-avatar.blade.php'));
        $creatorDetail = file_get_contents(resource_path('views/academic/announcements/show.blade.php'));
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('flex min-w-0 gap-3', $thread);
        $this->assertStringContainsString('[overflow-wrap:anywhere]', $thread);
        $this->assertStringContainsString('flex flex-wrap items-center justify-between gap-2', $composer);
        $this->assertStringContainsString('min-h-11', $composer);
        $this->assertStringContainsString('object-cover', $avatar);
        $this->assertStringContainsString('onerror=', $avatar);
        $this->assertStringContainsString('pb-24 sm:pb-8', $creatorDetail);
        $this->assertStringContainsString('<x-mobile-bottom-nav', $layout);
    }
}
