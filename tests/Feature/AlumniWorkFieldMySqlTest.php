<?php

namespace Tests\Feature;

use App\Repositories\GoogleSheets\AlumniRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AlumniWorkFieldMySqlTest extends TestCase
{
    private bool $transactionStarted = false;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = DB::connection((string) config('wms_runtime.connection'));
        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)
            || $connection->getDatabaseName() !== 'wms_mysql_test') {
            $this->markTestSkipped('Alumni MySQL integration test requires wms_mysql_test.');
        }

        $connection->beginTransaction();
        $this->transactionStarted = true;
    }

    protected function tearDown(): void
    {
        if ($this->transactionStarted) {
            DB::connection((string) config('wms_runtime.connection'))->rollBack();
        }

        parent::tearDown();
    }

    public function test_work_field_is_nullable_and_persists_without_changing_visa_type(): void
    {
        $schema = Schema::connection((string) config('wms_runtime.connection'));
        $this->assertTrue($schema->hasColumn('MASTER_ALUMNI', 'Work_Field'));

        $repository = app(AlumniRepository::class);
        $repository->create([
            'Alumni_ID' => 'ALM990001',
            'Source_Type' => 'MANUAL',
            'Full_Name' => 'MySQL Work Field Test',
            'NIK' => '990001',
            'Visa_Number' => 'VISA-990001',
            'Visa_Type' => 'Existing Manual Visa Type',
            'Pathway' => 'TG',
            'Work_Field' => 'Bidang Pekerjaan Test',
            'Japan_City' => 'Test City',
            'Departure_Date' => '2026-01-01',
            'Status' => 'ALUMNI',
            'Is_Active' => 'TRUE',
        ]);

        $persisted = $repository->findByIdFresh('ALM990001');
        $this->assertSame('Bidang Pekerjaan Test', $persisted['Work_Field']);
        $this->assertSame('Existing Manual Visa Type', $persisted['Visa_Type']);

        $repository->create([
            'Alumni_ID' => 'ALM990002',
            'Source_Type' => 'MANUAL',
            'Full_Name' => 'Nullable Work Field Test',
            'NIK' => '990002',
            'Visa_Number' => 'VISA-990002',
            'Visa_Type' => 'Manual Visa Type',
            'Pathway' => 'MAGANG',
            'Work_Field' => null,
            'Japan_City' => 'Test City',
            'Departure_Date' => '2026-01-01',
            'Status' => 'ALUMNI',
            'Is_Active' => 'TRUE',
        ]);

        $this->assertNull($repository->findByIdFresh('ALM990002')['Work_Field']);
    }

    public function test_engineer_pathway_fits_existing_varchar_and_persists_without_field_inference(): void
    {
        $connection = DB::connection((string) config('wms_runtime.connection'));
        $column = $connection->selectOne(
            "SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'MASTER_ALUMNI'
               AND COLUMN_NAME = 'Pathway'"
        );
        $this->assertNotNull($column);
        $this->assertSame('varchar', strtolower((string) $column->DATA_TYPE));
        $this->assertGreaterThanOrEqual(32, (int) $column->CHARACTER_MAXIMUM_LENGTH);

        $repository = app(AlumniRepository::class);
        $repository->create([
            'Alumni_ID' => 'ALM990003',
            'Source_Type' => 'MANUAL',
            'Full_Name' => 'MySQL Engineer Pathway Test',
            'NIK' => '990003',
            'Visa_Number' => 'VISA-990003',
            'Visa_Type' => 'Existing Manual Visa Type',
            'Pathway' => 'ENGINEER',
            'Work_Field' => 'Mechanical Engineer',
            'Japan_City' => 'Nagoya',
            'Departure_Date' => '2026-01-01',
            'Status' => 'ALUMNI',
            'Is_Active' => 'TRUE',
        ]);

        $persisted = $repository->findByIdFresh('ALM990003');
        $this->assertSame('ENGINEER', $persisted['Pathway']);
        $this->assertSame('Mechanical Engineer', $persisted['Work_Field']);
        $this->assertSame('Existing Manual Visa Type', $persisted['Visa_Type']);
    }
}
