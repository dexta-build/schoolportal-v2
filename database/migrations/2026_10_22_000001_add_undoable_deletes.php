<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleted notices, assignments, CBT, result sheets, classes and banners are kept for 30 days so a
 * delete can be undone (Undo bar, Recently deleted page). model:prune removes them for good after that,
 * with their Cloudinary files.
 */
return new class extends Migration
{
    private const TABLES = ['notices', 'assignments', 'exams', 'results', 'classes', 'school_announcements'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->softDeletes()->index();
                // Who deleted it — plain id, no foreign key: the user may be gone by the time it is purged
                $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropIndex(['deleted_at']);
                $table->dropColumn(['deleted_at', 'deleted_by']);
            });
        }
    }
};
