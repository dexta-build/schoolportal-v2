<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Assignment;
use App\Models\Exam;
use App\Models\Notice;
use App\Models\Result;
use App\Models\SchoolAnnouncement;
use App\Models\SchoolClass;
use App\Models\TeacherAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One place for the "deleted, but can be undone for 30 days" rules: what each role may see and restore
 * (Undo bar, Recently deleted page), and what has to be checked before something comes back.
 */
class RecentlyDeletedService
{
    /**
     * type => [model, label, approval-queue action type for teacher submissions]
     */
    public const TYPES = [
        'notice' => [Notice::class, 'Notice', 'post_notice'],
        'assignment' => [Assignment::class, 'Assignment', 'upload_assignment'],
        'exam' => [Exam::class, 'CBT', 'create_exam'],
        'result' => [Result::class, 'Result sheet', 'upload_result'],
        'class' => [SchoolClass::class, 'Class', null],
        'banner' => [SchoolAnnouncement::class, 'Banner announcement', null],
    ];

    /** What each portal can restore */
    private const ROLE_TYPES = [
        'admin' => ['notice', 'assignment', 'exam', 'result', 'class', 'banner'],
        'teacher' => ['notice', 'exam'],
    ];

    /**
     * Every model whose deletes can be undone — the nightly model:prune purges these.
     *
     * @return list<class-string<Model>>
     */
    public static function modelClasses(): array
    {
        return array_values(array_map(fn (array $type) => $type[0], self::TYPES));
    }

    /**
     * Call right after deleting: takes the item out of the approval queue and returns the Undo bar flash.
     *
     * @return array{message: string, url: string}
     */
    public function deleted(Model $model, string $portal): array
    {
        $type = $this->typeOf($model);

        // A deleted submission should not sit in the admin's approval queue; restore() puts it back
        TeacherAction::where('entity_type', $type)
            ->where('entity_id', $model->getKey())
            ->where('status', 'pending')
            ->delete();

        return [
            'message' => __(':type ":title" deleted.', ['type' => __(self::TYPES[$type][1]), 'title' => Str::limit($model->undoableTitle(), 40)]),
            'url' => route("{$portal}.recently-deleted.restore", ['type' => $type, 'id' => $model->getKey()]),
        ];
    }

    /**
     * Deleted items this user may restore, newest first.
     *
     * @return Collection<int, array{type: string, label: string, model: Model}>
     */
    public function itemsFor(User $user, string $portal): Collection
    {
        return collect(self::ROLE_TYPES[$portal])
            ->flatMap(function (string $type) use ($user) {
                $query = $this->trashedQuery($type, $user)->with('deleter:id,name');

                if ($type === 'result') {
                    $query->with(['student:id,name', 'term:id,name']);
                }

                return $query->latest('deleted_at')->limit(200)->get()
                    ->map(fn (Model $model) => ['type' => $type, 'label' => __(self::TYPES[$type][1]), 'model' => $model]);
            })
            ->sortByDesc(fn (array $item) => $item['model']->deleted_at)
            ->values();
    }

    /**
     * Brings an item back. Returns a success message, or throws RestoreFailed with a message for the user.
     */
    public function restore(User $user, string $portal, string $type, int $id): string
    {
        abort_unless(in_array($type, self::ROLE_TYPES[$portal] ?? [], true), 404);

        /** @var Model|null $model */
        $model = $this->trashedQuery($type, $user)->find($id);

        if (! $model) {
            throw new RestoreFailed(__('This item can no longer be restored. Deleted items are kept for :days days.', ['days' => Notice::RESTORE_DAYS]));
        }

        $this->ensureNothingTookItsPlace($type, $model);

        DB::transaction(function () use ($model, $type): void {
            $model->restore();

            // Still waiting for approval: back into the admin's queue
            [, , $actionType] = self::TYPES[$type];
            $attributes = $model->getAttributes();
            $creatorId = $attributes['created_by'] ?? $attributes['uploaded_by'] ?? null;

            if ($actionType && ($attributes['status'] ?? null) === 'pending' && $creatorId) {
                $creator = User::withoutGlobalScopes()->find($creatorId);
                if ($creator?->role === 'teacher') {
                    TeacherAction::create([
                        'school_id' => $model->getAttribute('school_id'),
                        'teacher_id' => $creator->id,
                        'action_type' => $actionType,
                        'entity_type' => $type,
                        'entity_id' => $model->getKey(),
                        'status' => 'pending',
                    ]);
                }
            }
        });

        return __(':type ":title" restored.', ['type' => __(self::TYPES[$type][1]), 'title' => Str::limit($model->undoableTitle(), 40)]);
    }

    /**
     * Trashed items of a type within the current school that this user may restore.
     */
    private function trashedQuery(string $type, User $user)
    {
        [$class] = self::TYPES[$type];
        $query = $class::onlyTrashed();

        // Banners are not tenant-scoped by a global scope
        if ($type === 'banner') {
            $query->where('school_id', $user->school_id);
        }

        // Notices belong to their author (same rule as editing and deleting them); teachers only see their own CBT
        if ($type === 'notice' || ($type === 'exam' && $user->role === 'teacher')) {
            $query->where('created_by', $user->id);
        }

        return $query;
    }

    /**
     * Some records are one-per-slot (unique index): something new may have taken the place of the deleted one.
     */
    private function ensureNothingTookItsPlace(string $type, Model $model): void
    {
        $message = match ($type) {
            'assignment' => Assignment::claimWeek($model->class_id, $model->session_id, $model->term_id, $model->week_number, $model->getKey())
                ? null
                : __('This class now has another assignment for week :week. Delete that one first.', ['week' => $model->week_number]),
            'result' => Result::where('student_id', $model->student_id)->where('session_id', $model->session_id)->where('term_id', $model->term_id)->exists()
                ? __('This student already has a new result sheet for that term. Delete it first to bring this one back.')
                : null,
            'class' => SchoolClass::where('level_id', $model->level_id)->where('slug', $model->slug)->exists()
                ? __('Another class is now called ":name". Rename it first to bring this one back.', ['name' => $model->name])
                : null,
            default => null,
        };

        if ($message) {
            throw new RestoreFailed($message);
        }
    }

    private function typeOf(Model $model): string
    {
        foreach (self::TYPES as $type => [$class]) {
            if ($model instanceof $class) {
                return $type;
            }
        }

        throw new \InvalidArgumentException(get_class($model).' cannot be restored.');
    }
}
