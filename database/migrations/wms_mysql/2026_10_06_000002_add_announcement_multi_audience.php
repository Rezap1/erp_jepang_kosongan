<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('MASTER_ANNOUNCEMENT', function (Blueprint $table): void {
            if (! Schema::hasColumn('MASTER_ANNOUNCEMENT', 'Student_Scope')) {
                $table->string('Student_Scope', 32)->nullable()->after('Target_Class_ID')->index('announcement_student_scope_idx');
            }
        });

        Schema::create('ANNOUNCEMENT_AUDIENCE_ROLE', function (Blueprint $table): void {
            $table->string('Announcement_ID', 191);
            $table->string('Role_ID', 191);
            $table->dateTime('Created_At', 6)->nullable();
            $table->primary(['Announcement_ID', 'Role_ID'], 'announcement_audience_role_pk');
            $table->index('Role_ID', 'announcement_audience_role_lookup_idx');
            $table->foreign('Announcement_ID', 'audience_role_announcement_fk')->references('Announcement_ID')->on('MASTER_ANNOUNCEMENT')->cascadeOnDelete();
            $table->foreign('Role_ID', 'audience_role_master_role_fk')->references('Role_ID')->on('MASTER_ROLE')->restrictOnDelete();
        });

        Schema::create('ANNOUNCEMENT_AUDIENCE_CLASS', function (Blueprint $table): void {
            $table->string('Announcement_ID', 191);
            $table->string('Batch_ID', 191);
            $table->string('Class_ID', 191);
            $table->dateTime('Created_At', 6)->nullable();
            $table->primary(['Announcement_ID', 'Class_ID'], 'announcement_audience_class_pk');
            $table->index(['Batch_ID', 'Class_ID'], 'announcement_audience_batch_class_idx');
            $table->foreign('Announcement_ID', 'audience_class_announcement_fk')->references('Announcement_ID')->on('MASTER_ANNOUNCEMENT')->cascadeOnDelete();
            $table->foreign('Batch_ID', 'audience_class_batch_fk')->references('Batch_ID')->on('MASTER_BATCH')->restrictOnDelete();
            $table->foreign('Class_ID', 'audience_class_master_class_fk')->references('Class_ID')->on('MASTER_CLASS')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ANNOUNCEMENT_AUDIENCE_CLASS');
        Schema::dropIfExists('ANNOUNCEMENT_AUDIENCE_ROLE');

        Schema::table('MASTER_ANNOUNCEMENT', function (Blueprint $table): void {
            if (Schema::hasColumn('MASTER_ANNOUNCEMENT', 'Student_Scope')) {
                $table->dropIndex('announcement_student_scope_idx');
            }
        });
        Schema::table('MASTER_ANNOUNCEMENT', function (Blueprint $table): void {
            if (Schema::hasColumn('MASTER_ANNOUNCEMENT', 'Student_Scope')) {
                $table->dropColumn('Student_Scope');
            }
        });
    }
};
