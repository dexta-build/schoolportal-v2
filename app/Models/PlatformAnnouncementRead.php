<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAnnouncementRead extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'announcement_id',
        'school_id',
        'read_by',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'announcement_id' => 'integer',
            'school_id' => 'integer',
            'read_at' => 'datetime',
        ];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(PlatformAnnouncement::class, 'announcement_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'read_by');
    }
}
