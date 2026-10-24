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

class Result extends Model
{
    use Auditable, BelongsToTenant, HasFactory, UndoableDeletes;

    protected $fillable = [
        'school_id',
        'student_id',
        'session_id',
        'term_id',
        'class_id',
        'file_url',
        'file_public_id',
        'uploaded_by',
        'approved_by',
        'approved_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'student_id' => 'integer',
            'session_id' => 'integer',
            'term_id' => 'integer',
            'class_id' => 'integer',
            'uploaded_by' => 'integer',
            'approved_by' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
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
     * One result sheet per student per term (unique index). A deleted sheet still holds that place,
     * so a new upload removes it for good first.
     */
    public static function releaseDeletedSlot(int $studentId, int $sessionId, int $termId): void
    {
        static::onlyTrashed()
            ->where('student_id', $studentId)
            ->where('session_id', $sessionId)
            ->where('term_id', $termId)
            ->get()
            ->each->forceDelete();
    }

    public function undoableTitle(): string
    {
        return __('Result sheet: :name', ['name' => $this->student?->name ?? __('student')]).($this->term ? ' ('.$this->term->name.')' : '');
    }

    protected function undoableFileColumns(): array
    {
        return ['file_public_id'];
    }
}
