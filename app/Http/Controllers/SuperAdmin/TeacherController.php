<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolLevel;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class TeacherController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::withoutGlobalScopes()
            ->where('role', 'teacher')
            ->with([
                'school:id,name',
                'assignedClasses' => fn ($q) => $q->withoutGlobalScopes(),
                'level' => fn ($q) => $q->withoutGlobalScopes(),
            ]);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        if ($request->filled('school_id')) {
            $query->where('school_id', $request->input('school_id'));
        }

        $teachers = $query->orderBy('name')->paginate(10)->withQueryString();

        $schools = School::tenants()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('super-admin.teachers.index', compact('teachers', 'schools'));
    }

    public function create(Request $request): View
    {
        $schools = School::tenants()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $levels = collect();
        $classes = collect();
        if ($request->filled('school_id')) {
            $levels = SchoolLevel::withoutGlobalScopes()
                ->where('school_id', $request->input('school_id'))
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name']);

            $classes = SchoolClass::withoutGlobalScope('tenant')
                ->with('level:id,name')
                ->where('school_id', $request->input('school_id'))
                ->where('is_active', true)
                ->whereNull('teacher_id')
                ->orderBy('name')
                ->get(['id', 'name', 'level_id', 'teacher_id']);
        }

        return view('super-admin.teachers.create', compact('schools', 'levels', 'classes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['required', 'string', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'in:male,female,other'],
            'level_id' => ['nullable', 'exists:school_levels,id'],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['exists:classes,id'],
        ]);

        // Ensure username is unique within the school
        $existingUser = User::withoutGlobalScopes()
            ->where('school_id', $validated['school_id'])
            ->where('username', $validated['username'])
            ->exists();

        if ($existingUser) {
            return back()->withInput()->withErrors(['username' => __('This username is already taken in this school.')]);
        }

        $teacher = User::withoutGlobalScopes()->create([
            'school_id' => $validated['school_id'],
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => 'teacher',
            'phone' => $validated['phone'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'level_id' => $validated['level_id'] ?? null,
            'must_change_password' => true,
        ]);

        if (! empty($validated['class_ids'])) {
            SchoolClass::withoutGlobalScope('tenant')
                ->whereIn('id', $validated['class_ids'])
                ->update(['teacher_id' => $teacher->id]);
        }

        return redirect()->route('super-admin.teachers.index')
            ->with('success', __('Teacher ":name" created.', ['name' => $validated['name']]));
    }

    // No destroy: a school's teachers are the school's own records. The platform can't delete
    // them, so no school can say its records were changed by the platform.
}
