<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('MASTER_ALUMNI') || Schema::hasColumn('MASTER_ALUMNI', 'Work_Field')) {
            return;
        }

        Schema::table('MASTER_ALUMNI', function (Blueprint $table): void {
            $table->string('Work_Field', 150)->nullable()->after('Pathway');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('MASTER_ALUMNI') || ! Schema::hasColumn('MASTER_ALUMNI', 'Work_Field')) {
            return;
        }

        Schema::table('MASTER_ALUMNI', function (Blueprint $table): void {
            $table->dropColumn('Work_Field');
        });
    }
};
