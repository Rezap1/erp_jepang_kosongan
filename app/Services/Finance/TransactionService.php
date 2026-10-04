<?php

namespace App\Services\Finance;

use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Interfaces\GoogleSheets\AccountRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Support\Finance\Money;
use App\Support\Finance\PaymentStatus;
use App\Exceptions\FinancialIntegrityException;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\PayrollRepositoryInterface;
use Carbon\Carbon;

class TransactionService
{
    protected $repository;
    protected $accountRepository;
    protected $enterpriseEvent;
    protected $invoiceRepository;
    protected $paymentRepository;
    protected $payrollRepository;

    public function __construct(
        TransactionRepositoryInterface $repository,
        AccountRepositoryInterface $accountRepository,
        EnterpriseEventService $enterpriseEvent,
        ?InvoiceRepositoryInterface $invoiceRepository = null,
        ?PaymentRepositoryInterface $paymentRepository = null,
        ?PayrollRepositoryInterface $payrollRepository = null
    ) {
        $this->repository = $repository;
        $this->accountRepository = $accountRepository;
        $this->enterpriseEvent = $enterpriseEvent;
        $this->invoiceRepository = $invoiceRepository;
        $this->paymentRepository = $paymentRepository;
        $this->payrollRepository = $payrollRepository;
    }

    public function getAll()
    {
        return collect($this->repository->fetchAll())
            ->where('Is_Active', '!=', 'FALSE')
            ->map(function ($transaction) {
                $normalizedType = $this->normalizeTypeOrNull($transaction['Type'] ?? '');
                if ($normalizedType !== null) {
                    $transaction['Type'] = $normalizedType;
                }

                if (isset($transaction['Amount'])) {
                    $transaction['Amount'] = Money::value($transaction['Amount'], 'Nominal transaksi');
                }

                return $transaction;
            })
            ->values();
    }

    public function getById($id)
    {
        return $this->repository->findById($id);
    }

    public function create(array $data)
    {
        $this->assertFinanceMutationActor();
        $data = $this->normalizeTransactionData($data);
        $this->assertAccountReference($data);
        $this->assertPaymentReferenceIntegrity($data);

        if (!in_array(strtolower((string) ($data['Reference_Type'] ?? '')), ['other', ''], true)
            && trim((string) ($data['Reference_ID'] ?? '')) !== '') {
            $referenceType = trim((string) $data['Reference_Type']);
            $existing = collect($this->freshTransactions())
                ->where('Is_Active', '!=', 'FALSE')
                ->first(fn ($row) => strcasecmp((string) ($row['Reference_Type'] ?? ''), $referenceType) === 0
                    && trim((string) ($row['Reference_ID'] ?? '')) === trim((string) $data['Reference_ID']));
            if ($existing) {
                return $existing;
            }
        }

        if (empty($data['Transaction_ID'])) {
            $data['Transaction_ID'] = $this->repository->generateNewId();
        } else {
            $existingId = method_exists($this->repository, 'findByIdFresh')
                ? $this->repository->findByIdFresh($data['Transaction_ID'])
                : $this->repository->findById($data['Transaction_ID']);
            if ($existingId) {
                throw new Exception("Transaction ID {$data['Transaction_ID']} sudah terdaftar.");
            }
        }
        

        $data['Is_Active'] = 'TRUE';
        $data['Created_By'] = \App\Support\ActorIdentity::required();
        $data['Created_At'] = now()->toDateTimeString();
        $data['Updated_By'] = \App\Support\ActorIdentity::required();
        $data['Updated_At'] = now()->toDateTimeString();
        unset($data['_domain_reversal']);

        $res = $this->repository->create($data);
        if (!$res) {
            throw new Exception("Gagal menyimpan transaksi {$data['Transaction_ID']}.");
        }
        $this->repository->clearCache();

        try {
            $this->enterpriseEvent->dispatch('FINANCE', 'CREATE', 'TRANSACTION', $data['Transaction_ID'], \App\Support\ActorIdentity::required(), ['FINANCE'], [], $data);
        } catch (\Throwable $e) {
            Log::error('Transaction side effect dispatch failed after primary persistence', [
                'transaction_id' => $data['Transaction_ID'], 'exception' => get_class($e),
            ]);
        }

        return $res;
    }

