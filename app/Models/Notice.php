<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\FileUploadService;
use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use App\Traits\HasCloudinaryImages;
use App\Traits\UndoableDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notice extends Model
{
    use Auditable, BelongsToTenant, HasCloudinaryImages, HasFactory, UndoableDeletes;

    protected $fillable = [
        'school_id',
        'title',
        'content',
        'image_url',
        'image_public_id',
        'file_url',
        'file_public_id',
        'file_name',
        'target_levels',
        'target_roles',
        'target_classes',
        'is_published',
        'status',
        'published_at',
        'expires_at',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_by' => 'integer',
            'school_id' => 'integer',
            'created_by' => 'integer',
            'target_levels' => 'array',
            'target_roles' => 'array',
            'target_classes' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function undoableTitle(): string
    {
        return $this->title;
    }

    protected function undoableFileColumns(): array
    {
        return ['file_public_id', 'image_public_id'];
    }

    /**
     * Whether a student, parent or teacher may read this notice — the same targeting the notice
     * lists apply, so a notice can't be opened by ID by someone it wasn't addressed to.
     */
    public function isVisibleTo(User $user): bool
    {
        if (! $this->is_published || ($this->expires_at !== null && $this->expires_at->lt(now()))) {
            return false;
        }

        $roles = array_filter((array) $this->target_roles);
        if ($roles !== [] && ! in_array($user->role, $roles, true)) {
            return false;
        }

        $levels = array_map('intval', array_filter((array) $this->target_levels));
        $classes = array_map('intval', array_filter((array) $this->target_classes));

        if ($levels === [] && $classes === []) {
            return true;
        }

        // A class/level matches when it is in the targets, or that target list is empty
        $matches = fn (?int $classId, ?int $levelId): bool => ($levels === [] || in_array((int) $levelId, $levels, true))
            && ($classes === [] || in_array((int) $classId, $classes, true));

        return match ($user->role) {
            'student' => ($profile = $user->studentProfile()->with('class:id,level_id')->first())
                && $matches($profile->class_id, $profile->class?->level_id),
            'parent' => StudentProfile::with('class:id,level_id')
                ->whereIn('user_id', $user->children()->pluck('student_id'))
                ->get()
                ->contains(fn (StudentProfile $child): bool => $matches($child->class_id, $child->class?->level_id)),
            // Teachers are addressed by level when a notice is sent
            'teacher' => $levels === [] || in_array((int) $user->level_id, $levels, true)
                || $user->assignedClasses()->whereIn('level_id', $levels)->exists(),
            default => false,
        };
    }

    /**
     * Determine if the attached file is an image based on its name/url.
     */
    public function fileIsImage(): bool
    {
        $name = $this->file_name ?? $this->attributes['file_url'] ?? '';

        return (bool) preg_match('/\.(jpe?g|png|gif|webp|bmp|svg)$/i', $name);
    }

    /**
     * Get the file URL — auto-signs raw (non-image) Cloudinary files
     * so they don't return HTTP 401 on accounts with strict delivery.
     */
    public function getFileUrlAttribute(?string $value): ?string
    {
        if (! $value || ! $this->file_public_id || $this->fileIsImage()) {
            return $value;
        }

        try {
            return FileUploadService::signedRawUrl($this->file_public_id);
        } catch (\Throwable) {
            return $value;
        }
    }

    // ── Cloudinary Image Accessors ──

    /** For small inline thumbnails in dashboard notice cards (displayed ~48px). */
    public function imageThumbnailUrl(): string
    {
        return self::cloudinaryTransform($this->image_url, 'w_128,h_128,c_fill,f_auto,q_auto');
    }

    /** For notice index grid cards displayed in aspect-video containers (~400px wide). */
    public function imageCardUrl(): string
    {
        return self::cloudinaryTransform($this->image_url, 'w_800,h_450,c_fill,f_auto,q_auto');
    }
}
