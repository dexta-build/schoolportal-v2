<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ParentProfile;
use App\Models\ParentStudent;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ParentController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::withoutGlobalScopes()
            ->where('role', 'parent')
            ->with([
                'school:id,name',
                'parentProfile' => fn ($q) => $q->withoutGlobalScopes(),
                'children' => fn ($q) => $q->withoutGlobalScopes()->with([
                    'student' => fn ($q2) => $q2->withoutGlobalScopes()->with([
                        'studentProfile' => fn ($q3) => $q3->withoutGlobalScopes()->with([
                            'class' => fn ($q4) => $q4->withoutGlobalScopes(),
                        ]),
                    ]),
                ]),
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

        $parents = $query->orderBy('name')->paginate(10)->withQueryString();

        $schools = School::tenants()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('super-admin.parents.index', compact('parents', 'schools'));
    }

    public function create(Request $request): View
    {
        $schools = School::tenants()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $students = collect();
        if ($request->filled('school_id')) {
            $students = User::withoutGlobalScopes()
                ->where('role', 'student')
                ->where('school_id', $request->input('school_id'))
                ->where('is_active', true)
                ->with('studentProfile.class:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'school_id']);
        }

        return view('super-admin.parents.create', compact('schools', 'students'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'in:male,female,other'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'relationship' => ['nullable', 'in:father,mother,guardian,other'],
            'address' => ['nullable', 'string'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['exists:users,id'],
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
            $parent = User::withoutGlobalScopes()->create([
                'school_id' => $validated['school_id'],
                'name' => $validated['name'],
                'username' => $validated['username'],
                'password' => Hash::make($validated['password']),
                'role' => 'parent',
                'phone' => $validated['phone'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'must_change_password' => true,
            ]);

            ParentProfile::withoutGlobalScopes()->create([
                'user_id' => $parent->id,
                'school_id' => $validated['school_id'],
                'occupation' => $validated['occupation'] ?? null,
                'relationship' => $validated['relationship'] ?? null,
                'address' => $validated['address'] ?? null,
            ]);

            foreach ($validated['student_ids'] as $studentId) {
                ParentStudent::withoutGlobalScopes()->create([
                    'parent_id' => $parent->id,
                    'student_id' => $studentId,
                    'school_id' => $validated['school_id'],
                ]);
            }
        });

        return redirect()->route('super-admin.parents.index')
            ->with('success', __('Parent ":name" created and linked to :count student(s).', [
                'name' => $validated['name'],
                'count' => count($validated['student_ids']),
            ]));
    }

    // No destroy: a school's parents are the school's own records. The platform can't delete
    // them, so no school can say its records were changed by the platform.
}
