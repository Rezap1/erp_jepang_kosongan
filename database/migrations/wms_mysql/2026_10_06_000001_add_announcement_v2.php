<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('MASTER_ANNOUNCEMENT', function (Blueprint $table): void {
            if (! Schema::hasColumn('MASTER_ANNOUNCEMENT', 'Audience_Type')) {
                $table->string('Audience_Type', 20)->nullable()->after('Target_ID');
            }
            if (! Schema::hasColumn('MASTER_ANNOUNCEMENT', 'Target_Batch_ID')) {
                $table->string('Target_Batch_ID', 191)->nullable()->after('Audience_Type')->index('announcement_target_batch_idx');
            }
            if (! Schema::hasColumn('MASTER_ANNOUNCEMENT', 'Target_Class_ID')) {
                $table->string('Target_Class_ID', 191)->nullable()->after('Target_Batch_ID')->index('announcement_target_class_idx');
            }
        });

        Schema::create('MASTER_STICKER_PACK', function (Blueprint $table): void {
            $table->string('Sticker_Pack_ID', 191)->primary();
            $table->string('Name', 120);
            $table->boolean('Is_Active')->default(true)->index();
            $table->unsignedInteger('Sort_Order')->default(0)->index();
            $table->dateTime('Created_At', 6)->nullable();
            $table->dateTime('Updated_At', 6)->nullable();
            $table->string('Created_By', 191)->nullable();
            $table->string('Updated_By', 191)->nullable();
        });

        Schema::create('MASTER_STICKER', function (Blueprint $table): void {
            $table->string('Sticker_ID', 191)->primary();
            $table->string('Sticker_Pack_ID', 191)->index();
            $table->string('Name', 120);
            $table->string('File_Path', 512);
            $table->string('Mime_Type', 64);
            $table->unsignedBigInteger('File_Size');
            $table->unsignedInteger('Width')->nullable();
            $table->unsignedInteger('Height')->nullable();
            $table->boolean('Is_Active')->default(true)->index();
            $table->unsignedInteger('Sort_Order')->default(0)->index();
            $table->dateTime('Created_At', 6)->nullable();
            $table->dateTime('Updated_At', 6)->nullable();
            $table->string('Created_By', 191)->nullable();
            $table->string('Updated_By', 191)->nullable();
            $table->foreign('Sticker_Pack_ID', 'sticker_pack_fk')->references('Sticker_Pack_ID')->on('MASTER_STICKER_PACK')->restrictOnDelete();
        });

        Schema::create('ANNOUNCEMENT_COMMENT', function (Blueprint $table): void {
            $table->string('Comment_ID', 191)->primary();
            $table->string('Announcement_ID', 191)->index();
            $table->string('User_ID', 191)->index();
            $table->string('Comment_Type', 16);
            $table->text('Comment_Text')->nullable();
            $table->string('Sticker_ID', 191)->nullable()->index();
            $table->dateTime('Created_At', 6)->nullable()->index();
            $table->dateTime('Updated_At', 6)->nullable();
            $table->softDeletes('Deleted_At', 6);
            $table->index(['Announcement_ID', 'Deleted_At', 'Created_At'], 'announcement_comment_stream_idx');
            $table->foreign('Announcement_ID', 'comment_announcement_fk')->references('Announcement_ID')->on('MASTER_ANNOUNCEMENT')->restrictOnDelete();
            $table->foreign('User_ID', 'comment_user_fk')->references('User_ID')->on('MASTER_USER')->restrictOnDelete();
            $table->foreign('Sticker_ID', 'comment_sticker_fk')->references('Sticker_ID')->on('MASTER_STICKER')->nullOnDelete();
        });

        Schema::create('USER_STICKER_FAVORITE', function (Blueprint $table): void {
            $table->string('User_ID', 191);
            $table->string('Sticker_ID', 191);
            $table->dateTime('Created_At', 6)->nullable();
            $table->primary(['User_ID', 'Sticker_ID'], 'user_sticker_favorite_pk');
            $table->foreign('User_ID', 'favorite_user_fk')->references('User_ID')->on('MASTER_USER')->cascadeOnDelete();
            $table->foreign('Sticker_ID', 'favorite_sticker_fk')->references('Sticker_ID')->on('MASTER_STICKER')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('USER_STICKER_FAVORITE');
        Schema::dropIfExists('ANNOUNCEMENT_COMMENT');
        Schema::dropIfExists('MASTER_STICKER');
        Schema::dropIfExists('MASTER_STICKER_PACK');

        Schema::table('MASTER_ANNOUNCEMENT', function (Blueprint $table): void {
            if (Schema::hasColumn('MASTER_ANNOUNCEMENT', 'Target_Class_ID')) {
                $table->dropIndex('announcement_target_class_idx');
            }
            if (Schema::hasColumn('MASTER_ANNOUNCEMENT', 'Target_Batch_ID')) {
                $table->dropIndex('announcement_target_batch_idx');
            }
        });
        Schema::table('MASTER_ANNOUNCEMENT', function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['Target_Class_ID', 'Target_Batch_ID', 'Audience_Type'],
                fn (string $column): bool => Schema::hasColumn('MASTER_ANNOUNCEMENT', $column)
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
