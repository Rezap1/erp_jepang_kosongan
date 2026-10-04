<?php

namespace Tests\Unit;

use App\Http\Controllers\Finance\TransactionController;
use App\Http\Controllers\Finance\PaymentController;
use App\Interfaces\GoogleSheets\AccountRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\PayrollRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use App\Services\Finance\AccountService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\TransactionPresentationService;
use App\Services\Finance\TransactionService;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class FinanceTransactionPresentationHardeningTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_regular_invoice_linked_payment_transaction_detail_is_readable(): void
    {
        $detail = $this->presenter()->presentDetail($this->paymentTransaction());
        $html = $this->renderDetail($detail);

        $this->assertSame('RCT-2026-0001', $detail['source']['label']);
        $this->assertSame('Cita Maharani', $detail['party']['name']);
        $this->assertSame('Rp 1.000.000', $detail['amount_label']);
        $this->assertSame('Transfer Bank', $detail['payment']['method_label']);
        $this->assertSame('Terverifikasi', $detail['payment']['status_label']);
        $this->assertSame('BNK-001', $detail['payment']['reference_number']);
        $this->assertSame('INV-2026-001', $detail['invoice']['number']);
        $this->assertTrue($detail['evidence']['available']);
        $this->assertFalse($detail['evidence']['is_pdf']);
        $this->assertStringContainsString('PAY-INV', $html);
        $this->assertStringContainsString('BNK-001', $html);
    }

    public function test_common_fields_and_numeric_account_code_are_presented(): void
    {
        $transaction = $this->manualTransaction(['Account_ID' => '101']);
        $detail = $this->presenter(transactions: [$transaction])->presentDetail($transaction);

        $this->assertSame('TRX-MANUAL', $detail['transaction_id']);
        $this->assertSame('5 September 2026', $detail['date_label']);
        $this->assertSame('Pengeluaran', $detail['type_label']);
        $this->assertSame('Operasional', $detail['category_label']);
        $this->assertSame('Rp 250.000', $detail['amount_label']);
        $this->assertSame('101 - Kas Utama', $detail['account']['label']);
        $this->assertSame('Pembelian ATK', $detail['description']);
    }

    public function test_self_service_payment_without_invoice_renders_without_invoice_dereference(): void
    {
        $detail = $this->presenter(
            transactions: [$this->selfServiceTransaction()],
            payments: [$this->selfServicePayment(['Proof_File' => 'payments/self-service.pdf'])],
            invoices: []
        )->presentDetail($this->selfServiceTransaction());

        $html = view('finance.transactions.show', [
            'presentation' => $detail,
            'canAccessPayments' => true,
            'canAccessInvoices' => true,
            'canMutateTransactions' => true,
        ])->render();

        $this->assertSame('Pembayaran Mandiri Siswa', $detail['source']['type_label']);
        $this->assertTrue($detail['payment']['invoice_optional']);
        $this->assertNull($detail['invoice']);
        $this->assertStringContainsString('Pembayaran mandiri ini valid tanpa invoice', $html);
        $this->assertStringContainsString('Buka PDF', $html);
        $this->assertStringNotContainsString('Undefined variable $title', $html);
    }

    public function test_payment_proof_pdf_image_absent_and_actual_private_serving_contract_are_safe(): void
    {
        $presenter = $this->presenter();

        $image = $presenter->paymentEvidence($this->payment(['Proof_File' => 'payments/proof.jpg']));
        $pdf = $presenter->paymentEvidence($this->payment(['Proof_File' => 'payments/proof.pdf']));
        $none = $presenter->paymentEvidence($this->payment(['Proof_File' => '']));
        $unsafe = $presenter->paymentEvidence($this->payment(['Proof_File' => '../secret.txt']));

        $this->assertTrue($image['available']);
        $this->assertFalse($image['is_pdf']);
        $this->assertStringContainsString('/finance/payments/PAY-INV/proof', $image['download_url']);
        $this->assertTrue($pdf['available']);
        $this->assertTrue($pdf['is_pdf']);
        $this->assertFalse($none['available']);
        $this->assertSame('Bukti pembayaran tidak tersedia.', $none['message']);
        $this->assertFalse($unsafe['available']);

        Storage::fake('local');
        Storage::disk('local')->put('payments/served-proof.png', 'proof-content');
        $paymentService = Mockery::mock(PaymentService::class);
        $paymentService->shouldReceive('getById')->once()->with('PAY-SERVE')->andReturn([
            'Payment_ID' => 'PAY-SERVE',
            'Proof_Image' => 'payments/served-proof.png',
        ]);
        $controller = new PaymentController($paymentService, Mockery::mock(TransactionPresentationService::class));

        $response = $controller->downloadProof(
            Request::create('/finance/payments/PAY-SERVE/proof?inline=1', 'GET', ['inline' => '1']),
            'PAY-SERVE'
        );

        $this->assertSame('proof-content', file_get_contents($response->getFile()->getRealPath()));
        $this->assertStringContainsString(
            str_replace('/', DIRECTORY_SEPARATOR, 'payments/served-proof.png'),
            $response->getFile()->getRealPath()
        );
    }

    public function test_missing_invoice_student_and_account_do_not_crash_detail(): void
    {
        $detail = $this->presenter(
            transactions: [$this->paymentTransaction(['Account_ID' => 'ACC-MISSING'])],
            payments: [$this->payment(['Invoice_ID' => 'INV-MISSING', 'Student_ID' => 'STU-MISSING', 'Proof_File' => ''])],
            invoices: [],
            students: [],
            accounts: []
        )->presentDetail($this->paymentTransaction(['Account_ID' => 'ACC-MISSING']));

        $html = view('finance.transactions.show', [
            'presentation' => $detail,
            'canAccessPayments' => true,
            'canAccessInvoices' => true,
            'canMutateTransactions' => true,
        ])->render();

        $this->assertSame('Data siswa tidak ditemukan', $detail['party']['name']);
        $this->assertSame('Akun tidak ditemukan', $detail['account']['label']);
        $this->assertTrue($detail['payment']['invoice_missing']);
        $this->assertStringContainsString('Invoice terkait tidak ditemukan', $html);
        $this->assertStringContainsString('Bukti pembayaran tidak tersedia.', $html);
    }

    public function test_missing_payment_remains_an_explicit_integrity_failure(): void
    {
        $missingPayment = $this->presenter(
            transactions: [$this->paymentTransaction(['Reference_ID' => 'PAY-MISSING'])],
            payments: []
        )->presentDetail($this->paymentTransaction(['Reference_ID' => 'PAY-MISSING']));

        $this->assertTrue($missingPayment['integrity']['relational']);
        $this->assertFalse($missingPayment['integrity']['valid']);
        $this->assertStringContainsString('PAY-MISSING tidak ditemukan', $missingPayment['integrity_warning']);
        $this->assertFalse($missingPayment['source']['available']);
        $this->assertNull($missingPayment['party']);
        $this->assertTrue($missingPayment['evidence']['applicable']);
        $this->assertFalse($missingPayment['evidence']['available']);
    }

    public function test_manual_expense_hides_non_applicable_relational_sections(): void
    {
        $transaction = $this->manualTransaction();
        $detail = $this->presenter(transactions: [$transaction])->presentDetail($transaction);
        $html = $this->renderDetail($detail);

        $this->assertSame('Transaksi Manual', $detail['source']['type_label']);
        $this->assertSame('TRX-MANUAL', $detail['source']['official_reference']);
        $this->assertFalse($detail['source']['status_applicable']);
        $this->assertNull($detail['party']);
        $this->assertFalse($detail['evidence']['applicable']);
        $this->assertFalse($detail['reversal']['applicable']);
        $this->assertNull($detail['integrity_warning']);
        $this->assertStringContainsString('Pembelian ATK', $html);
        $this->assertStringNotContainsString('Pihak terkait tidak ditemukan', $html);
        $this->assertStringNotContainsString('Bukti pembayaran tidak tersedia', $html);
        $this->assertStringNotContainsString('Nomor Referensi Resmi', $html);
    }

    public function test_manual_income_uses_transaction_id_without_fake_status_or_party(): void
    {
        $transaction = $this->manualTransaction([
            'Transaction_ID' => 'TRX-MANUAL-INCOME',
            'Type' => 'Income',
            'Category' => 'Pendapatan Lain',
            'Amount' => 400000,
            'Description' => 'Pendapatan manual',
        ]);
        $detail = $this->presenter(transactions: [$transaction])->presentDetail($transaction);

        $this->assertSame('Pemasukan', $detail['type_label']);
        $this->assertSame('Rp 400.000', $detail['amount_label']);
        $this->assertSame('TRX-MANUAL-INCOME', $detail['source']['official_reference']);
        $this->assertNull($detail['source']['status_label']);
        $this->assertNull($detail['party']);
    }

    public function test_trx000017_manual_acceptance_contract(): void
    {
        $transaction = $this->manualTransaction([
            'Transaction_ID' => 'TRX-000017',
            'Transaction_Date' => '2026-10-02',
            'Account_ID' => 'ACC-1',
            'Amount' => 230000,
            'Description' => 'Pembayaran 10 pcs lanyard+ID card',
        ]);
        $detail = $this->presenter(transactions: [$transaction])->presentDetail($transaction);

        $this->assertSame('Transaksi Manual', $detail['source']['label']);
        $this->assertSame('Pengeluaran', $detail['type_label']);
        $this->assertSame('Rp 230.000', $detail['amount_label']);
        $this->assertSame('2 Oktober 2026', $detail['date_label']);
        $this->assertSame('Operasional', $detail['category_label']);
        $this->assertSame('101 - Kas Utama', $detail['account']['label']);
        $this->assertSame('Pembayaran 10 pcs lanyard+ID card', $detail['description']);
        $this->assertSame('TRX-000017', $detail['source']['official_reference']);
    }

    public function test_invoice_source_resolves_status_party_and_metadata(): void
    {
        $transaction = $this->invoiceTransaction();
        $detail = $this->presenter(transactions: [$transaction])->presentDetail($transaction);

        $this->assertSame('INV-2026-001', $detail['source']['official_reference']);
        $this->assertSame('Terlambat', $detail['source']['status_label']);
        $this->assertSame('Cita Maharani', $detail['party']['name']);
        $this->assertSame('INV-2026-001', $detail['invoice']['number']);
        $this->assertSame('September 2026', $detail['invoice']['period']);
        $this->assertSame('Biaya pendidikan September', $detail['invoice']['description']);
        $this->assertFalse($detail['evidence']['applicable']);
        $this->assertNull($detail['payment']);
    }

    public function test_invoice_company_party_uses_actual_company_foreign_key(): void
    {
        $transaction = $this->invoiceTransaction();
        $detail = $this->presenter(
            transactions: [$transaction],
            payments: [$this->payment(['Invoice_ID' => 'INV-OTHER'])],
            invoices: [$this->invoice(['Student_ID' => '', 'Company_ID' => 'COMP-1'])],
            students: [],
            companies: [[
                'Company_ID' => 'COMP-1',
                'Company_Code' => 'WKM-001',
                'Company_Name' => 'PT Wakamiya Indonesia',
            ]]
        )->presentDetail($transaction);

        $this->assertSame('Perusahaan', $detail['party']['type_label']);
        $this->assertSame('PT Wakamiya Indonesia', $detail['party']['name']);
        $this->assertSame('WKM-001', $detail['party']['context']);
    }

    public function test_payroll_source_resolves_employee_status_and_metadata(): void
    {
        $transaction = $this->payrollTransaction();
        $detail = $this->presenter(
            transactions: [$transaction],
            payrolls: [$this->payroll()],
            employees: [$this->employee()]
        )->presentDetail($transaction);

        $this->assertSame('DOC-PAY-2026-0001', $detail['source']['official_reference']);
        $this->assertSame('Lunas', $detail['source']['status_label']);
        $this->assertSame('Raka Pratama', $detail['party']['name']);
        $this->assertSame('2026-09', $detail['payroll']['period']);
        $this->assertSame('Rp 6.250.000', $detail['payroll']['net_salary_label']);
        $this->assertFalse($detail['evidence']['applicable']);
    }

    public function test_blank_payment_reference_is_not_reclassified_as_manual(): void
    {
        $transaction = $this->paymentTransaction([
            'Transaction_ID' => 'TRX-000018',
            'Reference_ID' => null,
        ]);
        $detail = $this->presenter(transactions: [$transaction])->presentDetail($transaction);
        $html = $this->renderDetail($detail);

        $this->assertSame('Pembayaran', $detail['source']['type_label']);
        $this->assertTrue($detail['integrity']['relational']);
        $this->assertFalse($detail['integrity']['valid']);
        $this->assertStringContainsString('Reference_ID kosong', $detail['integrity_warning']);
        $this->assertNotSame('Transaksi Manual', $detail['source']['label']);
        $this->assertNull($detail['source']['official_reference']);
        $this->assertStringContainsString('Tidak dapat di-resolve', $html);
        $this->assertStringNotContainsString('Transaksi Manual', $html);
    }

    public function test_reversal_and_original_transactions_show_audit_linkage(): void
    {
        $original = $this->paymentTransaction();
        $reversal = $this->paymentTransaction([
            'Transaction_ID' => 'TRX-REV-1',
            'Type' => 'Expense',
            'Reference_Type' => 'PaymentReversal',
            'Description' => 'Koreksi pembayaran',
        ]);
        $presenter = $this->presenter(transactions: [$original, $reversal]);

        $originalDetail = $presenter->presentDetail($original);
        $reversalDetail = $presenter->presentDetail($reversal);

        $this->assertTrue($originalDetail['reversal']['has_reversal']);
        $this->assertTrue($originalDetail['reversal']['applicable']);
        $this->assertSame('TRX-REV-1', $originalDetail['reversal']['transaction']['id']);
        $this->assertTrue($reversalDetail['reversal']['is_reversal']);
        $this->assertSame('TRX-PAY-1', $reversalDetail['reversal']['transaction']['id']);
        $this->assertSame('Pembalikan/Koreksi', $reversalDetail['type_label']);
        $this->assertSame('Pembalikan Pembayaran', $reversalDetail['source']['type_label']);
        $this->assertSame('Cita Maharani', $reversalDetail['party']['name']);
        $this->assertNotNull($reversalDetail['payment']);
        $this->assertTrue($reversalDetail['evidence']['applicable']);
    }

    public function test_history_presentations_use_human_context_not_raw_ids_as_primary_fields(): void
    {
        $items = $this->presenter()->presentCollection([$this->paymentTransaction(), $this->manualTransaction()]);

        $this->assertSame('Cita Maharani', $items[0]['party']['name']);
        $this->assertSame('RCT-2026-0001', $items[0]['source']['label']);
        $this->assertSame('Pemasukan', $items[0]['type_label']);
        $this->assertSame('Rp 1.000.000', $items[0]['amount_label']);
        $this->assertNotSame('STU-1', $items[0]['party']['name']);
        $this->assertSame('Transaksi Manual', $items[1]['source']['label']);
    }

    public function test_unknown_transaction_returns_404_not_redirect_or_500(): void
    {
        $controller = new TransactionController(
            Mockery::mock(TransactionService::class)->shouldReceive('getById')->once()->with('TRX-MISSING')->andReturn(null)->getMock(),
            Mockery::mock(AccountService::class),
            Mockery::mock(TransactionPresentationService::class)
        );

        $this->expectException(NotFoundHttpException::class);

        $controller->show('TRX-MISSING');
    }

    public function test_transaction_history_presenter_uses_bounded_snapshots(): void
    {
        $transactions = [];
        for ($i = 1; $i <= 100; $i++) {
            $transactions[] = $this->paymentTransaction([
                'Transaction_ID' => 'TRX-PAY-' . $i,
                'Reference_ID' => 'PAY-INV',
            ]);
        }

        $presenter = $this->presenter(
            transactions: $transactions,
            payments: [$this->payment()],
            exactReads: true
        );

        $presented = $presenter->presentCollection($transactions);

        $this->assertCount(100, $presented);
        $this->assertSame('Cita Maharani', $presented->first()['party']['name']);
    }

    public function test_snapshot_read_failure_is_not_masked_as_missing_finance_data(): void
    {
        $paymentRepository = Mockery::mock(PaymentRepositoryInterface::class);
        $paymentRepository->shouldReceive('getAll')->once()->andThrow(new \RuntimeException('MySQL read failed'));

        $presenter = new TransactionPresentationService(
            $paymentRepository,
            $this->repo(InvoiceRepositoryInterface::class, 'getAll', []),
            $this->repo(StudentRepositoryInterface::class, 'fetchAll', []),
            $this->repo(CompanyRepositoryInterface::class, 'fetchAll', []),
            $this->repo(AccountRepositoryInterface::class, 'fetchAll', []),
            $this->repo(PayrollRepositoryInterface::class, 'getAll', []),
            $this->repo(TransactionRepositoryInterface::class, 'fetchAll', [$this->paymentTransaction()]),
            $this->repo(UserRepositoryInterface::class, 'fetchAll', []),
            $this->repo(EmployeeRepositoryInterface::class, 'fetchAll', [])
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MySQL read failed');

        $presenter->presentDetail($this->paymentTransaction());
    }

    public function test_unauthorized_student_cannot_open_finance_transaction_route(): void
    {
        $roleService = Mockery::mock(\App\Services\Core\RoleService::class);
        $roleService->shouldReceive('getRoleById')->with('ROLE-STUDENT')->andReturn([
            'Role_ID' => 'ROLE-STUDENT',
            'Role_Name' => 'STUDENT',
            'Is_Active' => 'TRUE',
        ]);
        $this->app->instance(\App\Services\Core\RoleService::class, $roleService);

        $this->actingAs(new GenericUser([
            'id' => 'USR-STUDENT',
            'User_ID' => 'USR-STUDENT',
            'Role_ID' => 'ROLE-STUDENT',
        ]));

        $this->get(route('transactions.show', 'TRX-PAY-1'))->assertForbidden();
    }

    private function presenter(
        array $transactions = [],
        array $payments = [],
        array $invoices = [],
        array $students = [],
        array $companies = [],
        array $accounts = [],
        array $payrolls = [],
        array $users = [],
        array $employees = [],
        bool $exactReads = false
    ): TransactionPresentationService {
        $transactions = $transactions ?: [$this->paymentTransaction()];
        $payments = $payments ?: [$this->payment()];
        $invoices = $invoices ?: [$this->invoice()];
        $students = $students ?: [$this->student()];
        $accounts = $accounts ?: [$this->account()];
        $users = $users ?: [
            ['User_ID' => 'USR-FIN', 'Full_Name' => 'Finance WMS'],
        ];

        return new TransactionPresentationService(
            $this->repo(PaymentRepositoryInterface::class, 'getAll', $payments, $exactReads),
            $this->repo(InvoiceRepositoryInterface::class, 'getAll', $invoices, $exactReads),
            $this->repo(StudentRepositoryInterface::class, 'fetchAll', $students, $exactReads),
            $this->repo(CompanyRepositoryInterface::class, 'fetchAll', $companies, $exactReads),
            $this->repo(AccountRepositoryInterface::class, 'fetchAll', $accounts, $exactReads),
            $this->repo(PayrollRepositoryInterface::class, 'getAll', $payrolls, $exactReads),
            $this->repo(TransactionRepositoryInterface::class, 'fetchAll', $transactions, $exactReads),
            $this->repo(UserRepositoryInterface::class, 'fetchAll', $users, $exactReads),
            $this->repo(EmployeeRepositoryInterface::class, 'fetchAll', $employees, $exactReads)
        );
    }

    private function renderDetail(array $detail): string
    {
        return view('finance.transactions.show', [
            'presentation' => $detail,
            'canAccessPayments' => true,
            'canAccessInvoices' => true,
            'canMutateTransactions' => true,
        ])->render();
    }

    private function repo(string $interface, string $method, array $rows, bool $exactReads = false)
    {
        $repo = Mockery::mock($interface);
        $expectation = $repo->shouldReceive($method)->andReturn(collect($rows));
        $exactReads ? $expectation->once() : $expectation->zeroOrMoreTimes();

        return $repo;
    }

    private function paymentTransaction(array $overrides = []): array
    {
        return array_merge([
            'Transaction_ID' => 'TRX-PAY-1',
            'Transaction_Date' => '2026-09-05',
            'Account_ID' => 'ACC-1',
            'Type' => 'Income',
            'Category' => 'Payment Receipt',
            'Amount' => 1000000,
            'Reference_Type' => 'Payment',
            'Reference_ID' => 'PAY-INV',
            'Description' => 'Pembayaran pendidikan',
            'Created_By' => 'USR-FIN',
            'Updated_By' => 'USR-FIN',
            'Created_At' => '2026-09-05 01:13:00',
            'Updated_At' => '2026-09-05 01:15:00',
            'Is_Active' => 'TRUE',
        ], $overrides);
    }

    private function selfServiceTransaction(array $overrides = []): array
    {
        return $this->paymentTransaction(array_merge([
            'Transaction_ID' => 'TRX-PAY-SELF',
            'Reference_ID' => 'PAY-SELF',
            'Description' => 'Pembayaran mandiri terverifikasi',
        ], $overrides));
    }

    private function invoiceTransaction(array $overrides = []): array
    {
        return $this->paymentTransaction(array_merge([
            'Transaction_ID' => 'TRX-INVOICE-1',
            'Reference_Type' => 'Invoice',
            'Reference_ID' => 'INV-2026-001',
            'Description' => 'Penerbitan tagihan',
        ], $overrides));
    }

    private function payrollTransaction(array $overrides = []): array
    {
        return $this->paymentTransaction(array_merge([
            'Transaction_ID' => 'TRX-PAYROLL-1',
            'Type' => 'Expense',
            'Category' => 'Payroll',
            'Amount' => 6250000,
            'Reference_Type' => 'Payroll',
            'Reference_ID' => 'PAYROLL-1',
            'Description' => 'Pembayaran gaji September 2026',
        ], $overrides));
    }

    private function manualTransaction(array $overrides = []): array
    {
        return array_merge([
            'Transaction_ID' => 'TRX-MANUAL',
            'Transaction_Date' => '2026-09-05',
            'Account_ID' => 'ACC-1',
            'Type' => 'Expense',
            'Category' => 'Operasional',
            'Amount' => 250000,
            'Reference_Type' => 'Other',
            'Reference_ID' => '',
            'Description' => 'Pembelian ATK',
            'Is_Active' => 'TRUE',
        ], $overrides);
    }

    private function payment(array $overrides = []): array
    {
        return array_merge([
            'Payment_ID' => 'PAY-INV',
            'Invoice_ID' => 'INV-2026-001',
            'Student_ID' => 'STU-1',
            'Payment_Type' => 'STUDENT',
            'Amount_Paid' => 1000000,
            'Payment_Date' => '2026-09-05',
            'Payment_Method' => 'Transfer',
            'Receipt_Number' => 'RCT-2026-0001',
            'Reference_Number' => 'BNK-001',
            'Proof_File' => 'payments/proof.jpg',
            'Status' => 'Verified',
            'Verified_By' => 'USR-FIN',
            'Verified_At' => '2026-09-05 01:15:00',
            'Is_Active' => 'TRUE',
        ], $overrides);
    }

    private function selfServicePayment(array $overrides = []): array
    {
        return $this->payment(array_merge([
            'Payment_ID' => 'PAY-SELF',
            'Invoice_ID' => '',
            'Payment_Type' => 'STUDENT_SELF_SERVICE',
            'Receipt_Number' => 'RCT-SELF-2026-0001',
        ], $overrides));
    }

    private function invoice(array $overrides = []): array
    {
        return array_merge([
            'Invoice_ID' => 'INV-2026-001',
            'Invoice_Number' => 'INV-2026-001',
            'Student_ID' => 'STU-1',
            'Amount' => 1500000,
            'Remaining_Amount' => 500000,
            'Status' => 'Partial Paid',
            'Due_Date' => '2026-09-30',
            'Period' => 'September 2026',
            'Description' => 'Biaya pendidikan September',
        ], $overrides);
    }

    private function payroll(array $overrides = []): array
    {
        return array_merge([
            'Payroll_ID' => 'PAYROLL-1',
            'Document_Number' => 'DOC-PAY-2026-0001',
            'Employee_ID' => 'EMP-1',
            'Payroll_Period' => '2026-09',
            'Base_Salary' => 6000000,
            'Total_Allowances' => 500000,
            'Total_Deductions' => 250000,
            'Net_Salary' => 6250000,
            'Status' => 'Paid',
            'Paid_Date' => '2026-09-30',
        ], $overrides);
    }

    private function employee(array $overrides = []): array
    {
        return array_merge([
            'Employee_ID' => 'EMP-1',
            'Employee_Number' => 'EMP-001',
            'Full_Name' => 'Raka Pratama',
            'Is_Active' => 'TRUE',
        ], $overrides);
    }

    private function student(array $overrides = []): array
    {
        return array_merge([
            'Student_ID' => 'STU-1',
            'Full_Name' => 'Cita Maharani',
            'Student_Number' => 'NIS-001',
            'Class_Name' => 'Kelas Sakura',
            'Program_Name' => 'Bahasa Jepang',
        ], $overrides);
    }

    private function account(array $overrides = []): array
    {
        return array_merge([
            'Account_ID' => 'ACC-1',
            'Account_Code' => '101',
            'Account_Name' => 'Kas Utama',
            'Is_Active' => 'TRUE',
        ], $overrides);
    }
}
