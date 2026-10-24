<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * For routes that notifications link to: when the item has since been deleted, show a short
 * "no longer available" page with a way back, instead of a bare "not found".
 *
 * Usage: Route::get(...)->missing(RemovedItemPage::for('notice', 'student.notices.index'));
 */
class RemovedItemPage
{
    private const LABELS = [
        'notice' => ['This notice is no longer available', 'Back to notices'],
        'result' => ['This result sheet is no longer available', 'Back to results'],
        'exam' => ['This CBT is no longer available', 'Back to CBT'],
    ];

    public static function for(string $type, string $backRoute): Closure
    {
        return fn (Request $request) => self::response($type, $backRoute);
    }

    /**
     * Also for items that still exist but are no longer shown to this user (e.g. a notice unpublished
     * after the notification went out).
     */
    public static function response(string $type, string $backRoute): Response
    {
        [$title, $backLabel] = self::LABELS[$type];

        return response()->view('errors.item-removed', [
            'title' => __($title),
            'backUrl' => route($backRoute),
            'backLabel' => __($backLabel),
        ], 404);
    }
}
