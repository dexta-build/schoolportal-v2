<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ReportCardConfig extends Model
{
    use BelongsToTenant;

    protected $table = 'report_card_config';

    protected $fillable = [
        'school_id',
        'psychomotor_traits',
        'affective_traits',
        'trait_rating_scale',
        'comment_presets',
        'show_position',
        'show_class_average',
        'show_subject_teacher',
        'show_grade_summary',
        'require_class_teacher_comment',
        'require_principal_comment',
        'principal_title',
        'principal_signature_url',
        'principal_signature_public_id',
        'school_stamp_url',
        'school_stamp_public_id',
        'enabled_report_types',
        'session_calculation_method',
        'midterm_weight',
        'fullterm_weight',
        'show_term_breakdown_in_session',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'psychomotor_traits' => 'array',
            'affective_traits' => 'array',
            'trait_rating_scale' => 'array',
            'comment_presets' => 'array',
            'show_position' => 'boolean',
            'show_class_average' => 'boolean',
            'show_subject_teacher' => 'boolean',
            'show_grade_summary' => 'boolean',
            'require_class_teacher_comment' => 'boolean',
            'require_principal_comment' => 'boolean',
            'enabled_report_types' => 'array',
            'midterm_weight' => 'decimal:2',
            'fullterm_weight' => 'decimal:2',
            'show_term_breakdown_in_session' => 'boolean',
        ];
    }
}