    public function update($id, array $data)
    {
        $this->assertFinanceMutationActor();
        $transaction = method_exists($this->repository, 'findByIdFresh')
            ? $this->repository->findByIdFresh($id)
            : $this->repository->findById($id);
        if (!$transaction) {
            throw new Exception("Transaksi tidak ditemukan.");
        }

        if ($this->isPaymentLinked($transaction)) {
            throw new FinancialIntegrityException('Transaksi payment-linked bersifat immutable dan tidak dapat diubah. Gunakan alur reversal/kompensasi.');
        }

        $data = $this->normalizeTransactionData($data, false);
        $candidate = array_merge($transaction, $data);
        $this->assertAccountReference($candidate);
        $this->assertPaymentReferenceIntegrity($candidate);

        $data['Updated_By'] = \App\Support\ActorIdentity::required();
        $data['Updated_At'] = now()->toDateTimeString();
        $res = $this->repository->update($id, $data);
        if (!$res) {
            throw new Exception("Gagal memperbarui transaksi {$id}.");
        }
        $this->repository->clearCache();

        try {
            $this->enterpriseEvent->dispatch('FINANCE', 'UPDATE', 'TRANSACTION', $id, \App\Support\ActorIdentity::required(), ['FINANCE'], [], $data);
        } catch (\Throwable $e) {
            Log::error('Transaction update side effect failed after primary persistence', ['transaction_id' => $id, 'exception' => get_class($e)]);
        }

        return $res;
    }

