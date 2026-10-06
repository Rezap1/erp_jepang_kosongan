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
        foreach (['USER_STICKER_FAVORITE', 'ANNOUNCEMENT_COMMENT', 'MASTER_STICKER', 'MASTER_STICKER_PACK', 'MASTER_ANNOUNCEMENT', 'MASTER_USER'] as $table) {
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
}
