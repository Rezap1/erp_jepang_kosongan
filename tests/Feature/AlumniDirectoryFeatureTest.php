<?php

namespace Tests\Feature;

use App\Services\Core\AlumniDirectoryService;
use App\Services\Core\AlumniService;
use App\Services\Core\BatchService;
use App\Services\Core\ClassService;
use App\Services\Core\ProgramService;
use App\Services\Core\RoleService;
use App\Services\Core\StudentService;
use Illuminate\Auth\GenericUser;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;

class AlumniDirectoryFeatureTest extends TestCase
{
    private AlumniService $alumni;

    private AlumniDirectoryService $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Mockery::mock(RoleService::class);
        $role->shouldReceive('getRoleById')->zeroOrMoreTimes()->andReturn([
            'Role_ID' => 'ROLE-ADMIN',
            'Role_Name' => 'ADMINISTRATOR',
            'Is_Active' => 'TRUE',
        ]);
        $this->app->instance(RoleService::class, $role);

        $this->alumni = Mockery::mock(AlumniService::class);
        $this->directory = Mockery::mock(AlumniDirectoryService::class);
        $this->app->instance(AlumniService::class, $this->alumni);
        $this->app->instance(AlumniDirectoryService::class, $this->directory);

        $students = Mockery::mock(StudentService::class);
        $students->shouldReceive('getAllStudents')->zeroOrMoreTimes()->andReturn(collect());
        $programs = Mockery::mock(ProgramService::class);
        $programs->shouldReceive('getAllPrograms')->zeroOrMoreTimes()->andReturn(collect());
        $programs->shouldReceive('getProgramById')->zeroOrMoreTimes()->andReturn(null);
        $batches = Mockery::mock(BatchService::class);
        $batches->shouldReceive('getAllBatches')->zeroOrMoreTimes()->andReturn(collect());
        $batches->shouldReceive('getBatchById')->zeroOrMoreTimes()->andReturn(null);
        $classes = Mockery::mock(ClassService::class);
        $classes->shouldReceive('getAllClasses')->zeroOrMoreTimes()->andReturn(collect());
        $classes->shouldReceive('getClassById')->zeroOrMoreTimes()->andReturn(null);
        $this->app->instance(StudentService::class, $students);
        $this->app->instance(ProgramService::class, $programs);
        $this->app->instance(BatchService::class, $batches);
        $this->app->instance(ClassService::class, $classes);

        $this->actingAs(new GenericUser([
            'id' => 'USR-ADMIN',
            'User_ID' => 'USR-ADMIN',
            'Role_ID' => 'ROLE-ADMIN',
        ]));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_directory_renders_analytics_filters_and_enhanced_card(): void
    {
        $row = $this->row();
        $this->directory->shouldReceive('directory')->once()->with(Mockery::on(
            fn (array $filters): bool => $filters['pathway'] === 'TG' && $filters['search'] === 'Akira'
        ))->andReturn([
            'alumni' => new LengthAwarePaginator([$row], 1, 15, 1, ['path' => route('alumni.index')]),
            'analytics' => $this->analytics(),
            'programs' => collect([['Program_ID' => 'PRG-1', 'Program_Name' => 'Program Jepang']]),
            'batches' => collect([['Batch_ID' => 'BAT-1', 'Batch_Name' => 'Batch 1']]),
            'classes' => collect([['Class_ID' => 'CLS-1', 'Class_Name' => 'Kelas 1']]),
            'visaTypes' => collect(['Specified Skilled Worker']),
            'regions' => collect(['Tokyo']),
        ]);

        $this->get(route('alumni.index', ['search' => 'Akira', 'pathway' => 'TG']))
            ->assertOk()
            ->assertSee('Total Alumni')
            ->assertSee('TG vs Magang')
            ->assertSee('Alumni berdasarkan wilayah kerja')
            ->assertSee('Program Jepang')
            ->assertSee('Batch 1')
            ->assertSee('Kelas 1')
            ->assertSee('Jenis Visa')
            ->assertSee('Specified Skilled Worker')
            ->assertSee('Tokutei Ginou (TG)')
            ->assertSee('Tokyo');
    }

    public function test_export_respects_filters_and_appends_new_columns(): void
    {
        $this->directory->shouldReceive('filteredRows')->once()->with(Mockery::on(
            fn (array $filters): bool => $filters['pathway'] === 'TG' && $filters['region'] === 'Tokyo'
        ), 'all')->andReturn(collect([$this->row()]));

        $response = $this->get(route('alumni.export-csv', ['pathway' => 'TG', 'region' => 'Tokyo']));

        $response->assertOk()->assertDownload();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Jenis Visa', $csv);
        $this->assertStringContainsString('Jalur', $csv);
        $this->assertStringContainsString('Wilayah Kerja', $csv);
        $this->assertStringContainsString('Specified Skilled Worker', $csv);
    }

