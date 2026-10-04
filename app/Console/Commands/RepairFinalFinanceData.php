<?php

namespace App\Console\Commands;

use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Services\Core\ActivityLogService;
use App\Support\Finance\Money;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class RepairFinalFinanceData extends Command
{
    protected $signature = 'finance:repair-final-data
        {--apply : Apply the allowlisted repair; without this option the command is read-only}
        {--id=* : Optional allowlisted Transaction_ID subset}';

    protected $description = 'One-time, allowlisted repair for proven manual ledgers and historical test contamination.';

    private const MANUAL_LEDGER_REPAIRS = [
        'TRX-000010' => [
            'Reference_Type' => 'Payment',
            'Type' => 'Expense',
            'Amount' => '924000.0000',
            'Description' => 'Print kosa kata 10set,print minna nihonggo 1 (10 set)',
        ],
        'TRX-000014' => [
            'Reference_Type' => 'Invoice',
            'Type' => 'Expense',
            'Amount' => '300000.0000',
            'Description' => 'WIFI bulanan',
        ],
        'TRX-000015' => [
            'Reference_Type' => 'Invoice',
            'Type' => 'Expense',
            'Amount' => '200000.0000',
            'Description' => 'Iuran sampah dan Security',
        ],
        'TRX-000017' => [
            'Reference_Type' => 'Payment',
            'Type' => 'Expense',
            'Amount' => '230000.0000',
            'Description' => 'Pembayaran 10 pcs lanyard+ID card',
        ],
    ];

    private const TEST_LEDGER_REPAIRS = [
        'TRX-PAY-21210FD45D2475A2D3AE' => [
            'Reference_ID' => 'PAY-65EC45470A14EE664E777171',
            'Amount' => '500000.0000',
        ],
        'TRX-PAY-29F19A281E0C64F89FD2' => [
            'Reference_ID' => 'PAY-EE5A885F6BE06771B8612A08',
            'Amount' => '2500000.0000',
        ],
        'TRX-PAY-2E9247814A67E98A088A' => [
            'Reference_ID' => 'PAY-43A41D4784B3315E934BC8C1',
            'Amount' => '3000000.0000',
        ],
        'TRX-PAY-9BFD4F50DBB05C6A2B96' => [
            'Reference_ID' => 'PAY-5A8EC3B1F1ACE36524D1D1B7',
            'Amount' => '2000000.0000',
        ],
    ];

    public function handle(ActivityLogService $activityLog): int
    {
        try {
            $connectionName = (string) config('wms_runtime.connection');
            $connection = DB::connection($connectionName);
            $this->assertRuntime($connection);
            $ids = $this->requestedIds();
            $before = $this->readRows($connection, $ids);
            $plans = $this->buildPlans($before);
            $mode = $this->option('apply') ? 'apply' : 'dry-run';

            if (!$this->option('apply')) {
                $path = $this->writeReport($mode, $connection, $before, $before, $plans, []);
                $this->line((string) json_encode([
                    'status' => 'DRY_RUN',
                    'database' => $connection->getDatabaseName(),
                    'plans' => $plans,
                    'report' => $path,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                return self::SUCCESS;
            }

            $executed = [];
            $auditRows = [];
            $repairTimestamp = now()->toDateTimeString();
            $connection->transaction(function () use ($connection, $ids, $activityLog, $repairTimestamp, &$executed, &$auditRows): void {
                foreach ($ids as $id) {
                    $locked = (array) $connection->table('FINANCE_TRANSACTION')
                        ->where('Transaction_ID', $id)
                        ->lockForUpdate()
                        ->first();
                    if ($locked === []) {
                        throw new RuntimeException("Transaction {$id} tidak ditemukan saat lock.");
                    }
                    $plan = $this->planFor($locked);
                    if ($plan['operation'] === 'NO_OP_ALREADY_REPAIRED') {
                        $executed[] = $plan;
                        continue;
                    }

                    $updates = $plan['changes'] + [
                        'Updated_By' => 'SYSTEM',
                        'Updated_At' => $repairTimestamp,
                    ];
                    $updated = $connection->table('FINANCE_TRANSACTION')
                        ->where('Transaction_ID', $id)
                        ->update($updates);
                    if ($updated !== 1) {
                        throw new RuntimeException("Update {$id} tidak mengubah tepat satu row.");
                    }

                    $after = (array) $connection->table('FINANCE_TRANSACTION')
                        ->where('Transaction_ID', $id)
                        ->first();
                    $this->assertAfter($id, $after);
                    $logged = $activityLog->logAction(
                        'SYSTEM',
                        'DATA_REPAIR',
                        'FINANCE',
                        'P0 final finance repair; allowlisted and evidence-backed.',
                        '127.0.0.1',
                        $locked,
                        $after,
                        'artisan/finance:repair-final-data',
                        'TRANSACTION',
                        $id,
                    );
                    if (!$logged) {
                        throw new RuntimeException("Audit log {$id} gagal; transaction dibatalkan.");
                    }
                    $executed[] = $plan + ['before' => $locked, 'after' => $after];
                }

                $auditRows = $connection->table('MASTER_AUDIT_LOG')
                    ->whereIn('Reference_ID', $ids)
                    ->where('Action', 'DATA_REPAIR')
                    ->where('Created_At', '>=', $repairTimestamp)
                    ->orderBy('Audit_ID')
                    ->get()
                    ->map(fn ($row) => (array) $row)
                    ->all();
                $changedCount = count(array_filter($executed, fn ($item) => $item['operation'] !== 'NO_OP_ALREADY_REPAIRED'));
                if (count($auditRows) !== $changedCount) {
                    throw new RuntimeException('Jumlah audit row tidak sama dengan jumlah perubahan; transaction dibatalkan.');
                }
            }, 5);

            $repository = app(TransactionRepositoryInterface::class);
            if (method_exists($repository, 'clearCache')) {
                $repository->clearCache();
            }
            $after = $this->readRows($connection, $ids);
            $path = $this->writeReport($mode, $connection, $before, $after, $executed, $auditRows);
            $this->line((string) json_encode([
                'status' => 'APPLIED',
                'database' => $connection->getDatabaseName(),
                'rows' => $executed,
                'audit_rows_created' => count($auditRows),
                'report' => $path,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function assertRuntime(ConnectionInterface $connection): void
    {
        $expectedDatabase = (string) config('wms_runtime.database');
        if ($connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== 'wms_mysql'
            || $expectedDatabase !== 'wms_mysql') {
            throw new RuntimeException('Repair hanya diizinkan pada runtime mysql / wms_mysql yang eksplisit.');
        }
        $directory = storage_path('app/migration-reports');
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('Directory report tidak tersedia atau tidak writable; repair dihentikan.');
        }
    }

    private function requestedIds(): array
    {
        $allowlist = array_merge(array_keys(self::MANUAL_LEDGER_REPAIRS), array_keys(self::TEST_LEDGER_REPAIRS));
        $requested = array_values(array_unique(array_filter(array_map(
            fn ($id) => trim((string) $id),
            (array) $this->option('id')
        ))));
        if ($requested === []) {
            return $allowlist;
        }
        $unknown = array_values(array_diff($requested, $allowlist));
        if ($unknown !== []) {
            throw new RuntimeException('ID di luar allowlist ditolak: '.implode(', ', $unknown));
        }

        return $requested;
    }

    private function readRows(ConnectionInterface $connection, array $ids): array
    {
        $rows = $connection->table('FINANCE_TRANSACTION')
            ->whereIn('Transaction_ID', $ids)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->keyBy('Transaction_ID');
        $missing = array_values(array_diff($ids, $rows->keys()->all()));
        if ($missing !== []) {
            throw new RuntimeException('Target transaction tidak lengkap: '.implode(', ', $missing));
        }

        return array_map(fn ($id) => $rows->get($id), $ids);
    }

    private function buildPlans(array $rows): array
    {
        return array_map(fn ($row) => $this->planFor($row), $rows);
    }

    private function planFor(array $row): array
    {
        $id = trim((string) ($row['Transaction_ID'] ?? ''));
        if (isset(self::MANUAL_LEDGER_REPAIRS[$id])) {
            $expected = self::MANUAL_LEDGER_REPAIRS[$id];
            $immutableExpected = $expected;
            unset($immutableExpected['Reference_Type']);
            $this->assertCommonExpected($id, $row, $immutableExpected);
            if (strcasecmp(trim((string) ($row['Reference_Type'] ?? '')), 'Other') === 0
                && trim((string) ($row['Reference_ID'] ?? '')) === '') {
                if (!$this->isActive($row['Is_Active'] ?? null)) {
                    throw new RuntimeException("Manual ledger {$id} hasil repair tidak lagi aktif.");
                }

                return ['transaction_id' => $id, 'operation' => 'NO_OP_ALREADY_REPAIRED', 'changes' => []];
            }
            $this->assertCommonExpected($id, $row, ['Reference_Type' => $expected['Reference_Type']]);
            if (trim((string) ($row['Reference_ID'] ?? '')) !== '' || !$this->isActive($row['Is_Active'] ?? null)) {
                throw new RuntimeException("State manual ledger {$id} tidak sesuai snapshot yang dibuktikan.");
            }

            return [
                'transaction_id' => $id,
                'operation' => 'CONVERT_TO_OTHER',
                'changes' => ['Reference_Type' => 'Other', 'Reference_ID' => null],
            ];
        }

        if (isset(self::TEST_LEDGER_REPAIRS[$id])) {
            $expected = self::TEST_LEDGER_REPAIRS[$id] + [
                'Reference_Type' => 'Payment',
                'Type' => 'Income',
                'Created_By' => 'USR-FIN',
            ];
            $this->assertCommonExpected($id, $row, $expected);
            if (!$this->isActive($row['Is_Active'] ?? null)) {
                return ['transaction_id' => $id, 'operation' => 'NO_OP_ALREADY_REPAIRED', 'changes' => []];
            }

            return [
                'transaction_id' => $id,
                'operation' => 'SOFT_DELETE_TEST_CONTAMINATION',
                'changes' => ['Is_Active' => false],
            ];
        }

        throw new RuntimeException("ID {$id} di luar allowlist.");
    }

    private function assertCommonExpected(string $id, array $row, array $expected): void
    {
        foreach ($expected as $field => $value) {
            $actual = $row[$field] ?? null;
            $matches = $field === 'Amount'
                ? Money::equal($actual, $value)
                : trim((string) $actual) === trim((string) $value);
            if (!$matches) {
                throw new RuntimeException("Guard {$id}.{$field} gagal; repair dihentikan.");
            }
        }
    }

    private function assertAfter(string $id, array $row): void
    {
        if (isset(self::MANUAL_LEDGER_REPAIRS[$id])) {
            if (strcasecmp(trim((string) ($row['Reference_Type'] ?? '')), 'Other') !== 0
                || trim((string) ($row['Reference_ID'] ?? '')) !== ''
                || !$this->isActive($row['Is_Active'] ?? null)) {
                throw new RuntimeException("Verifikasi AFTER manual ledger {$id} gagal.");
            }

            return;
        }
        if ($this->isActive($row['Is_Active'] ?? null)) {
            throw new RuntimeException("Verifikasi AFTER test ledger {$id} gagal.");
        }
    }

    private function isActive(mixed $value): bool
    {
        return !in_array(strtoupper(trim((string) $value)), ['0', 'FALSE', 'NO', 'N'], true);
    }

    private function writeReport(string $mode, ConnectionInterface $connection, array $before, array $after, array $operations, array $auditRows): string
    {
        $report = [
            'report_type' => 'finance-final-repair-'.$mode,
            'captured_at' => now()->toIso8601String(),
            'runtime' => [
                'connection' => (string) config('wms_runtime.connection'),
                'driver' => $connection->getDriverName(),
                'database' => $connection->getDatabaseName(),
            ],
            'before_sha256' => hash('sha256', (string) json_encode($before, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)),
            'after_sha256' => hash('sha256', (string) json_encode($after, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)),
            'before' => $before,
            'operations' => $operations,
            'after' => $after,
            'audit_rows' => $auditRows,
        ];
        $path = storage_path('app/migration-reports/finance-final-repair-'.$mode.'-'.now()->format('Ymd-His').'.json');
        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false || file_put_contents($path, $json.PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Report repair gagal ditulis.');
        }

        return $path;
    }
}
