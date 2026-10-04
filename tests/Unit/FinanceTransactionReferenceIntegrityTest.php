<?php

namespace Tests\Unit;

use App\Exceptions\FinancialIntegrityException;
use App\Interfaces\GoogleSheets\AccountRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\PayrollRepositoryInterface;
use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use App\Services\Finance\TransactionService;
use Illuminate\Auth\GenericUser;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FinanceTransactionReferenceIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(new GenericUser([
            'id' => 'USR-FIN',
            'User_ID' => 'USR-FIN',
            'Role' => 'FINANCE',
        ]));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public static function relationalReferenceTypes(): array
    {
        return [
            'payment' => ['Payment'],
            'payment reversal' => ['PaymentReversal'],
            'invoice' => ['Invoice'],
            'payroll' => ['Payroll'],
        ];
    }

    #[DataProvider('relationalReferenceTypes')]
    public function test_relational_transaction_cannot_be_created_without_reference_id(string $referenceType): void
    {
        $transactions = new ReferenceIntegrityTransactionRepository;
        $service = $this->service($transactions);

        $this->expectException(FinancialIntegrityException::class);
        $this->expectExceptionMessage('Reference_ID wajib diisi');

        $service->create($this->payload([
            'Reference_Type' => $referenceType,
            'Reference_ID' => '',
        ]));
    }

    public function test_manual_other_transaction_without_relation_remains_valid(): void
    {
        $transactions = new ReferenceIntegrityTransactionRepository;
        $service = $this->service($transactions);

        $service->create($this->payload([
            'Reference_Type' => 'Other',
            'Reference_ID' => '',
        ]));

        $this->assertCount(1, $transactions->rows);
        $this->assertSame('Other', $transactions->rows[0]['Reference_Type']);
        $this->assertSame('', $transactions->rows[0]['Reference_ID']);
    }

    public function test_legacy_blank_payment_reference_can_be_reclassified_as_manual_other(): void
    {
        $transactions = new ReferenceIntegrityTransactionRepository([[
            'Transaction_ID' => 'TRX-LEGACY',
            'Transaction_Date' => '2026-10-02',
            'Account_ID' => 'ACC-1',
            'Type' => 'Expense',
            'Category' => 'Operasional',
            'Amount' => 230000,
            'Reference_Type' => 'Payment',
            'Reference_ID' => null,
            'Is_Active' => 'TRUE',
        ]]);
        $service = $this->service($transactions);

        $service->update('TRX-LEGACY', [
            'Reference_Type' => 'Other',
            'Reference_ID' => '',
        ]);

        $this->assertSame('Other', $transactions->rows[0]['Reference_Type']);
        $this->assertSame('', $transactions->rows[0]['Reference_ID']);
    }

    public function test_update_cannot_introduce_relational_type_without_reference_id(): void
    {
        $transactions = new ReferenceIntegrityTransactionRepository([[
            'Transaction_ID' => 'TRX-MANUAL',
            'Transaction_Date' => '2026-10-02',
            'Account_ID' => 'ACC-1',
            'Type' => 'Expense',
            'Category' => 'Operasional',
            'Amount' => 230000,
            'Reference_Type' => 'Other',
            'Reference_ID' => '',
            'Is_Active' => 'TRUE',
        ]]);
        $service = $this->service($transactions);

        $this->expectException(FinancialIntegrityException::class);
        $this->expectExceptionMessage('Reference_ID wajib diisi');

        $service->update('TRX-MANUAL', [
            'Reference_Type' => 'Invoice',
            'Reference_ID' => '',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'Transaction_Date' => '2026-10-04',
            'Account_ID' => 'ACC-1',
            'Type' => 'Expense',
            'Category' => 'Operasional',
            'Amount' => 100000,
            'Description' => 'Transaksi manual',
        ], $overrides);
    }

    private function service(ReferenceIntegrityTransactionRepository $transactions): TransactionService
    {
        $accounts = Mockery::mock(AccountRepositoryInterface::class);
        $accounts->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect([[
            'Account_ID' => 'ACC-1',
            'Account_Code' => '101',
            'Is_Active' => 'TRUE',
        ]]));

        return new TransactionService(
            $transactions,
            $accounts,
            Mockery::mock(EnterpriseEventService::class)->shouldIgnoreMissing(),
            Mockery::mock(InvoiceRepositoryInterface::class)->shouldIgnoreMissing(),
            Mockery::mock(PaymentRepositoryInterface::class)->shouldIgnoreMissing(),
            Mockery::mock(PayrollRepositoryInterface::class)->shouldIgnoreMissing(),
        );
    }
}

class ReferenceIntegrityTransactionRepository implements TransactionRepositoryInterface
{
    public function __construct(public array $rows = []) {}

    public function fetchAll()
    {
        return collect($this->rows);
    }

    public function findById(string $id)
    {
        return collect($this->rows)->firstWhere('Transaction_ID', $id);
    }

    public function create(array $data)
    {
        $this->rows[] = $data;

        return true;
    }

    public function update(string $id, array $data)
    {
        foreach ($this->rows as &$row) {
            if (($row['Transaction_ID'] ?? '') === $id) {
                $row = array_merge($row, $data);

                return true;
            }
        }

        return false;
    }

    public function delete(string $id)
    {
        return false;
    }

    public function generateNewId(string $prefix = 'TRX', int $padding = 6): string
    {
        return $prefix.'-000001';
    }

    public function clearCache(): void {}
}
