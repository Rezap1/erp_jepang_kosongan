<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\AlumniRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Core\AlumniService;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\StudentService;
use Illuminate\Auth\GenericUser;
use Mockery;
use Tests\TestCase;

class AlumniServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(new GenericUser(['id' => 'USR-ADMIN', 'User_ID' => 'USR-ADMIN']));
        $this->app->instance(EnterpriseEventService::class, Mockery::mock(EnterpriseEventService::class));
        \Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_manual_registration_does_not_create_or_update_student(): void
    {
        $alumni = new InMemoryAlumniRepository;
        $students = new InMemoryStudentRepository;
        $service = new AlumniService($alumni, $students);

        $record = $service->create($this->manualPayload());

        $this->assertSame('MANUAL', $record['Source_Type']);
        $this->assertSame('', $record['Student_ID']);
        $this->assertSame('Specified Skilled Worker', $record['Visa_Type']);
        $this->assertSame('TG', $record['Pathway']);
        $this->assertSame(0, $students->updateCalls);
        $this->assertSame(0, $students->findByIdCalls);
    }

    public function test_wms_registration_is_coordinated_and_deactivates_student(): void
    {
        $alumni = new InMemoryAlumniRepository;
        $students = new InMemoryStudentRepository([
            $this->studentRow('STD000001'),
        ]);
        $service = new AlumniService($alumni, $students);

        $record = $service->create(array_merge($this->manualPayload(), [
            'Source_Type' => 'WMS',
            'Student_ID' => 'STD000001',
            'Full_Name' => '',
            'NIK' => '',
        ]));

        $this->assertSame('WMS', $record['Source_Type']);
        $this->assertSame('STD000001', $record['Student_ID']);
        $this->assertSame('Nama WMS', $record['Full_Name']);
        $this->assertSame('TRUE', $record['Is_Active']);
        $this->assertSame('FALSE', $students->rows[0]['Is_Active']);
        $this->assertSame('Alumni', $students->rows[0]['Enrollment_Status']);
        $this->assertSame(1, $students->updateCalls);
    }

    public function test_wms_parent_address_and_missing_nik_can_be_completed_at_conversion(): void
    {
        $alumni = new InMemoryAlumniRepository;
        $students = new InMemoryStudentRepository([
            array_merge($this->studentRow('STD000001'), [
                'National_ID' => '',
                'Parent_Name' => 'Wali WMS',
                'Address' => 'Alamat WMS',
            ]),
        ]);
        $service = new AlumniService($alumni, $students);

        $record = $service->create([
            'Source_Type' => 'WMS',
            'Student_ID' => 'STD000001',
            'NIK' => '3170000000000099',
            'Visa_Number' => 'VISA-099',
            'Visa_Type' => 'Technical Intern Training',
            'Pathway' => 'MAGANG',
            'Japan_City' => 'Osaka',
            'Departure_Date' => now()->format('Y-m-d'),
        ]);

        $this->assertSame('3170000000000099', $record['NIK']);
        $this->assertSame('Wali WMS', $record['Parent_Name']);
        $this->assertSame('Alamat WMS', $record['Indonesia_Address']);
    }

    public function test_duplicate_active_student_is_rejected(): void
    {
        $alumni = new InMemoryAlumniRepository;
        $students = new InMemoryStudentRepository([$this->studentRow('STD000001')]);
        $service = new AlumniService($alumni, $students);
        $payload = array_merge($this->manualPayload(), [
            'Source_Type' => 'WMS',
            'Student_ID' => 'STD000001',
        ]);

        $service->create($payload);
        $this->expectException(\InvalidArgumentException::class);
        $service->create($payload);
    }

    public function test_future_departure_date_is_rejected_before_any_write(): void
    {
        $alumni = new InMemoryAlumniRepository;
        $students = new InMemoryStudentRepository;
        $service = new AlumniService($alumni, $students);

        try {
            $service->create(array_merge($this->manualPayload(), [
                'Departure_Date' => now()->addDay()->format('Y-m-d'),
            ]));
            $this->fail('Future departure date should be rejected.');
        } catch (\InvalidArgumentException $e) {
            $this->assertCount(0, $alumni->rows);
        }
    }

    public function test_same_idempotency_key_returns_the_same_record_once(): void
    {
        $alumni = new InMemoryAlumniRepository;
        $students = new InMemoryStudentRepository;
        $service = new AlumniService($alumni, $students);
        $payload = $this->manualPayload();

        $first = $service->create($payload, 'request-123');
        $second = $service->create($payload, 'request-123');

        $this->assertSame($first['Alumni_ID'], $second['Alumni_ID']);
        $this->assertCount(1, $alumni->rows);
    }

    public function test_deactivate_only_soft_deletes_alumni_row(): void
    {
        $alumni = new InMemoryAlumniRepository;
        $students = new InMemoryStudentRepository;
        $service = new AlumniService($alumni, $students);
        $record = $service->create($this->manualPayload());

        $this->assertTrue($service->deactivate($record['Alumni_ID']));
        $this->assertSame('FALSE', $alumni->rows[0]['Is_Active']);
        $this->assertSame(0, $students->updateCalls);
    }

    public function test_student_service_does_not_classify_lulus_as_alumni_without_registry(): void
    {
        $alumni = new InMemoryAlumniRepository;
        $students = new InMemoryStudentRepository([
            array_merge($this->studentRow('STD000001'), ['Graduation_Status' => 'Lulus']),
        ]);
        $service = $this->studentService($students, $alumni);

        $this->assertFalse($service->isAlumni($students->rows[0]));

        $alumni->rows[] = [
            'Alumni_ID' => 'ALM000001',
            'Student_ID' => 'STD000001',
            'Is_Active' => 'TRUE',
        ];
        $this->assertTrue($service->isAlumni($students->rows[0]));
    }

    public function test_process_graduation_no_longer_deactivates_or_sets_alumni_status(): void
    {
        $alumni = new InMemoryAlumniRepository;
        $students = new InMemoryStudentRepository([
            $this->studentRow('STD000001'),
        ]);
        $service = $this->studentService($students, $alumni);

        $service->processGraduation('STD000001');

        $this->assertSame('TRUE', $students->rows[0]['Is_Active']);
        $this->assertSame('Aktif', $students->rows[0]['Enrollment_Status']);
        $this->assertSame('Lulus', $students->rows[0]['Graduation_Status']);
    }

    private function manualPayload(): array
    {
        return [
            'Source_Type' => 'MANUAL',
            'Full_Name' => 'Nama Manual',
            'NIK' => '3170000000000001',
            'Parent_Name' => 'Orang Tua',
            'Indonesia_Address' => 'Jl. Contoh No. 1',
            'Visa_Number' => 'VISA-001',
            'Visa_Type' => 'Specified Skilled Worker',
            'Pathway' => 'TG',
            'Japan_City' => 'Tokyo',
            'Departure_Date' => now()->format('Y-m-d'),
        ];
    }

    private function studentRow(string $id): array
    {
        return [
            'Student_ID' => $id,
            'User_ID' => '',
            'Student_Number' => 'NIS-001',
            'Full_Name' => 'Nama WMS',
            'National_ID' => '3170000000000002',
            'Address' => 'Alamat Student',
            'Program_ID' => 'PROG001',
            'Batch_ID' => 'BATCH001',
            'Class_ID' => 'CLASS001',
            'Enrollment_Status' => 'Aktif',
            'Graduation_Status' => '',
            'Is_Active' => 'TRUE',
        ];
    }

    private function studentService(InMemoryStudentRepository $students, InMemoryAlumniRepository $alumni): StudentService
    {
        $program = Mockery::mock(ProgramRepositoryInterface::class);
        $batch = Mockery::mock(BatchRepositoryInterface::class);
        $class = Mockery::mock(ClassRepositoryInterface::class);
        $event = Mockery::mock(EnterpriseEventService::class);
        $program->shouldReceive('findById')->zeroOrMoreTimes()->andReturn(['Program_ID' => 'PROG001', 'Is_Active' => 'TRUE']);
        $batch->shouldReceive('findById')->zeroOrMoreTimes()->andReturn(['Batch_ID' => 'BATCH001', 'Is_Active' => 'TRUE']);
        $class->shouldReceive('findById')->zeroOrMoreTimes()->andReturn(['Class_ID' => 'CLASS001', 'Is_Active' => 'TRUE']);
        $event->shouldReceive('dispatch')->zeroOrMoreTimes();

        return new StudentService($students, $program, $batch, $class, $event, $alumni);
    }
}

