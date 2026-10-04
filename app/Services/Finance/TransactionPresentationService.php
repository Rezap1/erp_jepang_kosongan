<?php

namespace App\Services\Finance;

use App\Helpers\StoragePathHelper;
use App\Interfaces\GoogleSheets\AccountRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\PayrollRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use App\Support\Finance\Money;
use App\Support\Finance\AcceptedPaymentCalculator;
use App\Support\Presentation\IndonesianPresentation;
use App\Support\Reporting\HumanReadableResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class TransactionPresentationService
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private InvoiceRepositoryInterface $invoiceRepository,
        private StudentRepositoryInterface $studentRepository,
        private CompanyRepositoryInterface $companyRepository,
        private AccountRepositoryInterface $accountRepository,
        private PayrollRepositoryInterface $payrollRepository,
        private TransactionRepositoryInterface $transactionRepository,
        private UserRepositoryInterface $userRepository,
        private EmployeeRepositoryInterface $employeeRepository
    ) {
    }

    public function presentCollection(iterable $transactions): Collection
    {
        $snapshot = $this->snapshot();

        return collect($transactions)
            ->map(fn ($transaction) => $this->present((array) $transaction, $snapshot))
            ->values();
    }

    public function presentDetail(array $transaction): array
    {
        $snapshot = $this->snapshot();

        return $this->present($transaction, $snapshot);
    }

    public function paymentEvidence(array $payment): array
    {
        $paymentId = trim((string) ($payment['Payment_ID'] ?? ''));
        $storedPath = $payment['Proof_File'] ?? $payment['Proof_Image'] ?? '';
        $safePath = StoragePathHelper::normalizePrivateStoredPath($storedPath);

        if ($paymentId === '' || $safePath === null) {
            return [
                'applicable' => true,
                'available' => false,
                'message' => 'Bukti pembayaran tidak tersedia.',
                'is_pdf' => false,
                'inline_url' => null,
                'download_url' => null,
            ];
        }

        $isPdf = str_ends_with(strtolower($safePath), '.pdf');

        return [
            'applicable' => true,
            'available' => true,
            'message' => null,
            'is_pdf' => $isPdf,
            'inline_url' => Route::has('payments.proof') ? route('payments.proof', ['id' => $paymentId, 'inline' => 1]) : null,
            'download_url' => Route::has('payments.proof') ? route('payments.proof', $paymentId) : null,
            'label' => 'Lihat Bukti Pembayaran',
        ];
    }

    private function snapshot(): array
    {
        $transactions = collect($this->transactionRepository->fetchAll());
        $payments = collect($this->paymentRepository->getAll());
        $invoices = collect($this->invoiceRepository->getAll());
        $students = collect($this->studentRepository->fetchAll());
        $companies = collect($this->companyRepository->fetchAll());
        $accounts = collect($this->accountRepository->fetchAll());
        $payrolls = collect($this->payrollRepository->getAll());
        $users = collect($this->userRepository->fetchAll());
        $employees = collect($this->employeeRepository->fetchAll());

        $totals = AcceptedPaymentCalculator::totalsByInvoice($payments, $invoices->pluck('Invoice_ID')->all());
        $paymentsByInvoice = $payments->groupBy('Invoice_ID');
        $documents = app(FinanceDocumentPresenter::class);
        $invoices = $invoices->map(fn ($invoice) => $documents->invoice($invoice, $paymentsByInvoice->get($invoice['Invoice_ID'] ?? '', collect()), $totals));

        return [
            'transactions' => $transactions,
            'transactions_by_id' => $transactions->keyBy('Transaction_ID'),
            'payments_by_id' => $payments->keyBy('Payment_ID'),
            'invoices_by_id' => $invoices->keyBy('Invoice_ID'),
            'students_by_id' => $students->keyBy('Student_ID'),
            'companies_by_id' => $companies->keyBy('Company_ID'),
            'accounts_by_id' => $this->accountIndex($accounts),
            'payrolls_by_id' => $payrolls->keyBy('Payroll_ID'),
            'users_by_id' => $users->keyBy('User_ID'),
            'employees_by_id' => $employees->keyBy('Employee_ID'),
        ];
    }

    private function present(array $transaction, array $snapshot): array
    {
        $transactionId = trim((string) ($transaction['Transaction_ID'] ?? ''));
        $referenceType = trim((string) ($transaction['Reference_Type'] ?? ''));
        $referenceId = trim((string) ($transaction['Reference_ID'] ?? ''));
        $type = $this->canonicalTransactionType($transaction['Type'] ?? '');
        $payment = null;
        $invoice = null;
        $payroll = null;

        if (in_array(strtolower($referenceType), ['payment', 'paymentreversal'], true) && $referenceId !== '') {
            $payment = $this->row($snapshot['payments_by_id']->get($referenceId));
            if (!$payment) {
                Log::warning('finance.transaction_missing_payment_reference', [
                    'transaction_id' => $transactionId,
                    'payment_id' => $referenceId,
                ]);
            }
        }

        $invoiceId = trim((string) ($payment['Invoice_ID'] ?? ''));
        if ($invoiceId === '' && strcasecmp($referenceType, 'Invoice') === 0) {
            $invoiceId = $referenceId;
        }
        if ($invoiceId !== '') {
            $invoice = $this->row($snapshot['invoices_by_id']->get($invoiceId));
            if (!$invoice) {
                Log::warning('finance.transaction_missing_invoice_reference', [
                    'transaction_id' => $transactionId,
                    'invoice_id' => $invoiceId,
                ]);
            } elseif ($payment) {
                app(FinanceDocumentPresenter::class)->assertInvoiceOwner($payment, $invoice);
            }
        }

        if (strcasecmp($referenceType, 'Payroll') === 0 && $referenceId !== '') {
            $payroll = $this->row($snapshot['payrolls_by_id']->get($referenceId));
        }

        $integrity = $this->sourceIntegrity($referenceType, $referenceId, $payment, $invoice, $payroll);
        $party = $integrity['valid']
            ? $this->party($referenceType, $payment, $invoice, $payroll, $transaction, $snapshot)
            : null;
        $source = $this->source($transactionId, $referenceType, $referenceId, $payment, $invoice, $payroll, $integrity);
        $accountId = trim((string) ($transaction['Account_ID'] ?? $transaction['Account_Code'] ?? ''));
        $accountLabel = $accountId !== ''
            ? HumanReadableResolver::accountName($accountId, $snapshot['accounts_by_id'])
            : 'Akun tidak ditemukan';
        $paymentData = $payment ? $this->payment($payment, $invoice, $snapshot) : null;
        $invoiceData = $invoice ? $this->invoice($invoice) : null;
        $payrollData = $payroll ? $this->payroll($payroll) : null;
        $reversal = $this->reversal($transaction, $snapshot);

        return [
            'raw' => $transaction,
            'transaction_id' => $transactionId,
            'title' => $source['label'],
            'description' => $this->value($transaction['Description'] ?? '', $source['description']),
            'date_label' => $this->dateLabel($transaction['Transaction_Date'] ?? ''),
            'created_label' => $this->dateTimeLabel($transaction['Created_At'] ?? ''),
            'updated_label' => $this->dateTimeLabel($transaction['Updated_At'] ?? ''),
            'amount' => $this->amount($transaction['Amount'] ?? 0),
            'amount_label' => $this->money($transaction['Amount'] ?? 0),
            'type' => $type,
            'type_label' => $this->transactionTypeLabel($type, $referenceType),
            'type_color' => strcasecmp($type, 'Income') === 0 ? 'green' : 'red',
            'category_label' => $this->categoryLabel($transaction['Category'] ?? '', $referenceType, $payment),
            'account' => [
                'id' => $accountId,
                'label' => $accountLabel,
                'missing' => $accountLabel === 'Akun tidak ditemukan',
            ],
            'source' => $source,
            'party' => $party,
            'payment' => $paymentData,
            'invoice' => $invoiceData,
            'payroll' => $payrollData,
            'evidence' => $this->evidence($referenceType, $payment, $integrity),
            'audit' => [
                'created_by' => HumanReadableResolver::userName($transaction['Created_By'] ?? '', $snapshot['users_by_id']),
                'updated_by' => HumanReadableResolver::userName($transaction['Updated_By'] ?? '', $snapshot['users_by_id']),
                'created_at' => $this->dateTimeLabel($transaction['Created_At'] ?? ''),
                'updated_at' => $this->dateTimeLabel($transaction['Updated_At'] ?? ''),
            ],
            'reversal' => $reversal,
            'integrity' => $integrity,
            'integrity_warning' => $integrity['message'],
            'legacy_warning' => $integrity['message'],
            'reference_label' => $this->referenceLabel($referenceType, $referenceId),
        ];
    }

    private function payment(array $payment, ?array $invoice, array $snapshot): array
    {
        $verifiedBy = trim((string) ($payment['Verified_By'] ?? $payment['Approved_By'] ?? ''));

        return [
            'id' => trim((string) ($payment['Payment_ID'] ?? '')),
            'invoice_id' => trim((string) ($payment['Invoice_ID'] ?? '')),
            'amount_label' => $this->money($payment['Amount_Paid'] ?? $payment['Amount'] ?? 0),
            'method_label' => $this->paymentMethodLabel($payment['Payment_Method'] ?? ''),
            'date_label' => $this->dateLabel($payment['Payment_Date'] ?? $payment['Transfer_Date'] ?? ''),
            'receipt_number' => $this->value($payment['Receipt_Number'] ?? '', 'Nomor kwitansi tidak tersedia'),
            'reference_number' => $this->nullableValue($payment['Reference_Number'] ?? ''),
            'notes' => $this->nullableValue($payment['Notes'] ?? ''),
            'status_label' => $this->statusLabel($payment['Status'] ?? 'Waiting Verification'),
            'verified_by' => $verifiedBy !== ''
                ? HumanReadableResolver::userName($verifiedBy, $snapshot['users_by_id'])
                : 'Belum diverifikasi',
            'verified_at' => $this->dateTimeLabel($payment['Verified_At'] ?? $payment['Approved_At'] ?? ''),
            'receipt_url' => Route::has('payments.receipt') ? route('payments.receipt', $payment['Payment_ID']) : null,
            'is_self_service' => $this->isSelfServicePayment($payment),
            'invoice_missing' => trim((string) ($payment['Invoice_ID'] ?? '')) !== '' && !$invoice,
            'invoice_optional' => trim((string) ($payment['Invoice_ID'] ?? '')) === '' && $this->isSelfServicePayment($payment),
        ];
    }

    private function invoice(array $invoice): array
    {
        return [
            'id' => trim((string) ($invoice['Invoice_ID'] ?? '')),
            'number' => $this->value($invoice['Invoice_Number'] ?? $invoice['Invoice_ID'] ?? '', 'Nomor invoice tidak tersedia'),
            'amount_label' => $this->money($invoice['Grand_Total'] ?? $invoice['Amount'] ?? 0),
            'remaining_label' => $this->money($invoice['Remaining_Amount'] ?? 0),
            'status_label' => $this->statusLabel($invoice['Display_Status'] ?? $invoice['Status'] ?? ''),
            'due_date_label' => $this->dateLabel($invoice['Due_Date'] ?? ''),
            'category' => $this->value($invoice['Category'] ?? '', 'Tagihan'),
            'period' => $this->nullableValue($invoice['Period'] ?? ''),
            'description' => $this->nullableValue($invoice['Description'] ?? ''),
        ];
    }

    private function payroll(array $payroll): array
    {
        return [
            'id' => trim((string) ($payroll['Payroll_ID'] ?? '')),
            'number' => $this->firstValue([
                $payroll['Document_Number'] ?? null,
                $payroll['Payroll_ID'] ?? null,
            ], 'Nomor payroll tidak tersedia'),
            'period' => $this->value($payroll['Payroll_Period'] ?? $payroll['Period'] ?? '', 'Periode tidak tersedia'),
            'base_salary_label' => $this->money($payroll['Base_Salary'] ?? 0),
            'allowances_label' => $this->money($payroll['Total_Allowances'] ?? 0),
            'deductions_label' => $this->money($payroll['Total_Deductions'] ?? 0),
            'net_salary_label' => $this->money($payroll['Net_Salary'] ?? 0),
            'status_label' => $this->statusLabel($payroll['Status'] ?? ''),
            'paid_at' => $this->dateLabel($payroll['Paid_Date'] ?? $payroll['Paid_At'] ?? ''),
        ];
    }

    private function source(
        string $transactionId,
        string $referenceType,
        string $referenceId,
        ?array $payment,
        ?array $invoice,
        ?array $payroll,
        array $integrity
    ): array
    {
        $lower = strtolower($referenceType);
        if ($payment) {
            $selfService = $this->isSelfServicePayment($payment);
            $officialReference = $this->firstValue([
                $payment['Receipt_Number'] ?? null,
                $payment['Payment_ID'] ?? null,
            ], 'Referensi pembayaran tidak tersedia');

            return [
                'applicable' => true,
                'available' => true,
                'type' => $referenceType,
                'type_label' => $lower === 'paymentreversal' ? 'Pembalikan Pembayaran' : ($selfService ? 'Pembayaran Mandiri Siswa' : 'Pembayaran'),
                'id' => $referenceId,
                'label' => $lower === 'paymentreversal'
                    ? 'Pembalikan ' . $officialReference
                    : $officialReference,
                'official_reference' => $officialReference,
                'official_reference_label' => 'Nomor Referensi Resmi',
                'official_reference_applicable' => true,
                'status_label' => $this->statusLabel($payment['Status'] ?? ''),
                'status_applicable' => true,
                'description' => empty($payment['Invoice_ID']) ? 'Pembayaran tanpa tagihan' : 'Transaksi pembayaran untuk tagihan',
                'url' => Route::has('payments.show') ? route('payments.show', $payment['Payment_ID']) : null,
            ];
        }

        if ($invoice) {
            $officialReference = $this->firstValue([
                $invoice['Invoice_Number'] ?? null,
                $invoice['Invoice_ID'] ?? null,
            ], 'Referensi invoice tidak tersedia');

            return [
                'applicable' => true,
                'available' => true,
                'type' => $referenceType,
                'type_label' => 'Tagihan',
                'id' => $referenceId,
                'label' => $officialReference,
                'official_reference' => $officialReference,
                'official_reference_label' => 'Nomor Referensi Resmi',
                'official_reference_applicable' => true,
                'status_label' => $this->statusLabel($invoice['Display_Status'] ?? $invoice['Status'] ?? ''),
                'status_applicable' => true,
                'description' => 'Transaksi dari tagihan',
                'url' => Route::has('invoices.show') ? route('invoices.show', $invoice['Invoice_ID']) : null,
            ];
        }

        if ($payroll) {
            $officialReference = $this->firstValue([
                $payroll['Document_Number'] ?? null,
                $payroll['Payroll_ID'] ?? null,
            ], 'Referensi payroll tidak tersedia');

            return [
                'applicable' => true,
                'available' => true,
                'type' => $referenceType,
                'type_label' => 'Penggajian',
                'id' => $referenceId,
                'label' => $officialReference,
                'official_reference' => $officialReference,
                'official_reference_label' => 'Nomor Referensi Resmi',
                'official_reference_applicable' => true,
                'status_label' => $this->statusLabel($payroll['Status'] ?? ''),
                'status_applicable' => true,
                'description' => 'Transaksi payroll',
                'url' => Route::has('payrolls.show') ? route('payrolls.show', $payroll['Payroll_ID']) : null,
            ];
        }

        if (!$integrity['relational']) {
            return [
                'applicable' => true,
                'available' => true,
                'type' => $referenceType,
                'type_label' => $this->referenceTypeLabel($referenceType),
                'id' => $transactionId,
                'label' => $this->referenceTypeLabel($referenceType),
                'official_reference' => $transactionId,
                'official_reference_label' => 'Nomor Referensi Transaksi',
                'official_reference_applicable' => true,
                'status_label' => null,
                'status_applicable' => false,
                'description' => 'Transaksi ledger tanpa sumber relational',
                'url' => null,
            ];
        }

        return [
            'applicable' => true,
            'available' => false,
            'type' => $referenceType,
            'type_label' => $this->referenceTypeLabel($referenceType),
            'id' => $referenceId,
            'label' => $this->referenceTypeLabel($referenceType),
            'official_reference' => null,
            'official_reference_label' => 'Nomor Referensi Resmi',
            'official_reference_applicable' => true,
            'status_label' => null,
            'status_applicable' => true,
            'description' => $integrity['message'],
            'url' => null,
        ];
    }

    private function party(
        string $referenceType,
        ?array $payment,
        ?array $invoice,
        ?array $payroll,
        array $transaction,
        array $snapshot
    ): ?array
    {
        if (!$this->isRelationalReferenceType($referenceType)) {
            return null;
        }

        $owner = $payment ?? $invoice;
        if ($owner && (!empty($owner['Student_ID']) || !empty($owner['Company_ID']))) {
            $party = HumanReadableResolver::financialParty($owner, $snapshot['students_by_id'], $snapshot['companies_by_id']);
            return [
                'applicable' => true,
                'resolved' => empty($party['integrity_warning']),
                'type_label' => $party['type_label'],
                'name' => $party['name'],
                'context' => $party['integrity_warning'] ?? ($party['code'] !== '-' ? $party['code'] : 'Konteks pembayar tidak tersedia'),
            ];
        }
        $employeeId = trim((string) ($payroll['Employee_ID'] ?? $transaction['Employee_ID'] ?? ''));
        if ($employeeId !== '') {
            return [
                'applicable' => true,
                'resolved' => $snapshot['employees_by_id']->has($employeeId),
                'type_label' => 'Pegawai',
                'name' => HumanReadableResolver::employeeName($employeeId, $snapshot['employees_by_id']),
                'context' => $this->value($payroll['Payroll_Period'] ?? '', 'Konteks payroll tidak tersedia'),
            ];
        }

        return [
            'applicable' => true,
            'resolved' => false,
            'type_label' => 'Pihak terkait',
            'name' => 'Identitas pihak terkait tidak tersedia',
            'context' => 'Sumber relational tidak memuat identitas siswa, perusahaan, atau pegawai',
        ];
    }

    private function reversal(array $transaction, array $snapshot): array
    {
        $referenceId = trim((string) ($transaction['Reference_ID'] ?? ''));
        $referenceType = strtolower(trim((string) ($transaction['Reference_Type'] ?? '')));
        if (!in_array($referenceType, ['payment', 'paymentreversal'], true)) {
            return [
                'applicable' => false,
                'has_reversal' => false,
                'is_reversal' => false,
                'message' => null,
                'transaction' => null,
            ];
        }

        if ($referenceId === '') {
            return [
                'applicable' => true,
                'has_reversal' => false,
                'is_reversal' => $referenceType === 'paymentreversal',
                'message' => 'Relasi pembalikan tidak dapat di-resolve karena Reference_ID kosong.',
                'transaction' => null,
            ];
        }

        $targetType = $referenceType === 'payment' ? 'PaymentReversal' : 'Payment';
        $related = collect($snapshot['transactions'])
            ->first(fn ($row) => strcasecmp(trim((string) ($row['Reference_Type'] ?? '')), $targetType) === 0
                && trim((string) ($row['Reference_ID'] ?? '')) === $referenceId
                && strtoupper(trim((string) ($row['Is_Active'] ?? 'TRUE'))) !== 'FALSE');

        if (!$related) {
            return [
                'applicable' => true,
                'has_reversal' => false,
                'is_reversal' => $referenceType === 'paymentreversal',
                'message' => $referenceType === 'paymentreversal'
                    ? 'Transaksi asli tidak ditemukan.'
                    : 'Belum ada pembalikan/koreksi untuk transaksi ini.',
                'transaction' => null,
            ];
        }

        $relatedId = trim((string) ($related['Transaction_ID'] ?? ''));

        return [
            'applicable' => true,
            'has_reversal' => $referenceType === 'payment',
            'is_reversal' => $referenceType === 'paymentreversal',
            'message' => $referenceType === 'payment'
                ? 'Transaksi ini memiliki pembalikan/koreksi.'
                : 'Transaksi ini adalah pembalikan/koreksi dari transaksi asli.',
            'transaction' => [
                'id' => $relatedId,
                'label' => $referenceType === 'payment' ? 'Transaksi koreksi' : 'Transaksi asli',
                'amount_label' => $this->money($related['Amount'] ?? 0),
                'url' => $relatedId !== '' && Route::has('transactions.show') ? route('transactions.show', $relatedId) : null,
            ],
        ];
    }

    private function evidence(string $referenceType, ?array $payment, array $integrity): array
    {
        if (!in_array(strtolower(trim($referenceType)), ['payment', 'paymentreversal'], true)) {
            return [
                'applicable' => false,
                'available' => false,
                'message' => null,
                'is_pdf' => false,
                'inline_url' => null,
                'download_url' => null,
            ];
        }

        if (!$payment) {
            return [
                'applicable' => true,
                'available' => false,
                'message' => 'Bukti pembayaran tidak dapat di-resolve karena sumber pembayaran tidak valid.',
                'is_pdf' => false,
                'inline_url' => null,
                'download_url' => null,
            ];
        }

        return $this->paymentEvidence($payment);
    }

    private function sourceIntegrity(
        string $referenceType,
        string $referenceId,
        ?array $payment,
        ?array $invoice,
        ?array $payroll
    ): array {
        if (!$this->isRelationalReferenceType($referenceType)) {
            return [
                'relational' => false,
                'valid' => true,
                'message' => null,
            ];
        }

        if ($referenceId === '') {
            return [
                'relational' => true,
                'valid' => false,
                'message' => "Reference_ID kosong untuk transaksi {$this->referenceTypeLabel($referenceType)}. Sumber relational tidak dapat di-resolve; data ledger tetap ditampilkan.",
            ];
        }

        $resolved = match (strtolower(trim($referenceType))) {
            'payment', 'paymentreversal' => $payment !== null,
            'invoice' => $invoice !== null,
            'payroll' => $payroll !== null,
            default => false,
        };

        return [
            'relational' => true,
            'valid' => $resolved,
            'message' => $resolved
                ? null
                : "Sumber {$this->referenceTypeLabel($referenceType)} dengan Reference_ID {$referenceId} tidak ditemukan. Data ledger tetap ditampilkan untuk audit.",
        ];
    }

    private function isRelationalReferenceType(string $referenceType): bool
    {
        return in_array(strtolower(trim($referenceType)), ['payment', 'paymentreversal', 'invoice', 'payroll'], true);
    }

    private function accountIndex(Collection $accounts): Collection
    {
        $index = collect();
        foreach ($accounts as $account) {
            foreach (['Account_ID', 'Account_Code'] as $field) {
                $key = trim((string) ($account[$field] ?? ''));
                if ($key !== '') {
                    $index->put($key, $account);
                }
            }
        }

        return $index;
    }

    private function canonicalTransactionType(mixed $type): string
    {
        $value = strtolower(trim((string) $type));
        if (in_array($value, ['income', 'pemasukan', 'masuk', 'revenue', 'pendapatan'], true)) {
            return 'Income';
        }
        if (in_array($value, ['expense', 'pengeluaran', 'keluar', 'cost', 'biaya', 'beban'], true)) {
            return 'Expense';
        }

        return trim((string) $type) ?: 'Other';
    }

    private function transactionTypeLabel(string $type, string $referenceType): string
    {
        if (strcasecmp($referenceType, 'PaymentReversal') === 0) {
            return 'Pembalikan/Koreksi';
        }

        return strcasecmp($type, 'Income') === 0 ? 'Pemasukan' : (strcasecmp($type, 'Expense') === 0 ? 'Pengeluaran' : 'Transaksi');
    }

    private function referenceTypeLabel(string $referenceType): string
    {
        return match (strtolower(trim($referenceType))) {
            'payment' => 'Pembayaran',
            'paymentreversal' => 'Pembalikan Pembayaran',
            'invoice' => 'Tagihan',
            'payroll' => 'Penggajian',
            'adjustment' => 'Penyesuaian',
            'other', '' => 'Transaksi Manual',
            default => 'Sumber tidak dikenal',
        };
    }

    private function categoryLabel(mixed $category, string $referenceType, ?array $payment): string
    {
        $category = trim((string) $category);
        if ($this->isSelfServicePayment($payment ?? [])) {
            return 'Pembayaran Mandiri';
        }

        return $category !== '' ? $category : $this->referenceTypeLabel($referenceType);
    }

    private function referenceLabel(string $referenceType, string $referenceId): string
    {
        return trim($referenceId) === ''
            ? $this->referenceTypeLabel($referenceType)
            : $this->referenceTypeLabel($referenceType) . ' #' . $referenceId;
    }

    private function statusLabel(mixed $status): string
    {
        return IndonesianPresentation::status($status);
    }

    private function paymentMethodLabel(mixed $method): string
    {
        return IndonesianPresentation::paymentMethod($method);
    }

    private function isSelfServicePayment(array $payment): bool
    {
        if ($payment === []) {
            return false;
        }

        return strcasecmp(trim((string) ($payment['Payment_Type'] ?? '')), 'STUDENT_SELF_SERVICE') === 0
            || trim((string) ($payment['Invoice_ID'] ?? '')) === '';
    }

    private function money(mixed $value): string
    {
        return 'Rp ' . number_format($this->amount($value), 0, ',', '.');
    }

    private function amount(mixed $value): float
    {
        try {
            return Money::value($value ?? 0, 'Nominal transaksi');
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function dateLabel(mixed $date): string
    {
        $date = trim((string) $date);
        if ($date === '') {
            return '-';
        }

        try {
            return Carbon::parse($date, config('app.timezone', 'Asia/Jakarta'))->locale('id')->translatedFormat('j F Y');
        } catch (\Throwable) {
            return $date;
        }
    }

    private function dateTimeLabel(mixed $date): string
    {
        $date = trim((string) $date);
        if ($date === '') {
            return '-';
        }

        try {
            return Carbon::parse($date, config('app.timezone', 'Asia/Jakarta'))->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y, H:i') . ' WIB';
        } catch (\Throwable) {
            return $date;
        }
    }

    private function value(mixed $value, string $fallback): string
    {
        return HumanReadableResolver::value($value, $fallback);
    }

    private function firstValue(array $values, string $fallback): string
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return $fallback;
    }

    private function nullableValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function row(mixed $row): ?array
    {
        if (!$row) {
            return null;
        }

        return is_array($row) ? $row : (array) $row;
    }
}
