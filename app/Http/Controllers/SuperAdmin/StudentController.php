<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolLevel;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::withoutGlobalScopes()
            ->where('role', 'student')
            ->with([
                'school:id,name',
                'studentProfile' => fn ($q) => $q->withoutGlobalScopes()->with([
                    'class' => fn ($q2) => $q2->withoutGlobalScopes(),
                ]),
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

        if ($request->filled('class_id')) {
            $query->whereHas('studentProfile', fn ($q) => $q->withoutGlobalScopes()->where('class_id', $request->input('class_id')));
        }

        if ($request->filled('level_id')) {
            $query->where('level_id', $request->input('level_id'));
        }

        $students = $query->orderBy('name')->paginate(10)->withQueryString();

        $schools = School::tenants()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        // If a school is selected, load its levels and classes for the filters
        $levels = collect();
        $classes = collect();
        if ($request->filled('school_id')) {
            $levels = SchoolLevel::withoutGlobalScopes()
                ->where('school_id', $request->input('school_id'))
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name']);

            $classQuery = SchoolClass::withoutGlobalScope('tenant')
                ->where('school_id', $request->input('school_id'))
                ->where('is_active', true)
                ->orderBy('name');

            if ($request->filled('level_id')) {
                $classQuery->where('level_id', $request->input('level_id'));
            }

            $classes = $classQuery->get(['id', 'name']);
        }

        return view('super-admin.students.index', compact('students', 'schools', 'levels', 'classes'));
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
                ->orderBy('name')
                ->get(['id', 'name', 'level_id']);
        }

        return view('super-admin.students.create', compact('schools', 'levels', 'classes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', Password::defaults()],
            'gender' => ['required', 'in:male,female,other'],
            'level_id' => ['required', 'exists:school_levels,id'],
            'class_id' => ['required', 'exists:classes,id'],
            'admission_number' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date'],
            'address' => ['nullable', 'string'],
            'blood_group' => ['nullable', 'string', 'max:5'],
            'medical_notes' => ['nullable', 'string'],
        ]);

        // Ensure username is unique within the school
        $existingUser = User::withoutGlobalScopes()
            ->where('school_id', $validated['school_id'])
            ->where('username', $validated['username'])
            ->exists();

        if ($existingUser) {
            return back()->withInput()->withErrors(['username' => __('This username is already taken in this school.')]);
        }

        DB::transaction(function () use ($validated) {
            $school = School::findOrFail($validated['school_id']);
            $currentSession = $school->currentSession();
            $currentTerm = $school->currentTerm();

            $user = User::withoutGlobalScopes()->create([
                'school_id' => $validated['school_id'],
                'name' => $validated['name'],
                'username' => $validated['username'],
                'password' => Hash::make($validated['password']),
                'role' => 'student',
                'gender' => $validated['gender'],
                'level_id' => $validated['level_id'],
                'must_change_password' => true,
            ]);

            StudentProfile::withoutGlobalScopes()->create([
                'user_id' => $user->id,
                'school_id' => $validated['school_id'],
                'class_id' => $validated['class_id'],
                'admission_number' => $validated['admission_number'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'address' => $validated['address'] ?? null,
                'blood_group' => $validated['blood_group'] ?? null,
                'medical_notes' => $validated['medical_notes'] ?? null,
                'enrolled_session_id' => $currentSession?->id,
                'enrolled_term_id' => $currentTerm?->id,
            ]);
        });

        return redirect()->route('super-admin.students.index')
            ->with('success', __('Student ":name" created.', ['name' => $validated['name']]));
    }

    // No destroy: a school's students are the school's own records. The platform can't delete
    // them, so no school can say its records were changed by the platform.
}
