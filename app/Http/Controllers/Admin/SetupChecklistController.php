<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SetupChecklistService;
use Illuminate\Http\RedirectResponse;

class SetupChecklistController extends Controller
{
    /**
     * Hide the "Getting started" checklist (for schools that don't need every step).
     */
    public function dismiss(SetupChecklistService $checklist): RedirectResponse
    {
        $checklist->dismiss(app('current.school'));

        return redirect()->route('admin.dashboard')
            ->with('success', __('Checklist hidden. You can find every step in the menu.'));
    }
}
