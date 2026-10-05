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
use InvalidArgumentException;
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
            'remember_token' => null,
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
            'workFields' => collect(['Caregiver']),
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
            ->assertSee('Bidang Pekerjaan')
            ->assertSee('Caregiver')
            ->assertSee('Specified Skilled Worker')
            ->assertSee('Tokutei Ginou (TG)')
            ->assertSee('Tokyo')
            ->assertSee('cityHash', false)
            ->assertSee('cityPalette', false)
            ->assertSee('barValueLabels', false)
            ->assertSee('alumniSelection', false)
            ->assertSee('Pilih semua Alumni pada halaman ini')
            ->assertSee('Download PDF')
            ->assertDontSee('Math.random', false);
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
        $this->assertStringContainsString('Bidang Pekerjaan', $csv);
        $this->assertStringContainsString('Wilayah Kerja', $csv);
        $this->assertStringContainsString('Specified Skilled Worker', $csv);
    }

    public function test_create_store_edit_update_show_and_deactivate_remain_available(): void
    {
        $this->alumni->shouldReceive('eligibleStudents')->once()->andReturn(collect());
        $this->get(route('alumni.create'))
            ->assertOk()
            ->assertSee('Jenis Visa')
            ->assertSee('Bidang Pekerjaan')
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
            'Work_Field' => 'Caregiver',
            'Japan_City' => 'Tokyo',
            'Departure_Date' => '2026-01-15',
        ];
        $this->alumni->shouldReceive('create')->once()->with(Mockery::on(
            fn (array $data): bool => $data['Visa_Type'] === 'Specified Skilled Worker' && $data['Pathway'] === 'TG' && $data['Work_Field'] === 'Caregiver'
        ), null)->andReturn($this->row());
        $this->post(route('alumni.store'), $payload)->assertRedirect(route('alumni.index'));

        $this->alumni->shouldReceive('getById')->times(2)->with('ALM-1', false)->andReturn($this->row());
        $this->directory->shouldReceive('decorateProfile')->once()->andReturn($this->row());
        $this->get(route('alumni.show', 'ALM-1'))
            ->assertOk()
            ->assertSee('Specified Skilled Worker')
            ->assertSee('Bidang Pekerjaan')
            ->assertSee('Caregiver')
            ->assertSee('Tokutei Ginou (TG)');
        $this->get(route('alumni.edit', 'ALM-1'))
            ->assertOk()
            ->assertSee('Jenis Visa')
            ->assertSee('Bidang Pekerjaan')
            ->assertSee('Caregiver')
            ->assertSee('Jalur');

        $this->alumni->shouldReceive('update')->once()->with('ALM-1', Mockery::on(
            fn (array $data): bool => $data['Visa_Type'] === 'Specified Skilled Worker' && $data['Pathway'] === 'TG' && $data['Work_Field'] === 'Caregiver'
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

    public function test_selected_pdf_requires_at_least_one_valid_alumni_id(): void
    {
        $this->directory->shouldNotReceive('selectedProfiles');

        $this->post(route('alumni.export-selected-pdf'), [])
            ->assertSessionHasErrors('alumni_ids');
        $this->post(route('alumni.export-selected-pdf'), ['alumni_ids' => ['../ALM000001']])
            ->assertSessionHasErrors('alumni_ids.0');
    }

    public function test_selected_pdf_requires_authentication_and_existing_alumni_role(): void
    {
        auth()->logout();
        $this->post(route('alumni.export-selected-pdf'), ['alumni_ids' => ['ALM000001']])
            ->assertRedirect(route('login'));

        $role = Mockery::mock(RoleService::class);
        $role->shouldReceive('getRoleById')->zeroOrMoreTimes()->andReturn([
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

        $this->post(route('alumni.export-selected-pdf'), ['alumni_ids' => ['ALM000001']])
            ->assertForbidden();
    }

    public function test_selected_pdf_deduplicates_ids_requeries_profiles_and_returns_valid_pdf(): void
    {
        $this->directory->shouldReceive('selectedProfiles')
            ->once()
            ->with(['ALM000001', 'ALM000002'])
            ->andReturn(collect([
                $this->row(),
                array_merge($this->row(), ['Alumni_ID' => 'ALM000002', 'Full_Name' => 'Budi']),
            ]));

        $response = $this->post(route('alumni.export-selected-pdf'), [
            'alumni_ids' => ['alm000001', 'ALM000001', 'ALM000002'],
            'Full_Name' => 'Nama dari browser tidak dipercaya',
        ]);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('data-alumni-terpilih-', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertGreaterThan(1000, strlen($response->getContent()));
    }

    public function test_single_selected_pdf_has_safe_id_filename_and_optional_fields_are_safe(): void
    {
        $this->directory->shouldReceive('selectedProfiles')
            ->once()
            ->with(['ALM000001'])
            ->andReturn(collect([array_merge($this->row(), [
                'Work_Field' => '',
                'Visa_Type' => '',
                'Visa_Number' => '',
                'Work_Region' => '',
            ])]));

        $response = $this->post(route('alumni.export-selected-pdf'), ['alumni_ids' => ['ALM000001']]);

        $response->assertOk();
        $this->assertStringContainsString('alumni-ALM000001.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_selected_pdf_rejects_nonexistent_alumni(): void
    {
        $this->directory->shouldReceive('selectedProfiles')
            ->once()
            ->with(['ALM999999'])
            ->andThrow(new InvalidArgumentException('Satu atau lebih Alumni yang dipilih tidak ditemukan.'));

        $this->post(route('alumni.export-selected-pdf'), ['alumni_ids' => ['ALM999999']])
            ->assertSessionHasErrors('alumni_ids');
    }

    public function test_pdf_template_contains_only_trusted_selected_profiles_and_work_field(): void
    {
        $html = view('pdf.selected_alumni', [
            'alumni' => collect([$this->row()]),
            'exportedAt' => now('Asia/Jakarta'),
        ])->render();

        $this->assertStringContainsString('ALM-1', $html);
        $this->assertStringContainsString('Caregiver', $html);
        $this->assertStringContainsString('Specified Skilled Worker', $html);
        $this->assertStringNotContainsString('ALM999999', $html);
    }

    public function test_empty_directory_renders_safe_zero_and_disabled_selection_contract(): void
    {
        $this->directory->shouldReceive('directory')->once()->andReturn([
            'alumni' => new LengthAwarePaginator([], 0, 15, 1, ['path' => route('alumni.index')]),
            'analytics' => array_merge($this->analytics(), [
                'total' => 0,
                'tg' => 0,
                'magang' => 0,
                'total_regions' => 0,
                'pathway_chart' => ['labels' => ['Tokutei Ginou', 'Magang'], 'values' => [0, 0]],
                'region_chart' => ['labels' => [], 'values' => []],
            ]),
            'programs' => collect(),
            'batches' => collect(),
            'classes' => collect(),
            'visaTypes' => collect(),
            'workFields' => collect(),
            'regions' => collect(),
        ]);

        $this->get(route('alumni.index'))
            ->assertOk()
            ->assertSee('Belum ada data alumni')
            ->assertSee('0 alumni dipilih')
            ->assertSee(':disabled="selected.length === 0"', false)
            ->assertDontSee('NaN');
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
            'Work_Field' => 'Caregiver',
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
