<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AnnouncementV2MigrationTest extends TestCase
{
    public function test_migration_is_additive_and_supports_up_down_up(): void
    {
        foreach (['ANNOUNCEMENT_AUDIENCE_CLASS', 'ANNOUNCEMENT_AUDIENCE_ROLE', 'USER_STICKER_FAVORITE', 'ANNOUNCEMENT_COMMENT', 'MASTER_STICKER', 'MASTER_STICKER_PACK', 'MASTER_ANNOUNCEMENT', 'MASTER_CLASS', 'MASTER_BATCH', 'MASTER_USER', 'MASTER_ROLE'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('MASTER_USER', fn (Blueprint $table) => $table->string('User_ID')->primary());
        Schema::create('MASTER_ANNOUNCEMENT', function (Blueprint $table): void {
            $table->string('Announcement_ID')->primary();
            $table->string('Target_Role')->nullable();
            $table->string('Target_ID')->nullable();
        });
        DB::table('MASTER_ANNOUNCEMENT')->insert([
            'Announcement_ID' => 'ANN-SENTINEL',
            'Target_Role' => 'STUDENT',
            'Target_ID' => 'CLASS-SENTINEL',
        ]);
        $migration = require database_path('migrations/wms_mysql/2026_10_06_000001_add_announcement_v2.php');

        $migration->up();
        $this->assertTrue(Schema::hasColumns('MASTER_ANNOUNCEMENT', ['Audience_Type', 'Target_Batch_ID', 'Target_Class_ID']));
        $this->assertTrue(Schema::hasTable('ANNOUNCEMENT_COMMENT'));
        $this->assertTrue(Schema::hasTable('MASTER_STICKER_PACK'));
        $this->assertTrue(Schema::hasTable('MASTER_STICKER'));
        $this->assertTrue(Schema::hasTable('USER_STICKER_FAVORITE'));
        $this->assertDatabaseHas('MASTER_ANNOUNCEMENT', [
            'Announcement_ID' => 'ANN-SENTINEL',
            'Target_Role' => 'STUDENT',
            'Target_ID' => 'CLASS-SENTINEL',
        ]);

        $migration->down();
        $this->assertFalse(Schema::hasTable('ANNOUNCEMENT_COMMENT'));
        $this->assertFalse(Schema::hasColumn('MASTER_ANNOUNCEMENT', 'Audience_Type'));
        $this->assertDatabaseHas('MASTER_ANNOUNCEMENT', [
            'Announcement_ID' => 'ANN-SENTINEL',
            'Target_Role' => 'STUDENT',
            'Target_ID' => 'CLASS-SENTINEL',
        ]);

        $migration->up();
        $this->assertTrue(Schema::hasTable('ANNOUNCEMENT_COMMENT'));
        $this->assertTrue(Schema::hasColumn('MASTER_ANNOUNCEMENT', 'Audience_Type'));
        $this->assertDatabaseHas('MASTER_ANNOUNCEMENT', [
            'Announcement_ID' => 'ANN-SENTINEL',
            'Target_Role' => 'STUDENT',
            'Target_ID' => 'CLASS-SENTINEL',
        ]);
    }

    public function test_multi_audience_hotfix_is_additive_and_preserves_v2_sentinels_through_up_down_up(): void
    {
        foreach (['ANNOUNCEMENT_AUDIENCE_CLASS', 'ANNOUNCEMENT_AUDIENCE_ROLE', 'USER_STICKER_FAVORITE', 'ANNOUNCEMENT_COMMENT', 'MASTER_STICKER', 'MASTER_STICKER_PACK', 'MASTER_ANNOUNCEMENT', 'MASTER_CLASS', 'MASTER_BATCH', 'MASTER_USER', 'MASTER_ROLE'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('MASTER_ROLE', function (Blueprint $table): void {
            $table->string('Role_ID')->primary();
            $table->string('Role_Name');
        });
        Schema::create('MASTER_USER', fn (Blueprint $table) => $table->string('User_ID')->primary());
        Schema::create('MASTER_BATCH', function (Blueprint $table): void {
            $table->string('Batch_ID')->primary();
            $table->string('Batch_Name');
        });
        Schema::create('MASTER_CLASS', function (Blueprint $table): void {
            $table->string('Class_ID')->primary();
            $table->string('Batch_ID');
        });
        Schema::create('MASTER_ANNOUNCEMENT', function (Blueprint $table): void {
            $table->string('Announcement_ID')->primary();
            $table->string('Target_Role')->nullable();
            $table->string('Target_ID')->nullable();
        });

        $baseline = require database_path('migrations/wms_mysql/2026_10_06_000001_add_announcement_v2.php');
        $baseline->up();
        DB::table('MASTER_ROLE')->insert(['Role_ID' => 'ROLE-STUDENT', 'Role_Name' => 'STUDENT']);
        DB::table('MASTER_USER')->insert(['User_ID' => 'USR-SENTINEL']);
        DB::table('MASTER_BATCH')->insert(['Batch_ID' => 'BAT-SENTINEL', 'Batch_Name' => 'Batch Sentinel']);
        DB::table('MASTER_CLASS')->insert(['Class_ID' => 'CLS-SENTINEL', 'Batch_ID' => 'BAT-SENTINEL']);
        DB::table('MASTER_ANNOUNCEMENT')->insert([
            'Announcement_ID' => 'ANN-SENTINEL', 'Audience_Type' => 'ROLE',
            'Target_Role' => 'STUDENT', 'Target_Batch_ID' => 'BAT-SENTINEL', 'Target_Class_ID' => 'CLS-SENTINEL',
        ]);
        DB::table('MASTER_STICKER_PACK')->insert(['Sticker_Pack_ID' => 'PACK-SENTINEL', 'Name' => 'Pack Sentinel']);
        DB::table('MASTER_STICKER')->insert([
            'Sticker_ID' => 'STK-SENTINEL', 'Sticker_Pack_ID' => 'PACK-SENTINEL', 'Name' => 'Sticker Sentinel',
            'File_Path' => 'stickers/sentinel.webp', 'Mime_Type' => 'image/webp', 'File_Size' => 100,
        ]);
        DB::table('ANNOUNCEMENT_COMMENT')->insert([
            'Comment_ID' => 'COM-SENTINEL', 'Announcement_ID' => 'ANN-SENTINEL', 'User_ID' => 'USR-SENTINEL',
            'Comment_Type' => 'STICKER', 'Sticker_ID' => 'STK-SENTINEL',
        ]);
        DB::table('USER_STICKER_FAVORITE')->insert([
            'User_ID' => 'USR-SENTINEL', 'Sticker_ID' => 'STK-SENTINEL',
        ]);

        $hotfix = require database_path('migrations/wms_mysql/2026_10_06_000002_add_announcement_multi_audience.php');
        $hotfix->up();
        $this->assertTrue(Schema::hasColumn('MASTER_ANNOUNCEMENT', 'Student_Scope'));
        $this->assertTrue(Schema::hasTable('ANNOUNCEMENT_AUDIENCE_ROLE'));
        $this->assertTrue(Schema::hasTable('ANNOUNCEMENT_AUDIENCE_CLASS'));

        DB::table('ANNOUNCEMENT_AUDIENCE_ROLE')->insert(['Announcement_ID' => 'ANN-SENTINEL', 'Role_ID' => 'ROLE-STUDENT']);
        DB::table('ANNOUNCEMENT_AUDIENCE_CLASS')->insert([
            'Announcement_ID' => 'ANN-SENTINEL', 'Batch_ID' => 'BAT-SENTINEL', 'Class_ID' => 'CLS-SENTINEL',
        ]);

        $hotfix->down();
        $this->assertFalse(Schema::hasColumn('MASTER_ANNOUNCEMENT', 'Student_Scope'));
        $this->assertFalse(Schema::hasTable('ANNOUNCEMENT_AUDIENCE_ROLE'));
        $this->assertFalse(Schema::hasTable('ANNOUNCEMENT_AUDIENCE_CLASS'));
        $this->assertDatabaseHas('MASTER_ANNOUNCEMENT', ['Announcement_ID' => 'ANN-SENTINEL', 'Target_Role' => 'STUDENT']);
        $this->assertDatabaseHas('ANNOUNCEMENT_COMMENT', ['Comment_ID' => 'COM-SENTINEL', 'Sticker_ID' => 'STK-SENTINEL']);
        $this->assertDatabaseHas('MASTER_STICKER', ['Sticker_ID' => 'STK-SENTINEL']);
        $this->assertDatabaseHas('USER_STICKER_FAVORITE', ['User_ID' => 'USR-SENTINEL', 'Sticker_ID' => 'STK-SENTINEL']);

        $hotfix->up();
        $this->assertTrue(Schema::hasColumn('MASTER_ANNOUNCEMENT', 'Student_Scope'));
        $this->assertTrue(Schema::hasTable('ANNOUNCEMENT_AUDIENCE_ROLE'));
        $this->assertTrue(Schema::hasTable('ANNOUNCEMENT_AUDIENCE_CLASS'));
        $this->assertDatabaseHas('MASTER_ANNOUNCEMENT', ['Announcement_ID' => 'ANN-SENTINEL']);
        $this->assertDatabaseHas('ANNOUNCEMENT_COMMENT', ['Comment_ID' => 'COM-SENTINEL']);
        $this->assertDatabaseHas('USER_STICKER_FAVORITE', ['User_ID' => 'USR-SENTINEL', 'Sticker_ID' => 'STK-SENTINEL']);
    }
}
