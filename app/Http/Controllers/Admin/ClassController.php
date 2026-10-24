<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\SchoolLevel;
use App\Models\StudentSubjectScore;
use App\Models\StudentTermReport;
use App\Models\User;
use App\Services\RecentlyDeletedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClassController extends Controller
{
    public function index(): View
    {
        $classes = SchoolClass::with(['level:id,name', 'teacher:id,name'])
            ->withCount(['students'])
            ->orderBy('level_id')
            ->orderBy('sort_order')
            ->paginate(10);

        return view('admin.classes.index', compact('classes'));
    }

    public function create(): View
    {
        $levels = SchoolLevel::where('is_active', true)->orderBy('sort_order')->get();
        $teachers = User::where('role', 'teacher')->where('is_active', true)->orderBy('name')->get();

        return view('admin.classes.create', compact('levels', 'teachers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'level_id' => ['required', 'exists:school_levels,id'],
            'teacher_id' => ['nullable', 'exists:users,id'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        if ($error = $this->nameTakenError((int) $validated['level_id'], $validated['slug'], $validated['name'])) {
            return back()->withInput()->withErrors(['name' => $error]);
        }

        SchoolClass::create($validated);

        return redirect()->route('admin.classes.index')
            ->with('success', __('Class ":name" created.', ['name' => $validated['name']]));
    }

    public function edit(SchoolClass $class): View
    {
        $levels = SchoolLevel::where('is_active', true)->orderBy('sort_order')->get();
        $teachers = User::where('role', 'teacher')->where('is_active', true)->orderBy('name')->get();

        return view('admin.classes.edit', compact('class', 'levels', 'teachers'));
    }

    public function update(Request $request, SchoolClass $class): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'level_id' => ['required', 'exists:school_levels,id'],
            'teacher_id' => ['nullable', 'exists:users,id'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        if ($error = $this->nameTakenError((int) $validated['level_id'], $validated['slug'], $validated['name'], $class->id)) {
            return back()->withInput()->withErrors(['name' => $error]);
        }

        $class->update($validated);

        return redirect()->route('admin.classes.index')
            ->with('success', __('Class updated.'));
    }

    public function destroy(SchoolClass $class): RedirectResponse
    {
        if ($class->students()->exists()) {
            return redirect()->route('admin.classes.index')
                ->with('error', __('Cannot delete a class that has students. Move students first.'));
        }

        // Removing the class for good later would take its score and report history with it
        if (StudentSubjectScore::where('class_id', $class->id)->exists() || StudentTermReport::where('class_id', $class->id)->exists()) {
            return redirect()->route('admin.classes.index')
                ->with('error', __('":name" has score or report card history, so it cannot be deleted. Edit it and mark it inactive instead.', ['name' => $class->name]));
        }

        $class->delete();

        return redirect()->route('admin.classes.index')
            ->with('undo', app(RecentlyDeletedService::class)->deleted($class, 'admin'));
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'class_ids' => ['required', 'array', 'min:1'],
            'class_ids.*' => ['integer', 'exists:classes,id'],
        ]);

        $classes = SchoolClass::withCount('students')
            ->whereIn('id', $validated['class_ids'])
            ->get();

        // Empty classes only, and never one with score history (removing it for good later would take that history too)
        $withHistory = StudentSubjectScore::whereIn('class_id', $classes->pluck('id'))->distinct()->pluck('class_id')
            ->merge(StudentTermReport::whereIn('class_id', $classes->pluck('id'))->distinct()->pluck('class_id'))
            ->map(fn ($id) => (int) $id)
            ->all();
        $deletable = $classes->filter(fn (SchoolClass $class) => $class->students_count === 0 && ! in_array($class->id, $withHistory, true));
        $skipped = $classes->count() - $deletable->count();

        foreach ($deletable as $class) {
            $class->delete();
        }

        if ($deletable->isEmpty()) {
            return redirect()->route('admin.classes.index')
                ->with('error', __('No classes were deleted. Classes must have no students and no score history. Mark classes inactive instead.'));
        }

        $message = trans_choice(':count class deleted.|:count classes deleted.', $deletable->count(), ['count' => $deletable->count()]);

        if ($skipped > 0) {
            $message .= ' '.__(':count selected class(es) with students or score history were skipped.', ['count' => $skipped]);
        }

        $message .= ' '.__('You can restore them from Recently deleted for :days days.', ['days' => SchoolClass::RESTORE_DAYS]);

        return redirect()->route('admin.classes.index')
            ->with('success', $message);
    }

    /**
     * Class names are unique within a level, and a deleted class keeps its name until it is purged.
     */
    private function nameTakenError(int $levelId, string $slug, string $name, ?int $exceptId = null): ?string
    {
        $sameName = SchoolClass::withTrashed()
            ->where('level_id', $levelId)
            ->where('slug', $slug)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->first();

        return match (true) {
            $sameName === null => null,
            $sameName->trashed() => __('A deleted class is called ":name". Restore it from Recently deleted, or choose another name.', ['name' => $name]),
            default => __('This level already has a class called ":name".', ['name' => $name]),
        };
    }
}
