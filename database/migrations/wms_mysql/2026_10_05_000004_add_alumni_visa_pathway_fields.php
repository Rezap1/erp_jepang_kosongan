<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('MASTER_ALUMNI')) {
            return;
        }

        Schema::table('MASTER_ALUMNI', function (Blueprint $table): void {
            if (! Schema::hasColumn('MASTER_ALUMNI', 'Visa_Type')) {
                $table->string('Visa_Type', 100)->nullable()->after('Visa_Number');
            }
            if (! Schema::hasColumn('MASTER_ALUMNI', 'Pathway')) {
                $table->string('Pathway', 32)->nullable()->after('Program_ID');
                $table->index('Pathway', 'wms_alumni_pathway_lookup');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('MASTER_ALUMNI')) {
            return;
        }

        Schema::table('MASTER_ALUMNI', function (Blueprint $table): void {
            if (Schema::hasColumn('MASTER_ALUMNI', 'Pathway')) {
                $table->dropIndex('wms_alumni_pathway_lookup');
                $table->dropColumn('Pathway');
            }
            if (Schema::hasColumn('MASTER_ALUMNI', 'Visa_Type')) {
                $table->dropColumn('Visa_Type');
            }
        });
    }
};
