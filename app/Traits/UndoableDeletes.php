<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\User;
use App\Services\FileUploadService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

/**
 * Deleting keeps the record for RESTORE_DAYS so the delete can be undone (Undo bar, Recently deleted page).
 * The scheduled model:prune removes it for good afterwards, together with its Cloudinary files.
 *
 * The using model lists its file columns in undoableFileColumns() and names itself in undoableTitle().
 */
trait UndoableDeletes
{
    use Prunable, SoftDeletes;

    public const RESTORE_DAYS = 30;

    /**
     * Columns holding Cloudinary public ids that belong only to this record.
     *
     * @return list<string>
     */
    abstract protected function undoableFileColumns(): array;

    /**
     * How the item is named in the Undo bar and on the Recently deleted page.
     */
    abstract public function undoableTitle(): string;

    public static function bootUndoableDeletes(): void
    {
        // Remember who deleted it (a soft delete only writes deleted_at itself)
        static::softDeleted(function (self $model): void {
            $model->deleted_by = auth()->id();
            $model->newQueryWithoutScopes()->whereKey($model->getKey())->update(['deleted_by' => $model->deleted_by]);
        });

        static::restoring(function (self $model): void {
            $model->deleted_by = null;
        });

        // Files go only when the record is removed for good (prune, or forceDelete from a controller)
        static::forceDeleting(function (self $model): void {
            $model->deleteUndoableFiles();
        });
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by')->withoutGlobalScopes();
    }

    public function purgesAt(): ?CarbonInterface
    {
        return $this->deleted_at?->copy()->addDays(self::RESTORE_DAYS);
    }

    public function prunable(): Builder
    {
        return static::withoutGlobalScope('tenant')
            ->onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays(self::RESTORE_DAYS));
    }

    public function deleteUndoableFiles(): void
    {
        foreach ($this->undoableFileColumns() as $column) {
            $publicId = $this->getAttribute($column);
            if (! $publicId) {
                continue;
            }

            try {
                app(FileUploadService::class)->deleteAnyType($publicId);
            } catch (\Throwable $e) {
                Log::warning('Could not delete file from Cloudinary', [
                    'model' => static::class,
                    'id' => $this->getKey(),
                    'public_id' => $publicId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
