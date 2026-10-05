<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\AlumniRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Core\AlumniDirectoryService;
use App\Services\Core\AlumniService;
use App\Services\Core\BatchService;
use App\Services\Core\ClassService;
use App\Services\Core\ProgramService;
use App\Services\Core\StudentService;
use Mockery;
use Tests\TestCase;

class AlumniDirectoryServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_classification_totals_and_region_aggregation_are_canonical_and_safe(): void
    {
        $service = $this->service([
            $this->row('ALM-1', 'TG', 'Engineer', 'Tokyo'),
            $this->row('ALM-2', 'MAGANG', 'Technical Intern', 'Osaka'),
            $this->row('ALM-3', 'TG', 'Engineer', 'TOKYO'),
            $this->row('ALM-4', '', '', ''),
        ]);

        $result = $service->directory([]);

        $this->assertSame(4, $result['analytics']['total']);
        $this->assertSame(2, $result['analytics']['tg']);
        $this->assertSame(1, $result['analytics']['magang']);
        $this->assertSame(1, $result['analytics']['unknown_pathway']);
        $this->assertSame(2, $result['analytics']['total_regions']);
        $this->assertSame(1, $result['analytics']['unknown_region']);
        $this->assertSame(['Tokyo', 'Osaka'], $result['analytics']['region_chart']['labels']);
        $this->assertSame([2, 1], $result['analytics']['region_chart']['values']);
    }

    public function test_pathway_visa_region_and_search_filters_compose(): void
    {
        $service = $this->service([
            array_merge($this->row('ALM-1', 'TG', 'Engineer', 'Tokyo'), ['Full_Name' => 'Akira']),
            array_merge($this->row('ALM-2', 'MAGANG', 'Technical Intern', 'Osaka'), ['Full_Name' => 'Budi']),
            array_merge($this->row('ALM-3', 'TG', 'Engineer', 'Osaka'), [
                'Full_Name' => 'Citra',
                'Program_ID' => 'PRG-1',
                'Batch_ID' => 'BAT-1',
                'Class_ID' => 'CLS-1',
            ]),
        ]);

        $rows = $service->filteredRows([
            'search' => 'citra',
            'program' => 'PRG-1',
            'batch' => 'BAT-1',
            'class' => 'CLS-1',
            'pathway' => 'TG',
            'visa_type' => 'engineer',
            'region' => 'osaka',
            'source' => 'MANUAL',
            'departure_year' => '2026',
        ]);

        $this->assertSame(['ALM-3'], $rows->pluck('Alumni_ID')->all());
    }

    public function test_tg_and_magang_filters_do_not_misclassify_unknown_values(): void
    {
        $service = $this->service([
            $this->row('ALM-TG', 'TG', 'Engineer', 'Tokyo'),
            $this->row('ALM-MAGANG', 'MAGANG', 'Technical Intern', 'Osaka'),
            $this->row('ALM-UNKNOWN', 'Tokutei Ginou', '', ''),
        ]);

        $this->assertSame(['ALM-TG'], $service->filteredRows(['pathway' => 'TG'])->pluck('Alumni_ID')->all());
        $this->assertSame(['ALM-MAGANG'], $service->filteredRows(['pathway' => 'MAGANG'])->pluck('Alumni_ID')->all());
        $this->assertSame('Belum diketahui', $service->decorateProfile(['Pathway' => 'Tokutei Ginou'])['Pathway_Label']);
    }

    public function test_empty_dataset_produces_zeroed_chart_safe_analytics(): void
    {
        $analytics = $this->service([])->directory([])['analytics'];

        $this->assertSame(0, $analytics['total']);
        $this->assertSame([0, 0], $analytics['pathway_chart']['values']);
        $this->assertSame([], $analytics['region_chart']['labels']);
        $this->assertSame([], $analytics['region_chart']['values']);
    }

    public function test_directory_paginates_filtered_results_without_changing_totals(): void
    {
        $rows = collect(range(1, 16))
            ->map(fn (int $number): array => $this->row('ALM-'.$number, 'TG', 'Engineer', 'Tokyo'))
            ->all();

        $result = $this->service($rows)->directory(['pathway' => 'TG']);

        $this->assertSame(16, $result['analytics']['total']);
        $this->assertCount(15, $result['alumni']->items());
        $this->assertSame(2, $result['alumni']->lastPage());
    }

    private function service(array $rows): AlumniDirectoryService
    {
        $alumniRepository = new DirectoryAlumniRepository($rows);
        $studentRepository = new DirectoryStudentRepository;
        $alumniService = new AlumniService($alumniRepository, $studentRepository);

        $studentService = Mockery::mock(StudentService::class);
        $studentService->shouldReceive('getAllStudents')->zeroOrMoreTimes()->andReturn(collect());
        $programService = Mockery::mock(ProgramService::class);
        $programService->shouldReceive('getAllPrograms')->zeroOrMoreTimes()->andReturn(collect());
        $batchService = Mockery::mock(BatchService::class);
        $batchService->shouldReceive('getAllBatches')->zeroOrMoreTimes()->andReturn(collect());
        $classService = Mockery::mock(ClassService::class);
        $classService->shouldReceive('getAllClasses')->zeroOrMoreTimes()->andReturn(collect());

        return new AlumniDirectoryService(
            $alumniService,
            $studentService,
            $programService,
            $batchService,
            $classService
        );
    }

    private function row(string $id, string $pathway, string $visaType, string $region): array
    {
        return [
            'Alumni_ID' => $id,
            'Source_Type' => 'MANUAL',
            'Student_ID' => '',
            'Full_Name' => $id,
            'NIK' => 'NIK-'.$id,
            'Visa_Number' => 'VISA-'.$id,
            'Visa_Type' => $visaType,
            'Pathway' => $pathway,
            'Japan_City' => $region,
            'Departure_Date' => '2026-01-15',
            'Is_Active' => 'TRUE',
        ];
    }
}

class DirectoryAlumniRepository implements AlumniRepositoryInterface
{
    public function __construct(public array $rows = []) {}

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
        return true;
    }

    public function softDelete(string $id)
    {
        return true;
    }
}

class DirectoryStudentRepository implements StudentRepositoryInterface
{
    public function fetchAll()
    {
        return collect();
    }

    public function findById(string $id)
    {
        return null;
    }

    public function findByStudentNumber(string $number)
    {
        return null;
    }

    public function findByNationalId(string $nationalId)
    {
        return null;
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
