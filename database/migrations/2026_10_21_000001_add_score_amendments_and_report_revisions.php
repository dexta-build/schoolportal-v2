<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Controlled corrections for locked scores:
 *  - who locked a class's scores, and when
 *  - score_amendments: every change to a locked score (old → new, reason, who, approval state)
 *  - report cards that are out of date after a score change, and their earlier versions
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_subject_scores', function (Blueprint $table) {
            $table->foreignId('locked_by')->nullable()->after('is_locked')->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable()->after('locked_by');
        });

        Schema::create('score_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_subject_score_id')->nullable()->constrained('student_subject_scores')->nullOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->foreignId('score_component_id')->constrained('score_components')->cascadeOnDelete();
            $table->decimal('old_score', 6, 2)->nullable();
            $table->decimal('new_score', 6, 2);
            $table->text('reason');
            $table->enum('source', ['admin', 'teacher_request', 'cbt']);
            $table->enum('status', ['pending', 'applied', 'rejected'])->default('pending');
            $table->foreignId('source_attempt_id')->nullable()->constrained('exam_attempts')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index(['class_id', 'term_id', 'status']);
        });

        Schema::table('student_term_reports', function (Blueprint $table) {
            $table->boolean('needs_regeneration')->default(false)->after('status');
            $table->unsignedInteger('revision_count')->default(0)->after('needs_regeneration');
            $table->timestamp('revised_at')->nullable()->after('revision_count');
        });

        Schema::create('student_term_report_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_term_report_id')->constrained('student_term_reports')->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('status_at_revision', 30);
            $table->json('subject_scores_snapshot')->nullable();
            $table->decimal('total_weighted_score', 8, 2)->nullable();
            $table->decimal('average_weighted_score', 6, 2)->nullable();
            $table->string('overall_grade', 20)->nullable();
            $table->unsignedInteger('position')->nullable();
            $table->unsignedInteger('out_of')->nullable();
            $table->foreignId('revised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index('student_term_report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_term_report_revisions');

        Schema::table('student_term_reports', function (Blueprint $table) {
            $table->dropColumn(['needs_regeneration', 'revision_count', 'revised_at']);
        });

        Schema::dropIfExists('score_amendments');

        Schema::table('student_subject_scores', function (Blueprint $table) {
            $table->dropForeign(['locked_by']);
            $table->dropColumn(['locked_by', 'locked_at']);
        });
    }
};
