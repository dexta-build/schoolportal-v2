<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\ClassSubject;
use App\Models\GradingScale;
use App\Models\ParentStudent;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\ScoreComponent;
use App\Models\StudentProfile;
use App\Models\Term;
use App\Models\User;

/**
 * The "Getting started" checklist on the school admin dashboard: the steps a new school needs,
 * in the order they depend on each other, each ticked off from the school's real data.
 */
class SetupChecklistService
{
    private const DISMISSED_SETTING = 'onboarding.checklist_dismissed';

    /**
     * @return list<array{key: string, title: string, description: string, done: bool, url: string, action: string}>
     */
    public function steps(School $school): array
    {
        $id = $school->id;

        return [
            [
                'key' => 'classes',
                'title' => __('Add your classes'),
                'description' => __('For example Nursery 1, Primary 3 or JSS 2.'),
                'done' => SchoolClass::where('school_id', $id)->where('is_active', true)->exists(),
                'url' => route('admin.classes.index'),
                'action' => __('Add classes'),
            ],
            [
                'key' => 'subjects',
                'title' => __('Add subjects and give them to classes'),
                'description' => __('Each class needs its subjects before scores can be entered.'),
                'done' => ClassSubject::where('school_id', $id)->exists(),
                'url' => route('admin.subjects.index'),
                'action' => __('Add subjects'),
            ],
            [
                'key' => 'session',
                'title' => __('Set the current session and term'),
                'description' => __('Scores, CBTs and report cards are saved for the current term.'),
                'done' => AcademicSession::where('school_id', $id)->where('is_current', true)->exists()
                    && Term::where('school_id', $id)->where('is_current', true)->exists(),
                'url' => route('admin.sessions.index'),
                'action' => __('Set session & term'),
            ],
            [
                'key' => 'grading',
                'title' => __('Set up scores and grading'),
                'description' => __('What makes up a score (for example CA 1, CA 2, Exam) and the grades (A, B, C…).'),
                'done' => ScoreComponent::where('school_id', $id)->where('is_active', true)->exists()
                    && GradingScale::where('school_id', $id)->exists(),
                'url' => route('admin.grading.index'),
                'action' => __('Set up grading'),
            ],
            [
                'key' => 'teachers',
                'title' => __('Add teachers and give them their classes'),
                'description' => __('A class teacher can enter scores and manage their class.'),
                'done' => User::where('school_id', $id)->where('role', 'teacher')->exists()
                    && SchoolClass::where('school_id', $id)->whereNotNull('teacher_id')->exists(),
                'url' => route('admin.teachers.index'),
                'action' => __('Add teachers'),
            ],
            [
                'key' => 'students',
                'title' => __('Add your students'),
                'description' => __('Add them one by one, or import a whole list from a spreadsheet.'),
                'done' => StudentProfile::where('school_id', $id)->exists(),
                'url' => route('admin.students.import'),
                'action' => __('Add students'),
            ],
            [
                'key' => 'parents',
                'title' => __('Add parents and link them to their children'),
                'description' => __('Parents can then see results, report cards and notices.'),
                'done' => ParentStudent::where('school_id', $id)->exists(),
                'url' => route('admin.parents.index'),
                'action' => __('Add parents'),
            ],
        ];
    }

    /**
     * Shown until every step is done, unless the school has chosen to hide it.
     */
    public function shouldShow(School $school, array $steps): bool
    {
        return ! (bool) $school->setting(self::DISMISSED_SETTING, false)
            && collect($steps)->contains('done', false);
    }

    public function dismiss(School $school): void
    {
        $settings = $school->settings ?? [];
        data_set($settings, self::DISMISSED_SETTING, true);
        $school->update(['settings' => $settings]);
    }
}
