<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Term;
use App\Services\EndOfTermService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EndOfTermController extends Controller
{
    /**
     * The end-of-term checklist: scores → lock → report cards → approve → publish → promote.
     */
    public function __invoke(Request $request, EndOfTermService $service): View
    {
        $school = app('current.school');

        $term = $request->filled('term_id')
            ? Term::with('session')->findOrFail($request->integer('term_id'))
            : Term::with('session')->where('is_current', true)->first();

        $sessions = AcademicSession::with(['terms' => fn ($q) => $q->orderBy('term_number')])
            ->orderByDesc('start_date')
            ->get();

        $overview = $term ? $service->overview($school->id, $term) : null;

        return view('admin.end-of-term.index', compact('term', 'sessions', 'overview'));
    }
}
