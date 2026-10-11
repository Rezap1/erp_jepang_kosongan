<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ANNOUNCEMENT_COMMENT', function (Blueprint $table): void {
            if (! Schema::hasColumn('ANNOUNCEMENT_COMMENT', 'Parent_Comment_ID')) {
                $table->string('Parent_Comment_ID', 191)->nullable()->after('User_ID')->index('announcement_comment_parent_idx');
            }
            if (! Schema::hasColumn('ANNOUNCEMENT_COMMENT', 'External_Media_Provider')) {
                $table->string('External_Media_Provider', 32)->nullable()->after('Sticker_ID');
            }
            if (! Schema::hasColumn('ANNOUNCEMENT_COMMENT', 'External_Media_ID')) {
                $table->string('External_Media_ID', 191)->nullable()->after('External_Media_Provider');
            }
            if (! Schema::hasColumn('ANNOUNCEMENT_COMMENT', 'External_Media_URL')) {
                $table->string('External_Media_URL', 1024)->nullable()->after('External_Media_ID');
            }
            if (! Schema::hasColumn('ANNOUNCEMENT_COMMENT', 'External_Media_Preview_URL')) {
                $table->string('External_Media_Preview_URL', 1024)->nullable()->after('External_Media_URL');
            }
            if (! Schema::hasColumn('ANNOUNCEMENT_COMMENT', 'External_Media_Alt')) {
                $table->string('External_Media_Alt', 500)->nullable()->after('External_Media_Preview_URL');
            }
        });

        Schema::table('ANNOUNCEMENT_COMMENT', function (Blueprint $table): void {
            $table->foreign('Parent_Comment_ID', 'announcement_comment_parent_fk')
                ->references('Comment_ID')->on('ANNOUNCEMENT_COMMENT')->nullOnDelete();
        });

        Schema::create('ANNOUNCEMENT_COMMENT_MENTION', function (Blueprint $table): void {
            $table->string('Comment_ID', 191);
            $table->string('Mentioned_User_ID', 191);
            $table->dateTime('Created_At', 6)->nullable();
            $table->primary(['Comment_ID', 'Mentioned_User_ID'], 'announcement_comment_mention_pk');
            $table->index('Mentioned_User_ID', 'announcement_comment_mention_user_idx');
            $table->foreign('Comment_ID', 'announcement_comment_mention_comment_fk')
                ->references('Comment_ID')->on('ANNOUNCEMENT_COMMENT')->cascadeOnDelete();
            $table->foreign('Mentioned_User_ID', 'announcement_comment_mention_user_fk')
                ->references('User_ID')->on('MASTER_USER')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ANNOUNCEMENT_COMMENT_MENTION');

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->restoreSqliteV2CommentTable();

            return;
        }

        Schema::table('ANNOUNCEMENT_COMMENT', function (Blueprint $table): void {
            if (Schema::hasColumn('ANNOUNCEMENT_COMMENT', 'Parent_Comment_ID')) {
                $table->dropForeign('announcement_comment_parent_fk');
                $table->dropIndex('announcement_comment_parent_idx');
            }
        });
        Schema::table('ANNOUNCEMENT_COMMENT', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                'Parent_Comment_ID',
                'External_Media_Provider',
                'External_Media_ID',
                'External_Media_URL',
                'External_Media_Preview_URL',
                'External_Media_Alt',
            ], fn (string $column): bool => Schema::hasColumn('ANNOUNCEMENT_COMMENT', $column)));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function restoreSqliteV2CommentTable(): void
    {
        $columns = [
            'Comment_ID', 'Announcement_ID', 'User_ID', 'Comment_Type', 'Comment_Text',
            'Sticker_ID', 'Created_At', 'Updated_At', 'Deleted_At',
        ];

        Schema::disableForeignKeyConstraints();
        try {
            Schema::create('ANNOUNCEMENT_COMMENT_V2_TEMP', function (Blueprint $table): void {
                $table->string('Comment_ID', 191)->primary();
                $table->string('Announcement_ID', 191);
                $table->string('User_ID', 191);
                $table->string('Comment_Type', 16);
                $table->text('Comment_Text')->nullable();
                $table->string('Sticker_ID', 191)->nullable();
                $table->dateTime('Created_At', 6)->nullable();
                $table->dateTime('Updated_At', 6)->nullable();
                $table->softDeletes('Deleted_At', 6);
                $table->foreign('Announcement_ID')->references('Announcement_ID')->on('MASTER_ANNOUNCEMENT')->restrictOnDelete();
                $table->foreign('User_ID')->references('User_ID')->on('MASTER_USER')->restrictOnDelete();
                $table->foreign('Sticker_ID')->references('Sticker_ID')->on('MASTER_STICKER')->nullOnDelete();
            });
            DB::table('ANNOUNCEMENT_COMMENT_V2_TEMP')->insertUsing(
                $columns,
                DB::table('ANNOUNCEMENT_COMMENT')->select($columns),
            );
            Schema::drop('ANNOUNCEMENT_COMMENT');
            Schema::rename('ANNOUNCEMENT_COMMENT_V2_TEMP', 'ANNOUNCEMENT_COMMENT');
            Schema::table('ANNOUNCEMENT_COMMENT', function (Blueprint $table): void {
                $table->index('Announcement_ID');
                $table->index('User_ID');
                $table->index('Sticker_ID');
                $table->index('Created_At');
                $table->index(['Announcement_ID', 'Deleted_At', 'Created_At'], 'announcement_comment_stream_idx');
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
