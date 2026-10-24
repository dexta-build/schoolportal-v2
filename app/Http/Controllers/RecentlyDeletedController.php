<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\RecentlyDeletedService;
use App\Services\RestoreFailed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Recently deleted page and the Undo bar's restore action, for the admin and teacher portals.
 */
class RecentlyDeletedController extends Controller
{
    public function __construct(private readonly RecentlyDeletedService $service) {}

    public function index(Request $request): View
    {
        $portal = $this->portal($request);

        return view('recently-deleted.index', [
            'portal' => $portal,
            'items' => $this->service->itemsFor($request->user(), $portal),
        ]);
    }

    public function restore(Request $request, string $type, int $id): RedirectResponse
    {
        try {
            $message = $this->service->restore($request->user(), $this->portal($request), $type, $id);
        } catch (RestoreFailed $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }

    private function portal(Request $request): string
    {
        return str_starts_with((string) $request->route()?->getName(), 'teacher.') ? 'teacher' : 'admin';
    }
}
