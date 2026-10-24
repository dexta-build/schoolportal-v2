<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentStudent extends Model
{
    use BelongsToTenant;

    protected $table = 'parent_student';

    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'student_id',
        'school_id',
    ];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'student_id' => 'integer',
            'school_id' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
