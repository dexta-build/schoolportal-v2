<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\UndoableDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolAnnouncement extends Model
{
    use UndoableDeletes;

    protected $fillable = [
        'school_id',
        'title',
        'content',
        'priority',
        'target_roles',
        'is_active',
        'starts_at',
        'expires_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'created_by' => 'integer',
            'target_roles' => 'array',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dismissals(): HasMany
    {
        return $this->hasMany(SchoolAnnouncementDismissal::class, 'announcement_id');
    }

    public function isDismissedBy(int $userId): bool
    {
        return $this->dismissals()->where('user_id', $userId)->exists();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            });
    }

    public function scopeForRole($query, string $role)
    {
        return $query->where(function ($q) use ($role) {
            $q->whereNull('target_roles')
                ->orWhereJsonContains('target_roles', $role);
        });
    }

    public function undoableTitle(): string
    {
        return $this->title;
    }

    protected function undoableFileColumns(): array
    {
        return [];
    }
}
