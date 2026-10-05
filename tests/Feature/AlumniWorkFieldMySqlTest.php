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
}
