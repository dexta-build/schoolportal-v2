<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\FileUploadService;
use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use App\Traits\UndoableDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assignment extends Model
{
    use Auditable, BelongsToTenant, HasFactory, UndoableDeletes;

    protected $fillable = [
        'school_id',
        'class_id',
        'session_id',
        'term_id',
        'week_number',
        'title',
        'description',
        'file_url',
        'file_public_id',
        'uploaded_by',
        'approved_by',
        'approved_at',
        'status',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'class_id' => 'integer',
            'session_id' => 'integer',
            'term_id' => 'integer',
            'week_number' => 'integer',
            'uploaded_by' => 'integer',
            'approved_by' => 'integer',
            'approved_at' => 'datetime',
            'due_date' => 'date',
        ];
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Auto-sign raw Cloudinary URLs so they don't return HTTP 401.
     */
    public function getFileUrlAttribute(?string $value): ?string
    {
        if (! $value || ! $this->file_public_id) {
            return $value;
        }

        try {
            return FileUploadService::signedRawUrl($this->file_public_id);
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * Each class has one assignment per week of a term (unique index). Returns false when a live
     * assignment already has the week; otherwise frees it from a deleted one so it can be reused.
     */
    public static function claimWeek(int $classId, int $sessionId, int $termId, int $week, ?int $exceptId = null): bool
    {
        $slot = fn () => static::query()
            ->where('class_id', $classId)
            ->where('session_id', $sessionId)
            ->where('term_id', $termId)
            ->where('week_number', $week)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId));

        if ($slot()->exists()) {
            return false;
        }

        $slot()->onlyTrashed()->get()->each->forceDelete();

        return true;
    }

    public function undoableTitle(): string
    {
        return $this->title ?: __('Week :week assignment', ['week' => $this->week_number]);
    }

    protected function undoableFileColumns(): array
    {
        return ['file_public_id'];
    }
}
