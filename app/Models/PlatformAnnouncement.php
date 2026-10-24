<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformAnnouncement extends Model
{
    protected $fillable = [
        'title',
        'content',
        'priority',
        'is_active',
        'starts_at',
        'expires_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'created_by' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(PlatformAnnouncementRead::class, 'announcement_id');
    }

    public function isReadBySchool(int $schoolId): bool
    {
        return $this->reads()->where('school_id', $schoolId)->exists();
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
}