class InMemoryAlumniRepository implements AlumniRepositoryInterface
{
    public array $rows = [];

    public function fetchAll()
    {
        return collect($this->rows);
    }

    public function fetchAllFresh()
    {
        return collect($this->rows);
    }

    public function findById(string $id)
    {
        return collect($this->rows)->firstWhere('Alumni_ID', $id);
    }

    public function findByIdFresh(string $id)
    {
        return $this->findById($id);
    }

    public function findByStudentId(string $studentId)
    {
        return collect($this->rows)->firstWhere('Student_ID', $studentId);
    }

    public function generateNewId(string $prefix = 'ALM', int $padding = 6): string
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
            if ($row['Alumni_ID'] === $id) {
                $row = array_merge($row, $data);
            }
        }

        return true;
    }

    public function softDelete(string $id)
    {
        return $this->update($id, ['Is_Active' => 'FALSE']);
    }
}

class InMemoryStudentRepository implements StudentRepositoryInterface
{
    public array $rows;

    public int $updateCalls = 0;

    public int $findByIdCalls = 0;

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
        $this->findByIdCalls++;

        return collect($this->rows)->firstWhere('Student_ID', $id);
    }

    public function findByStudentNumber(string $number)
    {
        return collect($this->rows)->firstWhere('Student_Number', $number);
    }

    public function findByNationalId(string $nationalId)
    {
        return collect($this->rows)->firstWhere('National_ID', $nationalId);
    }

    public function generateNewId(string $prefix, int $padding = 6): string
    {
        return $prefix.str_pad('1', $padding, '0', STR_PAD_LEFT);
    }

    public function create(array $data)
    {
        $this->rows[] = $data;

        return $data;
    }

    public function update(string $id, array $data)
    {
        $this->updateCalls++;
        foreach ($this->rows as &$row) {
            if ($row['Student_ID'] === $id) {
                $row = array_merge($row, $data);
            }
        }

        return true;
    }

    public function softDelete(string $id)
    {
        return $this->update($id, ['Is_Active' => 'FALSE']);
    }

    public function clearCache() {}
}