    private function normalizeTransactionData(array $data, bool $isCreate = true): array
    {
        if (array_key_exists('Type', $data)) {
            $data['Type'] = $this->normalizeType($data['Type']);
        }

        if (array_key_exists('Category', $data)) {
            $data['Category'] = trim((string) $data['Category']);
            if ($data['Category'] === '') {
                throw new Exception('Nama pemasukan/pengeluaran wajib diisi.');
            }
        } elseif ($isCreate) {
            throw new Exception('Nama pemasukan/pengeluaran wajib diisi.');
        }

        foreach (['Account_ID', 'Reference_Type', 'Reference_ID', 'Description'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if (array_key_exists('Amount', $data)) {
            $data['Amount'] = Money::value($data['Amount'], 'Nominal transaksi');
        }

        if ($isCreate && empty($data['Amount'])) {
            throw new FinancialIntegrityException('Nominal transaksi harus lebih besar dari nol.');
        }
        if ($isCreate && empty($data['Transaction_Date'])) {
            throw new FinancialIntegrityException('Tanggal transaksi wajib diisi.');
        }
        if (array_key_exists('Transaction_Date', $data) && $data['Transaction_Date'] !== '') {
            $rawDate = trim((string) $data['Transaction_Date']);
            $date = Carbon::createFromFormat('!Y-m-d', $rawDate, config('app.timezone', 'Asia/Jakarta'));
            $errors = Carbon::getLastErrors();
            if (!$date || (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) || $date->format('Y-m-d') !== $rawDate) {
                throw new FinancialIntegrityException('Tanggal transaksi harus menggunakan format Y-m-d yang valid.');
            }
            $data['Transaction_Date'] = $date->format('Y-m-d');
        }

        return $data;
    }

    private function assertAccountReference(array $data): void
    {
        $accountId = trim((string) ($data['Account_ID'] ?? ''));
        if ($accountId === '') {
            throw new FinancialIntegrityException('Account_ID transaksi wajib diisi.');
        }
        $accountRows = method_exists($this->accountRepository, 'fetchAllFresh')
            ? $this->accountRepository->fetchAllFresh()
            : $this->accountRepository->fetchAll();
        $account = collect($accountRows)->first(fn ($row) =>
            ($row['Account_ID'] ?? '') === $accountId || ($row['Account_Code'] ?? '') === $accountId
        );
        if (!$account || strtoupper(trim((string) ($account['Is_Active'] ?? 'TRUE'))) === 'FALSE') {
            throw new FinancialIntegrityException("Account {$accountId} tidak ditemukan atau tidak aktif.");
        }

        $type = strtolower(trim((string) ($data['Reference_Type'] ?? '')));
        $referenceId = trim((string) ($data['Reference_ID'] ?? ''));
        if ($referenceId === '' && in_array($type, ['payment', 'paymentreversal', 'invoice', 'payroll'], true)) {
            throw new FinancialIntegrityException("Reference_ID wajib diisi untuk Reference_Type {$data['Reference_Type']}.");
        }
        if ($referenceId === '' || $type === 'other') {
            return;
        }
        // Payment references have stricter status, direction and amount
        // invariants enforced by assertPaymentReferenceIntegrity().
        if (in_array($type, ['payment', 'paymentreversal'], true)) {
            return;
        }
        $repo = match ($type) {
            'payment' => $this->paymentRepository,
            'paymentreversal' => $this->paymentRepository,
            'invoice' => $this->invoiceRepository,
            'payroll' => $this->payrollRepository,
            'adjustment' => null,
            default => null,
        };
        if ($type === 'adjustment') {
            return;
        }
        if ($repo === null) {
            throw new FinancialIntegrityException("Reference_Type {$data['Reference_Type']} belum memiliki validator entity.");
        }
        $entity = method_exists($repo, 'findByIdFresh')
            ? $repo->findByIdFresh($referenceId)
            : (method_exists($repo, 'getById') ? $repo->getById($referenceId) : null);
        if (!$entity || strtoupper(trim((string) ($entity['Is_Active'] ?? 'TRUE'))) === 'FALSE') {
            throw new FinancialIntegrityException("Reference {$data['Reference_Type']}:{$referenceId} tidak ditemukan atau tidak aktif.");
        }
    }

    private function normalizeType($type): string
    {
        $value = strtolower(trim((string) $type));

        $incomeAliases = ['income', 'pemasukan', 'masuk', 'revenue', 'pendapatan'];
        $expenseAliases = ['expense', 'pengeluaran', 'keluar', 'cost', 'biaya', 'beban'];

        if (in_array($value, $incomeAliases, true)) {
            return 'Income';
        }

        if (in_array($value, $expenseAliases, true)) {
            return 'Expense';
        }

        throw new Exception('Tipe transaksi harus Pemasukan atau Pengeluaran.');
    }

    private function normalizeTypeOrNull($type): ?string
    {
        try {
            return $this->normalizeType($type);
        } catch (Exception $e) {
            return null;
        }
    }

    public function delete($id)
    {
        $this->assertFinanceMutationActor();
        $transaction = method_exists($this->repository, 'findByIdFresh')
            ? $this->repository->findByIdFresh($id)
            : $this->repository->findById($id);
        if (!$transaction) {
            throw new Exception("Transaksi tidak ditemukan.");
        }

        if ($this->isPaymentLinked($transaction)) {
            throw new FinancialIntegrityException('Transaksi payment-linked bersifat immutable dan tidak dapat dihapus atau dinonaktifkan. Gunakan alur reversal/kompensasi.');
        }

        $actor = \App\Support\ActorIdentity::required();
        // Soft-delete through update so actor/timestamp metadata is retained.
        $res = $this->repository->update($id, [
            'Is_Active' => 'FALSE',
            'Updated_By' => $actor,
            'Updated_At' => now()->toDateTimeString(),
        ]);
        if (!$res) {
            throw new Exception("Gagal membatalkan transaksi {$id}.");
        }
        $this->repository->clearCache();

        try {
            $this->enterpriseEvent->dispatch('FINANCE', 'DELETE', 'TRANSACTION', $id, \App\Support\ActorIdentity::required(), ['FINANCE'], [], []);
        } catch (\Throwable $e) {
            Log::error('Transaction cancellation side effect failed after primary persistence', ['transaction_id' => $id, 'exception' => get_class($e)]);
        }

        return $res;
    }

    private function freshTransactions()
    {
        return method_exists($this->repository, 'fetchAllFresh')
            ? $this->repository->fetchAllFresh()
            : $this->repository->fetchAll();
    }

    private function isPaymentLinked(array $transaction): bool
    {
        return trim((string) ($transaction['Reference_ID'] ?? '')) !== ''
            && in_array(strtolower(trim((string) ($transaction['Reference_Type'] ?? ''))), ['payment', 'paymentreversal'], true);
    }

    private function assertPaymentReferenceIntegrity(array $data): void
    {
        $type = strtolower(trim((string) ($data['Reference_Type'] ?? '')));
        $referenceId = trim((string) ($data['Reference_ID'] ?? ''));
        if (!in_array($type, ['payment', 'paymentreversal'], true) || $referenceId === '') {
            return;
        }
        if (!$this->paymentRepository) {
            throw new FinancialIntegrityException('Payment reference tidak dapat divalidasi.');
        }
        $payment = method_exists($this->paymentRepository, 'getByIdFresh')
            ? $this->paymentRepository->getByIdFresh($referenceId)
            : $this->paymentRepository->getById($referenceId);
        if (!$payment || strtoupper(trim((string) ($payment['Is_Active'] ?? 'TRUE'))) === 'FALSE') {
            throw new FinancialIntegrityException("Payment {$referenceId} tidak ditemukan atau tidak aktif.");
        }
        if ($type === 'payment' && !PaymentStatus::verified($payment['Status'] ?? null)) {
            throw new FinancialIntegrityException("Payment {$referenceId} harus berstatus Verified sebelum menjadi referensi transaksi.");
        }
        if ($type === 'paymentreversal') {
            if (empty($data['_domain_reversal'])) {
                throw new FinancialIntegrityException('PaymentReversal hanya dapat dibuat melalui alur reversal Payment yang tervalidasi.');
            }
            if (!PaymentStatus::verified($payment['Status'] ?? null)
                && !PaymentStatus::is($payment['Status'] ?? null, 'Reversed')) {
                throw new FinancialIntegrityException("Payment {$referenceId} harus berstatus Verified atau Reversed sebelum direversal.");
            }
            $original = collect($this->freshTransactions())
                ->first(fn ($row) => strtoupper(trim((string) ($row['Is_Active'] ?? 'TRUE'))) !== 'FALSE'
                    && strcasecmp(trim((string) ($row['Reference_Type'] ?? '')), 'Payment') === 0
                    && trim((string) ($row['Reference_ID'] ?? '')) === $referenceId);
            if (!$original || strcasecmp(trim((string) ($original['Type'] ?? '')), 'Income') !== 0
                || !Money::equal($original['Amount'] ?? null, $payment['Amount_Paid'] ?? null)) {
                throw new FinancialIntegrityException("Income ledger asli untuk Payment {$referenceId} tidak ditemukan atau nominalnya tidak cocok.");
            }
        }
        $expectedType = $type === 'payment' ? 'Income' : 'Expense';
        if (strcasecmp(trim((string) ($data['Type'] ?? '')), $expectedType) !== 0) {
            throw new FinancialIntegrityException("Transaksi yang mereferensikan {$data['Reference_Type']} harus bertipe {$expectedType}.");
        }
        if (!\App\Support\Finance\Money::equal($data['Amount'] ?? null, $payment['Amount_Paid'] ?? null)) {
            throw new FinancialIntegrityException('Nominal transaksi harus sama persis dengan nominal Payment.');
        }
    }

    private function assertFinanceMutationActor(): void
    {
        \App\Support\ActorIdentity::required();
        $user = auth()->user();
        $role = strtoupper(trim((string) ($user->Role ?? $user->Role_Name ?? '')));
        if ($role === '' && $user && !empty($user->Role_ID)) {
            try {
                $role = strtoupper(trim((string) (app(\App\Services\Core\RoleService::class)
                    ->getRoleById($user->Role_ID)['Role_Name'] ?? '')));
            } catch (\Throwable) {
                $role = '';
            }
        }
        if (!in_array($role, ['FINANCE', 'ADMINISTRATOR', 'MASTER'], true)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Role pengguna tidak diizinkan melakukan mutasi keuangan.');
        }
    }
}
