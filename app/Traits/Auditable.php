<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Add this trait to any model that should be audit-logged.
 * Registers created/updated/deleted event listeners directly (no observer class).
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            static::logAudit($model, 'created', null, $model->getAttributes());
        });

        static::updated(function ($model) {
            $dirty = $model->getDirty();

            // Skip if only timestamps changed (a restore is logged as "restored" below)
            $ignoredKeys = ['updated_at', 'created_at', 'last_login_at', 'last_login_ip', 'deleted_at', 'deleted_by'];
            $meaningfulChanges = array_diff_key($dirty, array_flip($ignoredKeys));

            if (empty($meaningfulChanges)) {
                return;
            }

            $oldValues = array_intersect_key($model->getOriginal(), $dirty);

            // Never log password values
            unset($oldValues['password'], $dirty['password']);

            static::logAudit($model, 'updated', $oldValues, $dirty);
        });

        static::deleted(function ($model) {
            // Removing an already-deleted record for good is logged as "purged" below
            if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting() && $model->getOriginal('deleted_at')) {
                return;
            }

            static::logAudit($model, 'deleted', $model->getOriginal(), null);
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(function ($model) {
                static::logAudit($model, 'restored', null, null);
            });

            static::forceDeleted(function ($model) {
                if ($model->getOriginal('deleted_at')) {
                    static::logAudit($model, 'purged', null, null);
                }
            });
        }
    }

    protected static function logAudit($model, string $action, ?array $oldValues, ?array $newValues): void
    {
        $schoolId = $model->getAttribute('school_id');

        if (! $schoolId) {
            $school = app()->bound('current.school') ? app('current.school') : null;
            $schoolId = $school?->id;
        }

        if (! $schoolId) {
            return;
        }

        $entityType = class_basename($model);
        $actionName = strtolower($entityType).'.'.$action;

        AuditLog::withoutGlobalScopes()->create([
            'school_id' => $schoolId,
            'user_id' => auth()->id(),
            'action' => $actionName,
            'entity_type' => $entityType,
            'entity_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
