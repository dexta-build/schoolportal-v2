<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SchoolDataExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A school admin downloads a full copy of their school's records (ZIP of CSVs) as their own backup.
 * Reading only; each download is recorded in the audit log.
 */
class DataExportController extends Controller
{
    public function __invoke(Request $request, SchoolDataExportService $exporter): BinaryFileResponse|RedirectResponse
    {
        // A super admin viewing the school would be recorded as the school's admin; they export from the school page
        if ($request->session()->has('impersonating_original_id')) {
            return back()->with('error', __('Data exports are not available while viewing a school. Use the export on the school\'s page in the platform admin.'));
        }

        @set_time_limit(300);

        $path = $exporter->export(app('current.school'), $request->user());

        return response()->download($path, basename($path), ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }
}
