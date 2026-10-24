<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassSubject extends Model
{
    use BelongsToTenant;

    protected $table = 'class_subject';

    public $timestamps = false;

    protected $fillable = [
        'school_id',
        'class_id',
        'subject_id',
        'teacher_id',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'class_id' => 'integer',
            'subject_id' => 'integer',
            'teacher_id' => 'integer',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
