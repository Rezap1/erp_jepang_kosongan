<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\AnnouncementRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Services\Academic\AnnouncementService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\GenericUser;
use Tests\TestCase;

class AnnouncementServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_start_is_inclusive_and_expiry_is_exclusive(): void
    {
        $repo = new InMemoryAnnouncementRepository([
            $this->row('A1', '2026-10-08 08:00:00', '2026-10-09 09:00:00'),
        ]);
        $service = $this->service($repo);

        $before = CarbonImmutable::parse('2026-10-08 07:59:59', 'Asia/Jakarta');
        $atStart = CarbonImmutable::parse('2026-10-08 08:00:00', 'Asia/Jakarta');
        $atExpiry = CarbonImmutable::parse('2026-10-09 09:00:00', 'Asia/Jakarta');

        $this->assertCount(0, $service->getActiveAnnouncements('STUDENT', 'CLS-1', $before));
        $this->assertCount(1, $service->getActiveAnnouncements('STUDENT', 'CLS-1', $atStart));
        $this->assertCount(0, $service->getActiveAnnouncements('STUDENT', 'CLS-1', $atExpiry));
    }

    public function test_class_targeting_and_priority_sort_are_server_side(): void
    {
        $repo = new InMemoryAnnouncementRepository([
            $this->row('NORMAL', '2026-10-01 00:00:00', '2026-10-20 00:00:00', 'NORMAL', 'CLASS', 'CLS-1'),
            $this->row('URGENT', '2026-10-01 00:00:00', '2026-10-20 00:00:00', 'URGENT', 'ALL_STUDENTS'),
            $this->row('OTHER', '2026-10-01 00:00:00', '2026-10-20 00:00:00', 'URGENT', 'CLASS', 'CLS-2'),
        ]);
        $service = $this->service($repo, new InMemoryClassRepository([
            ['Class_ID' => 'CLS-1', 'Batch_ID' => 'BAT-1', 'Is_Active' => 'TRUE'],
        ]));
        $now = CarbonImmutable::parse('2026-10-09 09:00:00', 'Asia/Jakarta');

        $visible = $service->getActiveAnnouncements('STUDENT', 'CLS-1', $now);
        $this->assertSame(['URGENT', 'NORMAL'], $visible->pluck('Announcement_ID')->all());
        $this->assertSame('Mendesak', $service->priorityLabel('URGENT'));
        $this->assertStringContainsString('1 Kelas', $service->audienceLabel([
            'Audience_Type' => 'CLASS', 'Audience_ID' => 'CLS-1',
        ]));
    }

    public function test_create_normalizes_immediate_start_and_delete_deactivates(): void
    {
        $now = CarbonImmutable::parse('2026-10-08 08:00:00', 'Asia/Jakarta');
        CarbonImmutable::setTestNow($now);
        $repo = new InMemoryAnnouncementRepository;
        $service = $this->service($repo);

        $service->create(['Title' => 'Ujian', 'Message' => 'Datang tepat waktu', 'Priority' => 'URGENT', 'Audience_Type' => 'ALL_STUDENTS', 'Expires_At' => '2026-10-09 09:00:00']);
        $this->assertSame('2026-10-08 08:00:00', $repo->rows[0]['Start_At']);
        $service->delete($repo->rows[0]['Announcement_ID']);
        $this->assertSame('FALSE', $repo->rows[0]['Is_Active']);
    }

    public function test_forged_class_is_rejected(): void
    {
        $service = $this->service(new InMemoryAnnouncementRepository, new InMemoryClassRepository([]));
        $this->expectException(\InvalidArgumentException::class);
        $service->create([
            'Title' => 'Rahasia Kelas', 'Message' => 'Pesan', 'Priority' => 'NORMAL',
            'Audience_Type' => 'CLASS', 'Audience_ID' => 'CLS-PALSU',
            'Start_At' => '2026-10-09 10:00:00', 'Expires_At' => '2026-10-09 09:00:00',
        ]);
    }

    public function test_expiry_must_be_after_start(): void
    {
        $service = $this->service(new InMemoryAnnouncementRepository);
        $this->expectException(\InvalidArgumentException::class);
        $service->create([
            'Title' => 'Jadwal', 'Message' => 'Pesan', 'Priority' => 'NORMAL',
            'Audience_Type' => 'ALL_STUDENTS',
            'Start_At' => '2026-10-09 10:00:00', 'Expires_At' => '2026-10-09 09:00:00',
        ]);
    }

    public function test_draft_inactive_and_missing_expiry_are_hidden(): void
    {
        $draft = $this->row('DRAFT', '2026-10-01 00:00:00', '2026-10-20 00:00:00');
        $draft['Status'] = 'DRAFT';
        $inactive = $this->row('INACTIVE', '2026-10-01 00:00:00', '2026-10-20 00:00:00');
        $inactive['Is_Active'] = 'FALSE';
        $noExpiry = $this->row('NOEXPIRY', '2026-10-01 00:00:00', '2026-10-20 00:00:00');
        unset($noExpiry['Expires_At']);
        $service = $this->service(new InMemoryAnnouncementRepository([$draft, $inactive, $noExpiry]));
        $this->assertCount(0, $service->getActiveAnnouncements('STUDENT', 'CLS-1', CarbonImmutable::parse('2026-10-09', 'Asia/Jakarta')));
    }

    public function test_student_detail_fails_closed_outside_class_scope(): void
    {
        $row = $this->row('CLASS-A', '2026-10-01 00:00:00', '2026-10-20 00:00:00', 'IMPORTANT', 'CLASS', 'CLS-A');
        $service = $this->service(new InMemoryAnnouncementRepository([$row]));
        $now = CarbonImmutable::parse('2026-10-09 09:00:00', 'Asia/Jakarta');
        $this->assertNull($service->getVisibleForStudent('CLASS-A', 'CLS-B', $now));
        $this->assertSame('CLASS-A', $service->getVisibleForStudent('CLASS-A', 'CLS-A', $now)['Announcement_ID']);
    }

    public function test_authenticated_creator_cannot_be_forged_and_google_payload_is_canonical(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-TEACHER-A', 'User_ID' => 'USR-TEACHER-A']));
        $repo = new InMemoryAnnouncementRepository;
        $service = $this->service($repo);

        $service->create([
            'Title' => 'Libur Kegiatan Belajar',
            'Message' => 'Sekolah libur besok.',
            'Audience_Type' => 'ALL_STUDENTS',
            'Expires_At' => '2026-10-20 09:00:00',
            'Created_By' => 'USR-MASTER-FORGED',
        ]);

        $row = $repo->rows[0];
        $this->assertSame('USR-TEACHER-A', $row['Created_By']);
        $this->assertSame('USR-TEACHER-A', $row['Updated_By']);
        $this->assertSame('ALL_STUDENTS', $row['Audience_Type']);
        $this->assertSame('ALL_STUDENTS', $row['Target_Role']);
        $this->assertSame('', $row['Audience_ID']);
        $this->assertSame('', $row['Target_ID']);
        $this->assertSame($row['Message'], $row['Content']);
        $this->assertSame($row['Start_At'], $row['Publish_Date']);
        $this->assertSame($row['Expires_At'], $row['Expired_Date']);
    }

    public function test_update_preserves_creator_and_delete_audits_authenticated_actor(): void
    {
        $row = $this->row('OWN', '2026-10-01 00:00:00', '2026-10-20 00:00:00');
        $row['Created_By'] = 'USR-TEACHER-A';
        $repo = new InMemoryAnnouncementRepository([$row]);
        $service = $this->service($repo);
        $this->actingAs(new GenericUser(['id' => 'USR-TEACHER-A', 'User_ID' => 'USR-TEACHER-A']));

        $service->update('OWN', [
            'Title' => 'Diperbarui', 'Message' => 'Pesan baru', 'Priority' => 'IMPORTANT',
            'Audience_Type' => 'ALL_STUDENTS', 'Status' => 'PUBLISHED',
            'Start_At' => '2026-10-01 00:00:00', 'Expires_At' => '2026-10-21 00:00:00',
            'Created_By' => 'USR-FORGED',
        ]);
        $this->assertSame('USR-TEACHER-A', $repo->rows[0]['Created_By']);
        $this->assertSame('USR-TEACHER-A', $repo->rows[0]['Updated_By']);

        $service->delete('OWN');
        $this->assertSame('FALSE', $repo->rows[0]['Is_Active']);
        $this->assertSame('INACTIVE', $repo->rows[0]['Status']);
        $this->assertSame('USR-TEACHER-A', $repo->rows[0]['Updated_By']);
    }

    private function service(AnnouncementRepositoryInterface $repository, ?ClassRepositoryInterface $classes = null): AnnouncementService
    {
        $this->app->instance(AnnouncementRepositoryInterface::class, $repository);
        $this->app->instance(ClassRepositoryInterface::class, $classes ?? new InMemoryClassRepository([]));

        return $this->app->make(AnnouncementService::class);
    }

    private function row(string $id, string $start, string $expires, string $priority = 'NORMAL', string $audience = 'ALL_STUDENTS', string $classId = ''): array
    {
        return [
            'Announcement_ID' => $id, 'Title' => $id, 'Message' => 'Pesan',
            'Priority' => $priority, 'Audience_Type' => $audience, 'Audience_ID' => $classId,
            'Status' => 'PUBLISHED', 'Is_Active' => 'TRUE', 'Start_At' => $start, 'Expires_At' => $expires,
            'Created_At' => $start,
        ];
    }
}

class InMemoryAnnouncementRepository implements AnnouncementRepositoryInterface
{
    public array $rows;

    public function __construct(array $rows = [])
    {
        $this->rows = $rows;
    }

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
            }
        }

        return true;
    }

    public function softDelete(string $id)
    {
        return $this->update($id, ['Is_Active' => 'FALSE', 'Status' => 'INACTIVE']);
    }
}

class InMemoryClassRepository implements ClassRepositoryInterface
{
    public function __construct(private array $rows) {}

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
