<?php

use App\Support\Migration\WmsMigrationManifest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (WmsMigrationManifest::contracts() as $contract) {
            if (Schema::hasTable($contract['table'])) {
                continue;
            }

            Schema::create($contract['table'], function (Blueprint $table) use ($contract): void {
                if ($contract['preserve_duplicate_ids']) {
                    $table->bigIncrements('_migration_row_id');
                    $table->unsignedBigInteger('_source_row_number')->unique();
                }

                foreach ($contract['headers'] as $column) {
                    $definition = match (WmsMigrationManifest::columnType($column)) {
                        'identifier' => $table->string($column, 191),
                        'short_string' => $table->string($column, 512),
                        'work_field' => $table->string($column, 150),
                        'decimal' => $table->decimal($column, 20, 4),
                        'integer' => $table->bigInteger($column),
                        'boolean' => $table->boolean($column),
                        'date' => $table->date($column),
                        'datetime' => $table->dateTime($column, 6),
                        'time' => $table->time($column, 6),
                        default => $table->longText($column),
                    };

                    if (! $contract['preserve_duplicate_ids'] && $column === $contract['primary_key']) {
                        $definition->primary();
                    } else {
                        $definition->nullable();
                    }
                }

                foreach (WmsMigrationManifest::indexedColumns($contract) as $column) {
                    $table->index($column, 'wms_'.substr(hash('sha256', $contract['table'].'.'.$column), 0, 16));
                }

                if ($contract['preserve_duplicate_ids']) {
                    $table->index($contract['primary_key'], 'wms_'.substr(hash('sha256', $contract['table'].'.canonical'), 0, 16));
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(WmsMigrationManifest::contracts()) as $contract) {
            Schema::dropIfExists($contract['table']);
        }
    }
};