    public function test_create_store_edit_update_show_and_deactivate_remain_available(): void
    {
        $this->alumni->shouldReceive('eligibleStudents')->once()->andReturn(collect());
        $this->get(route('alumni.create'))
            ->assertOk()
            ->assertSee('Jenis Visa')
            ->assertSee('Tokutei Ginou (TG)');

        $payload = [
            'Source_Type' => 'MANUAL',
            'Full_Name' => 'Akira',
            'NIK' => '3170000000000001',
            'Parent_Name' => 'Parent',
            'Indonesia_Address' => 'Jakarta',
            'Visa_Number' => 'VISA-1',
            'Visa_Type' => 'Specified Skilled Worker',
            'Pathway' => 'TG',
            'Japan_City' => 'Tokyo',
            'Departure_Date' => '2026-01-15',
        ];
        $this->alumni->shouldReceive('create')->once()->with(Mockery::on(
            fn (array $data): bool => $data['Visa_Type'] === 'Specified Skilled Worker' && $data['Pathway'] === 'TG'
        ), null)->andReturn($this->row());
        $this->post(route('alumni.store'), $payload)->assertRedirect(route('alumni.index'));

        $this->alumni->shouldReceive('getById')->times(2)->with('ALM-1', false)->andReturn($this->row());
        $this->directory->shouldReceive('decorateProfile')->once()->andReturn($this->row());
        $this->get(route('alumni.show', 'ALM-1'))
            ->assertOk()
            ->assertSee('Specified Skilled Worker')
            ->assertSee('Tokutei Ginou (TG)');
        $this->get(route('alumni.edit', 'ALM-1'))
            ->assertOk()
            ->assertSee('Jenis Visa')
            ->assertSee('Jalur');

        $this->alumni->shouldReceive('update')->once()->with('ALM-1', Mockery::on(
            fn (array $data): bool => $data['Visa_Type'] === 'Specified Skilled Worker' && $data['Pathway'] === 'TG'
        ))->andReturn($this->row());
        $this->put(route('alumni.update', 'ALM-1'), $payload)->assertRedirect(route('alumni.show', 'ALM-1'));

        $this->alumni->shouldReceive('deactivate')->once()->with('ALM-1')->andReturn(true);
        $this->delete(route('alumni.destroy', 'ALM-1'))->assertRedirect(route('alumni.index'));
    }

    public function test_unauthorized_role_cannot_access_alumni_directory(): void
    {
        $role = Mockery::mock(RoleService::class);
        $role->shouldReceive('getRoleById')->twice()->andReturn([
            'Role_ID' => 'ROLE-MARKETING',
            'Role_Name' => 'MARKETING',
            'Is_Active' => 'TRUE',
        ]);
        $this->app->instance(RoleService::class, $role);
        $this->actingAs(new GenericUser([
            'id' => 'USR-MARKETING',
            'User_ID' => 'USR-MARKETING',
            'Role_ID' => 'ROLE-MARKETING',
        ]));
        $this->directory->shouldNotReceive('directory');

        $this->get(route('alumni.index'))->assertForbidden();
    }

    private function row(): array
    {
        return [
            'Alumni_ID' => 'ALM-1',
            'Source_Type' => 'MANUAL',
            'Source_Label' => 'Input Manual',
            'Student_ID' => '',
            'Full_Name' => 'Akira',
            'NIK' => '3170000000000001',
            'Parent_Name' => 'Parent',
            'Indonesia_Address' => 'Jakarta',
            'Visa_Number' => 'VISA-1',
            'Visa_Type' => 'Specified Skilled Worker',
            'Pathway' => 'TG',
            'Pathway_Label' => 'Tokutei Ginou (TG)',
            'Japan_City' => 'Tokyo',
            'Work_Region' => 'Tokyo',
            'Departure_Date' => '2026-01-15',
            'Program_ID' => '',
            'Batch_ID' => '',
            'Class_ID' => '',
            'Program_Name' => '-',
            'Batch_Name' => '-',
            'Class_Name' => '-',
            'Is_Active' => 'TRUE',
        ];
    }

    private function analytics(): array
    {
        return [
            'total' => 1,
            'tg' => 1,
            'magang' => 0,
            'total_regions' => 1,
            'unknown_pathway' => 0,
            'unknown_region' => 0,
            'pathway_chart' => ['labels' => ['Tokutei Ginou', 'Magang'], 'values' => [1, 0]],
            'region_chart' => ['labels' => ['Tokyo'], 'values' => [1]],
        ];
    }
}
