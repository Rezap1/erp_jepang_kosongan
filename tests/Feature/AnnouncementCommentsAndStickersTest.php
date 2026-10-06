<?php

namespace Tests\Feature;

use App\Interfaces\GoogleSheets\AnnouncementRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\RoleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Academic\AnnouncementCommentService;
use App\Services\Academic\AnnouncementService;
use App\Services\Academic\AnnouncementVisibilityService;
use App\Services\Academic\StickerLibraryService;
use App\Services\Core\RoleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AnnouncementCommentsAndStickersTest extends TestCase
{
    private AnnouncementCommentService $comments;

    private StickerLibraryService $stickers;

    private GenericUser $student;

    private GenericUser $outsider;

    private GenericUser $admin;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['ANNOUNCEMENT_AUDIENCE_CLASS', 'ANNOUNCEMENT_AUDIENCE_ROLE', 'USER_STICKER_FAVORITE', 'ANNOUNCEMENT_COMMENT', 'MASTER_STICKER', 'MASTER_STICKER_PACK', 'MASTER_ANNOUNCEMENT', 'MASTER_USER', 'MASTER_ROLE'] as $table) {
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
        Schema::create('MASTER_ANNOUNCEMENT', function (Blueprint $table): void {
            $table->string('Announcement_ID')->primary();
            $table->string('Target_Role')->nullable();
            $table->string('Target_ID')->nullable();
        });
        (require database_path('migrations/wms_mysql/2026_10_06_000001_add_announcement_v2.php'))->up();
        DB::table('MASTER_ROLE')->insert([
            ['Role_ID' => 'ROLE-STUDENT', 'Role_Name' => 'STUDENT'], ['Role_ID' => 'ROLE-HR', 'Role_Name' => 'HR'], ['Role_ID' => 'ROLE-ADMINISTRATOR', 'Role_Name' => 'ADMINISTRATOR'],
        ]);
        DB::table('MASTER_USER')->insert([
            ['User_ID' => 'USR-STUDENT', 'Role_ID' => 'ROLE-STUDENT', 'Full_Name' => 'Siswa A', 'Username' => 'siswa'],
            ['User_ID' => 'USR-HR', 'Role_ID' => 'ROLE-HR', 'Full_Name' => 'HR A', 'Username' => 'hr'],
            ['User_ID' => 'USR-ADMIN', 'Role_ID' => 'ROLE-ADMINISTRATOR', 'Full_Name' => 'Admin', 'Username' => 'admin'],
        ]);
        DB::table('MASTER_ANNOUNCEMENT')->insert(['Announcement_ID' => 'ANC-1', 'Audience_Type' => 'ROLE', 'Target_Role' => 'STUDENT']);
        DB::table('MASTER_STICKER_PACK')->insert(['Sticker_Pack_ID' => 'PACK-1', 'Name' => 'Reaksi', 'Is_Active' => true, 'Sort_Order' => 0]);
        DB::table('MASTER_STICKER')->insert([
            ['Sticker_ID' => 'STK-1', 'Sticker_Pack_ID' => 'PACK-1', 'Name' => 'Semangat', 'File_Path' => 'stickers/test.webp', 'Mime_Type' => 'image/webp', 'File_Size' => 100, 'Is_Active' => true, 'Sort_Order' => 0],
            ['Sticker_ID' => 'STK-OFF', 'Sticker_Pack_ID' => 'PACK-1', 'Name' => 'Lama', 'File_Path' => 'stickers/off.webp', 'Mime_Type' => 'image/webp', 'File_Size' => 100, 'Is_Active' => false, 'Sort_Order' => 1],
        ]);

        $roles = Mockery::mock(RoleRepositoryInterface::class);
        $roles->shouldReceive('fetchAll')->andReturn(collect([
            ['Role_ID' => 'ROLE-STUDENT', 'Role_Name' => 'STUDENT', 'Is_Active' => 'TRUE'], ['Role_ID' => 'ROLE-HR', 'Role_Name' => 'HR', 'Is_Active' => 'TRUE'], ['Role_ID' => 'ROLE-ADMINISTRATOR', 'Role_Name' => 'ADMINISTRATOR', 'Is_Active' => 'TRUE'],
        ]));
        $roles->shouldReceive('findById')->andReturnUsing(fn ($id) => ['Role_Name' => str_replace('ROLE-', '', $id), 'Is_Active' => 'TRUE']);
        $students = Mockery::mock(StudentRepositoryInterface::class);
        $students->shouldReceive('fetchAll')->andReturn(collect([['Student_ID' => 'STD-1', 'User_ID' => 'USR-STUDENT', 'Is_Active' => 'TRUE']]));
        $batches = Mockery::mock(BatchRepositoryInterface::class);
        $batches->shouldReceive('findById')->andReturnNull();
        $classes = Mockery::mock(ClassRepositoryInterface::class);
        $classes->shouldReceive('findById')->andReturnNull();
        $visibility = new AnnouncementVisibilityService(new RoleService($roles), $students, $batches, $classes);
        $repo = new class implements AnnouncementRepositoryInterface
        {
            public function fetchAll()
            {
                return collect([$this->findById('ANC-1')]);
            }

            public function findById(string $id)
            {
                return $id === 'ANC-1' ? ['Announcement_ID' => 'ANC-1', 'Audience_Type' => 'ROLE', 'Target_Role' => 'STUDENT', 'Title' => 'Tes', 'Content' => 'Isi', 'Is_Active' => 'TRUE', 'Status' => 'PUBLISHED', 'Publish_Date' => '2020-01-01', 'Expired_Date' => '2099-01-01'] : null;
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
        $announcements = new AnnouncementService($repo, null, $visibility);
        $this->comments = new AnnouncementCommentService($announcements, $visibility);
        $this->stickers = new StickerLibraryService;
        $this->app->instance(AnnouncementCommentService::class, $this->comments);
        $this->app->instance(StickerLibraryService::class, $this->stickers);
        $this->student = new GenericUser(['id' => 'USR-STUDENT', 'User_ID' => 'USR-STUDENT', 'Role_ID' => 'ROLE-STUDENT']);
        $this->outsider = new GenericUser(['id' => 'USR-HR', 'User_ID' => 'USR-HR', 'Role_ID' => 'ROLE-HR']);
        $this->admin = new GenericUser(['id' => 'USR-ADMIN', 'User_ID' => 'USR-ADMIN', 'Role_ID' => 'ROLE-ADMINISTRATOR']);
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
        $id = $this->comments->createText('ANC-1', $this->student, '<script>alert(1)</script>');
        $this->actingAs($this->student);
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
}
