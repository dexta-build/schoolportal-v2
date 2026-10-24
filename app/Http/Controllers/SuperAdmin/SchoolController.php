<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSchoolRequest;
use App\Http\Requests\UpdateSchoolRequest;
use App\Jobs\DeleteSchoolFiles;
use App\Models\AcademicSession;
use App\Models\AiCreditUsageLog;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\Game;
use App\Models\PlatformSetting;
use App\Models\Quiz;
use App\Models\Result;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolLevel;
use App\Models\Term;
use App\Models\User;
use App\Notifications\WelcomeNewUser;
use App\Services\DomainVerificationService;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use App\Services\SchoolDataExportService;
use App\Services\SchoolSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SchoolController extends Controller
{
    public function __construct(private readonly SchoolSetupService $setup) {}

    public function index(Request $request): View
    {
        $query = School::tenants()->withCount([
            'users as students_count' => fn ($q) => $q->withoutGlobalScopes()->where('role', 'student'),
            'users as teachers_count' => fn ($q) => $q->withoutGlobalScopes()->where('role', 'teacher'),
        ]);

        if ($search = trim((string) $request->string('search'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('custom_domain', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('is_active', $status === 'active');
        }

        if ($location = $request->string('location')->toString()) {
            $query->where(function ($q) use ($location) {
                $q->where('city', 'like', "%{$location}%")
                    ->orWhere('state', 'like', "%{$location}%")
                    ->orWhere('country', 'like', "%{$location}%");
            });
        }

        // Sorting
        $sort = $request->string('sort')->toString();
        $dir = in_array($request->string('dir')->toString(), ['asc', 'desc'])
            ? $request->string('dir')->toString()
            : ($sort === 'name' ? 'asc' : 'desc');

        match ($sort) {
            'students' => $query->orderBy('students_count', $dir),
            'teachers' => $query->orderBy('teachers_count', $dir),
            'credits' => $query->orderByRaw("(ai_free_credits + ai_purchased_credits) {$dir}"),
            'name' => $query->orderBy('name', $dir),
            'created' => $query->orderBy('created_at', $dir),
            'health' => $query->orderBy(
                User::withoutGlobalScopes()
                    ->select(DB::raw('MAX(last_login_at)'))
                    ->whereColumn('users.school_id', 'schools.id'),
                $dir,
            ),
            default => $query->orderByDesc('created_at'),
        };

        $schools = $query->paginate(10)->withQueryString();

        // ── Batch health data (§2.3) — 3 queries for all paginated schools, no N+1 ──
        $schoolIds = $schools->pluck('id')->all();
        $healthData = [];

        if ($schoolIds !== []) {
            // 1. Last login per school (MAX across all users)
            $lastLogins = User::withoutGlobalScopes()
                ->select('school_id', DB::raw('MAX(last_login_at) as last_login'))
                ->whereIn('school_id', $schoolIds)
                ->groupBy('school_id')
                ->pluck('last_login', 'school_id');

            // 2. Content created in last 30 days (results + quizzes)
            $since = now()->subDays(30);

            $resultCounts = Result::withoutGlobalScope('tenant')
                ->select('school_id', DB::raw('COUNT(*) as cnt'))
                ->whereIn('school_id', $schoolIds)
                ->where('created_at', '>=', $since)
                ->groupBy('school_id')
                ->pluck('cnt', 'school_id');

            $quizCounts = Quiz::withoutGlobalScopes()
                ->select('school_id', DB::raw('COUNT(*) as cnt'))
                ->whereIn('school_id', $schoolIds)
                ->where('created_at', '>=', $since)
                ->groupBy('school_id')
                ->pluck('cnt', 'school_id');

            // 3. AI credits used this calendar month
            $aiUsage = AiCreditUsageLog::withoutGlobalScopes()
                ->select('school_id', DB::raw('SUM(credits_used) as total'))
                ->whereIn('school_id', $schoolIds)
                ->where('created_at', '>=', now()->startOfMonth())
                ->groupBy('school_id')
                ->pluck('total', 'school_id');

            foreach ($schoolIds as $id) {
                $lastLogin = $lastLogins[$id] ? Carbon::parse($lastLogins[$id]) : null;
                $recentContent = (int) ($resultCounts[$id] ?? 0) + (int) ($quizCounts[$id] ?? 0);
                $aiThisMonth = (int) ($aiUsage[$id] ?? 0);

                $status = match (true) {
                    $lastLogin === null => 'never',
                    $lastLogin->gt(now()->subDays(7)) => 'healthy',
                    $lastLogin->gt(now()->subDays(30)) => 'moderate',
                    $lastLogin->gt(now()->subDays(60)) => 'at_risk',
                    default => 'idle',
                };

                $healthData[$id] = [
                    'last_login' => $lastLogin,
                    'recent_content' => $recentContent,
                    'ai_this_month' => $aiThisMonth,
                    'status' => $status,
                ];
            }
        }

        return view('super-admin.schools.index', [
            'schools' => $schools,
            'healthData' => $healthData,
        ]);
    }

    public function create(): View
    {
        return view('super-admin.schools.create', [
            'levelPresets' => SchoolSetupService::LEVEL_PRESETS,
            'levelNames' => SchoolSetupService::LEVEL_NAMES,
            'nigerianStates' => config('schoolportal.nigerian_states'),
        ]);
    }

    public function store(StoreSchoolRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $school = $this->setup->create($data);

        $this->auditLog($request, $school, 'school.created', [], [
            'name' => $school->name,
            'slug' => $school->slug,
            'email' => $school->email,
            'custom_domain' => $school->custom_domain,
        ]);

        // Send welcome email to the newly created school admin
        $admin = User::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('role', 'school_admin')
            ->first();

        if ($admin?->email) {
            $admin->notify(new WelcomeNewUser('school_admin', $admin->username, $school->name, $data['admin_password']));
        }

        try {
            app(NotificationService::class)->notifySchoolCreated($school);
        } catch (\Throwable $e) {
            Log::warning('School-created notification failed — school was created successfully', [
                'school_id' => $school->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Optional logo as part of initial setup. Upload failure is non-fatal —
        // the school is fully operational without a logo and the admin can upload
        // it later via school settings.
        $logoWarning = null;
        if ($request->hasFile('logo')) {
            try {
                $logoResult = app(FileUploadService::class)->uploadSchoolLogo($request->file('logo'), $school->id);
                $school->update([
                    'logo_url' => $logoResult['url'],
                    'logo_public_id' => $logoResult['public_id'],
                ]);
            } catch (\Throwable $e) {
                Log::warning('Logo upload failed during school setup — school was created successfully', [
                    'school_id' => $school->id,
                    'error' => $e->getMessage(),
                ]);
                $logoWarning = __('School created, but the logo could not be uploaded. You can add it later via school settings.');
            }
        }

        $redirect = redirect()
            ->route('super-admin.schools.show', $school)
            ->with('success', __('School ":name" created successfully.', ['name' => $school->name]));

        return $logoWarning ? $redirect->with('warning', $logoWarning) : $redirect;
    }

    public function show(School $school): View
    {
        // Use direct queries with an explicit school_id constraint to guarantee
        // correct counts regardless of what app('current.school') is currently bound to.
        // This is important after stop-impersonation, where current.school is the
        // platform meta-school rather than the target school being viewed.
        $school->students_count = User::withoutGlobalScopes()->where('school_id', $school->id)->where('role', 'student')->count();
        $school->teachers_count = User::withoutGlobalScopes()->where('school_id', $school->id)->where('role', 'teacher')->count();
        $school->parents_count = User::withoutGlobalScopes()->where('school_id', $school->id)->where('role', 'parent')->count();
        $school->admins_count = User::withoutGlobalScopes()->where('school_id', $school->id)->where('role', 'school_admin')->count();
        $school->levels_count = SchoolLevel::withoutGlobalScopes()->where('school_id', $school->id)->count();
        $school->classes_count = SchoolClass::withoutGlobalScope('tenant')->where('school_id', $school->id)->count();

        $primaryAdmin = User::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('role', 'school_admin')
            ->orderBy('id')
            ->first();

        $schoolAdmins = User::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('role', 'school_admin')
            ->orderBy('id')
            ->get();

        // School levels with their classes
        $levels = SchoolLevel::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->with(['classes' => fn ($q) => $q->withoutGlobalScopes()->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        // Current academic session and active term
        $currentSession = AcademicSession::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('is_current', true)
            ->first();

        $currentTerm = Term::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('is_current', true)
            ->first();

        // Recent audit log entries for this school (last 20)
        $auditLogs = AuditLog::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->with(['user' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name', 'role')])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // Content counts for this school (S16 — bypass tenant global scope)
        $contentCounts = [
            'quizzes' => [
                'total' => Quiz::withoutGlobalScopes()->where('school_id', $school->id)->count(),
                'published' => Quiz::withoutGlobalScopes()->where('school_id', $school->id)->where('is_published', true)->count(),
                'pending' => Quiz::withoutGlobalScopes()->where('school_id', $school->id)->where('status', 'pending')->count(),
            ],
            'games' => [
                'total' => Game::withoutGlobalScopes()->where('school_id', $school->id)->count(),
                'published' => Game::withoutGlobalScopes()->where('school_id', $school->id)->where('is_published', true)->count(),
            ],
            'exams' => [
                'total' => Exam::withoutGlobalScope('tenant')->where('school_id', $school->id)->count(),
                'published' => Exam::withoutGlobalScope('tenant')->where('school_id', $school->id)->where('is_published', true)->count(),
            ],
            'results' => [
                'total' => Result::withoutGlobalScope('tenant')->where('school_id', $school->id)->count(),
                'approved' => Result::withoutGlobalScope('tenant')->where('school_id', $school->id)->where('status', 'approved')->count(),
                'pending' => Result::withoutGlobalScope('tenant')->where('school_id', $school->id)->where('status', 'pending')->count(),
            ],
            'assignments' => [
                'total' => Assignment::withoutGlobalScope('tenant')->where('school_id', $school->id)->count(),
                'pending' => Assignment::withoutGlobalScope('tenant')->where('school_id', $school->id)->where('status', 'pending')->count(),
            ],
        ];

        // Permanent deletion needs a data export taken after the school was deactivated
        $dataExport = app(SchoolDataExportService::class)->exportSinceDeactivation($school);

        return view('super-admin.schools.show', compact(
            'school', 'primaryAdmin', 'schoolAdmins',
            'levels', 'currentSession', 'currentTerm', 'auditLogs',
            'contentCounts', 'dataExport',
        ));
    }

    /**
     * Download every record of a deactivated school as a ZIP of CSV files — handed to the school
     * before it is permanently deleted. Reading only: nothing in the school changes.
     */
    public function exportData(Request $request, School $school, SchoolDataExportService $exporter): BinaryFileResponse|RedirectResponse
    {
        abort_if($school->isPlatform(), 403);

        if ($school->is_active) {
            return back()->with('error', __('Deactivate the school before exporting its data for deletion.'));
        }

        @set_time_limit(300);

        $path = $exporter->export($school, $request->user());

        return response()->download($path, basename($path), ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    public function updateSettings(Request $request, School $school): RedirectResponse
    {
        $request->validate([
            'portal.session_timeout_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'portal.max_file_upload_mb' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $portal = $request->input('portal', []);
        $notifications = $request->input('notifications', []);
        $settings = $school->settings ?? [];

        $settings['portal'] = array_merge($settings['portal'] ?? [], [
            'enable_parent_portal' => (bool) ($portal['enable_parent_portal'] ?? false),
            'enable_quiz_generator' => (bool) ($portal['enable_quiz_generator'] ?? false),
            'enable_game_generator' => (bool) ($portal['enable_game_generator'] ?? false),
            'enable_teacher_approval' => (bool) ($portal['enable_teacher_approval'] ?? false),
            'enable_cbt_results_for_parents' => (bool) ($portal['enable_cbt_results_for_parents'] ?? false),
            'session_timeout_minutes' => (int) ($portal['session_timeout_minutes'] ?? 30),
            'max_file_upload_mb' => (int) ($portal['max_file_upload_mb'] ?? 10),
        ]);

        $settings['notifications'] = array_merge($settings['notifications'] ?? [], [
            'email_enabled' => (bool) ($notifications['email_enabled'] ?? false),
            'notify_parent_on_result' => (bool) ($notifications['notify_parent_on_result'] ?? false),
            'notify_parent_on_notice' => (bool) ($notifications['notify_parent_on_notice'] ?? false),
        ]);

        $school->update(['settings' => $settings]);

        $this->auditLog($request, $school, 'school.settings_updated', [], [
            'portal' => $settings['portal'],
            'notifications' => $settings['notifications'],
        ]);

        return back()->with('success', __('Portal settings saved for :name.', ['name' => $school->name]));
    }

    /**
     * Save super-admin feature locks for a school.
     * Each flag can be locked ON (1), locked OFF (0), or unlocked (empty string / absent).
     */
    public function lockFeatures(Request $request, School $school): RedirectResponse
    {
        $flagKeys = array_keys(PlatformSetting::FEATURE_FLAGS);

        $rules = [];
        foreach ($flagKeys as $key) {
            $rules["locks.{$key}"] = ['nullable', 'in:0,1,'];
        }

        $validated = $request->validate($rules);
        $locks = $validated['locks'] ?? [];

        $settings = $school->settings ?? [];
        $lockedFeatures = [];

        foreach ($flagKeys as $key) {
            $val = $locks[$key] ?? null;
            if ($val === '1') {
                $lockedFeatures[$key] = true;
            } elseif ($val === '0') {
                $lockedFeatures[$key] = false;
            }
            // Empty / absent = unlocked, so we just don't include the key
        }

        // Store as null if no locks are set, otherwise store the map
        $settings['locked_features'] = $lockedFeatures !== [] ? $lockedFeatures : null;

        $school->update(['settings' => $settings]);

        $this->auditLog($request, $school, 'school.feature_locks_updated', [], [
            'locked_features' => $lockedFeatures,
        ]);

        return back()->with('success', __('Feature locks saved for :name.', ['name' => $school->name]));
    }

    public function bulkToggleSetting(Request $request): RedirectResponse
    {
        $request->validate([
            'school_ids' => ['required', 'array', 'min:1'],
            'school_ids.*' => ['required', 'integer', 'exists:schools,id'],
            'setting_key' => ['required', 'string', Rule::in(['enable_cbt_results_for_parents'])],
            'setting_value' => ['required', 'boolean'],
        ]);

        $schools = School::tenants()
            ->whereIn('id', $request->input('school_ids'))
            ->get();

        $value = (bool) $request->input('setting_value');

        foreach ($schools as $school) {
            $settings = $school->settings ?? [];
            $settings['portal'] = array_merge($settings['portal'] ?? [], [
                $request->input('setting_key') => $value,
            ]);
            $school->update(['settings' => $settings]);
            $this->auditLog($request, $school, 'school.settings_updated', [], [
                'portal' => [$request->input('setting_key') => $value],
            ]);
        }

        $label = $value ? __('enabled') : __('disabled');

        return back()->with('success', __(':count school(s): CBT Results for Parents :label.', [
            'count' => $schools->count(),
            'label' => $label,
        ]));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // S25 — Bulk Operations
    // ─────────────────────────────────────────────────────────────────────────

    public function bulkActivate(Request $request): RedirectResponse
    {
        $request->validate([
            'school_ids' => ['required', 'array', 'min:1'],
            'school_ids.*' => ['required', 'integer', 'exists:schools,id'],
        ]);

        $schools = School::tenants()->whereIn('id', $request->input('school_ids'))->get();

        foreach ($schools as $school) {
            abort_if($school->isPlatform(), 403);
            $school->update(['is_active' => true, 'deactivation_reason' => null, 'deactivated_at' => null]);
            $this->auditLog($request, $school, 'school.activated', ['is_active' => false], ['is_active' => true]);
        }

        return back()->with('success', __(':count school(s) activated successfully.', ['count' => $schools->count()]));
    }

    public function bulkDeactivate(Request $request): RedirectResponse
    {
        $request->validate([
            'school_ids' => ['required', 'array', 'min:1'],
            'school_ids.*' => ['required', 'integer', 'exists:schools,id'],
            'deactivation_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $schools = School::tenants()->whereIn('id', $request->input('school_ids'))->get();
        $reason = $request->input('deactivation_reason');

        foreach ($schools as $school) {
            abort_if($school->isPlatform(), 403);
            $school->update(['is_active' => false, 'deactivation_reason' => $reason, 'deactivated_at' => now()]);
            $this->auditLog($request, $school, 'school.deactivated', ['is_active' => true], [
                'is_active' => false,
                'reason' => $reason,
            ]);
        }

        return back()->with('success', __(':count school(s) deactivated.', ['count' => $schools->count()]));
    }

    public function bulkAdjustCredits(Request $request): RedirectResponse
    {
        $request->validate([
            'school_ids' => ['required', 'array', 'min:1'],
            'school_ids.*' => ['required', 'integer', 'exists:schools,id'],
            'free_delta' => ['required', 'integer', 'min:-500', 'max:500'],
            'purchased_delta' => ['required', 'integer', 'min:-500', 'max:500'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $setup = app(SchoolSetupService::class);
        $schools = School::tenants()->whereIn('id', $request->input('school_ids'))->get();
        $freeDelta = (int) $request->input('free_delta');
        $purchasedDelta = (int) $request->input('purchased_delta');

        foreach ($schools as $school) {
            $old = ['ai_free_credits' => $school->ai_free_credits, 'ai_purchased_credits' => $school->ai_purchased_credits];
            $setup->adjustCredits($school, $freeDelta, $purchasedDelta);
            $school->refresh();
            $this->auditLog($request, $school, 'school.credits_adjusted', $old, [
                'ai_free_credits' => $school->ai_free_credits,
                'ai_purchased_credits' => $school->ai_purchased_credits,
                'free_delta' => $freeDelta,
                'purchased_delta' => $purchasedDelta,
                'reason' => $request->input('reason'),
            ]);
        }

        return back()->with('success', __('Credits adjusted for :count school(s).', ['count' => $schools->count()]));
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function edit(School $school): View
    {
        $primaryAdmin = User::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('role', 'school_admin')
            ->orderBy('id')
            ->first();

        return view('super-admin.schools.edit', [
            'school' => $school,
            'primaryAdmin' => $primaryAdmin,
            'nigerianStates' => config('schoolportal.nigerian_states'),
        ]);
    }

    public function update(UpdateSchoolRequest $school_request, School $school): RedirectResponse
    {
        $data = $school_request->validated();

        $oldValues = [
            'name' => $school->name,
            'email' => $school->email,
            'custom_domain' => $school->custom_domain,
            'motto' => $school->motto,
            'phone' => $school->phone,
            'address' => $school->address,
            'city' => $school->city,
            'state' => $school->state,
            'country' => $school->country,
            'website' => $school->website,
        ];

        $settings = $school->settings ?? [];
        $settings['branding'] = array_merge($settings['branding'] ?? [], [
            'primary_color' => $data['primary_color'] ?? ($settings['branding']['primary_color'] ?? '#4F46E5'),
            'secondary_color' => $data['secondary_color'] ?? ($settings['branding']['secondary_color'] ?? '#F59E0B'),
            'accent_color' => $data['accent_color'] ?? ($settings['branding']['accent_color'] ?? '#10B981'),
        ]);

        $newDomain = $data['custom_domain'] ?? null;
        $domainChanged = $school->custom_domain !== $newDomain;

        $school->update([
            'name' => $data['name'],
            'custom_domain' => $newDomain,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            // Reset verification if domain changed
            ...($school->isDirty('custom_domain') ? ['domain_verified_at' => null] : []),
            'country' => $data['country'] ?? null,
            'website' => $data['website'] ?? null,
            'motto' => $data['motto'] ?? null,
            'settings' => $settings,
            ...($domainChanged ? ['domain_verified_at' => null] : []),
        ]);

        $this->auditLog($school_request, $school, 'school.updated', $oldValues, [
            'name' => $school->name,
            'email' => $school->email,
            'custom_domain' => $school->custom_domain,
            'motto' => $school->motto,
            'phone' => $school->phone,
            'address' => $school->address,
            'city' => $school->city,
            'state' => $school->state,
            'country' => $school->country,
            'website' => $school->website,
        ]);

        return redirect()
            ->route('super-admin.schools.show', $school)
            ->with('success', __('School updated.'));
    }

    public function activate(Request $request, School $school): RedirectResponse
    {
        $school->update([
            'is_active' => true,
            'deactivation_reason' => null,
            'deactivated_at' => null,
        ]);

        $this->auditLog($request, $school, 'school.activated', ['is_active' => false], ['is_active' => true]);

        return back()->with('success', __('":name" activated.', ['name' => $school->name]));
    }

    public function deactivate(Request $request, School $school): RedirectResponse
    {
        $validated = $request->validate([
            'deactivation_reason' => ['required', 'string', 'max:1000'],
        ]);

        $school->update([
            'is_active' => false,
            'deactivation_reason' => $validated['deactivation_reason'],
            'deactivated_at' => now(),
        ]);

        $this->auditLog($request, $school, 'school.deactivated', ['is_active' => true], [
            'is_active' => false,
            'reason' => $validated['deactivation_reason'],
        ]);

        return back()->with('success', __('":name" deactivated.', ['name' => $school->name]));
    }

    public function destroy(Request $request, School $school): RedirectResponse
    {
        abort_if($school->isPlatform(), 403, 'The platform school cannot be deleted.');

        // Deleting wipes the school's entire history, so it must have been deactivated for a while first
        if (! $school->canBePermanentlyDeleted()) {
            return back()->with('error', $school->deletableFrom()
                ? __('":name" can be permanently deleted from :date — schools must stay deactivated for :days days first.', [
                    'name' => $school->name,
                    'date' => $school->deletableFrom()->format('j M Y'),
                    'days' => School::DELETION_WAITING_DAYS,
                ])
                : __('Deactivate ":name" first. It can be permanently deleted :days days after deactivation.', [
                    'name' => $school->name,
                    'days' => School::DELETION_WAITING_DAYS,
                ]));
        }

        // The school's data must have been handed over first
        if (! app(SchoolDataExportService::class)->exportSinceDeactivation($school)) {
            return back()->with('error', __('Download the data export for ":name" before deleting it, so the school keeps a copy of its records.', ['name' => $school->name]));
        }

        $request->validate([
            'name_confirmation' => [
                'required',
                'string',
                Rule::in([$school->name]),
            ],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'name_confirmation.in' => __('The name you entered does not match. Type the exact school name to confirm.'),
        ]);

        // Log to the platform school's audit trail before the cascade wipes the school's own logs.
        $platformSchool = School::where('slug', School::PLATFORM_SLUG)->first();
        AuditLog::create([
            'school_id' => $platformSchool?->id ?? $school->id,
            'user_id' => auth()->id(),
            'action' => 'school.permanently_deleted',
            'entity_type' => 'school',
            'entity_id' => $school->id,
            'old_values' => [
                'name' => $school->name,
                'slug' => $school->slug,
                'email' => $school->email,
                'custom_domain' => $school->custom_domain,
            ],
            'new_values' => [
                'reason' => $request->string('reason')->toString(),
                'deleted_by' => auth()->user()->email ?? auth()->user()->username,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $name = $school->name;
        $schoolId = $school->id;
        $school->delete();

        // Its photos, result sheets and attachments on Cloudinary go too (queued: can be many files)
        DeleteSchoolFiles::dispatch($schoolId, $name);

        return redirect()
            ->route('super-admin.schools.index')
            ->with('success', __('School ":name" permanently deleted. Its files are being removed from storage.', ['name' => $name]));
    }

    /**
     * Reset the password for a school's admin user.
     */
    public function resetAdminPassword(Request $request, School $school): RedirectResponse
    {
        $validated = $request->validate([
            'admin_id' => ['required', 'integer'],
            'password' => ['required', 'string', Password::defaults()],
        ]);

        $admin = User::withoutGlobalScopes()
            ->where('id', $validated['admin_id'])
            ->where('school_id', $school->id)
            ->where('role', 'school_admin')
            ->firstOrFail();

        $admin->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => true,
        ]);

        $this->auditLog($request, $school, 'school.admin_password_reset', [], [
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'username' => $admin->username,
        ]);

        return back()->with('success', __('Password reset for :name. They will be prompted to change it on next login.', ['name' => $admin->name]));
    }

    /**
     * Show form to create an additional school admin.
     */
    public function createAdmin(School $school): View
    {
        return view('super-admin.schools.create-admin', compact('school'));
    }

    /**
     * Store a new school admin for the given school.
     */
    public function storeAdmin(Request $request, School $school): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['required', 'string', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        // Ensure username is unique within the school
        $exists = User::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('username', $validated['username'])
            ->exists();

        if ($exists) {
            return back()->withInput()->withErrors(['username' => __('This username is already taken in this school.')]);
        }

        // Ensure email is unique within the school (if provided)
        if (! empty($validated['email'])) {
            $emailExists = User::withoutGlobalScopes()
                ->where('school_id', $school->id)
                ->where('email', $validated['email'])
                ->exists();

            if ($emailExists) {
                return back()->withInput()->withErrors(['email' => __('This email is already in use in this school.')]);
            }
        }

        User::withoutEvents(fn () => User::create([
            'school_id' => $school->id,
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => 'school_admin',
            'phone' => $validated['phone'] ?? null,
            'is_active' => true,
            'must_change_password' => true,
        ]));

        $this->auditLog($request, $school, 'school.admin_created', [], [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
        ]);

        return redirect()
            ->route('super-admin.schools.show', $school)
            ->with('success', __('Admin ":name" created successfully.', ['name' => $validated['name']]));
    }

    /**
     * Remove a school admin (cannot remove the last one).
     */
    public function destroyAdmin(Request $request, School $school, string $admin): RedirectResponse
    {
        // Super admins work across schools — look the user up without the tenant scope
        $admin = User::withoutGlobalScopes()->findOrFail($admin);

        abort_if($admin->school_id !== $school->id || $admin->role !== 'school_admin', 404);

        $adminCount = User::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('role', 'school_admin')
            ->count();

        if ($adminCount <= 1) {
            return back()->withErrors(['admin' => __('Cannot remove the last admin. A school must have at least one admin.')]);
        }

        $this->auditLog($request, $school, 'school.admin_removed', [
            'admin_id' => $admin->id,
            'name' => $admin->name,
            'username' => $admin->username,
            'email' => $admin->email,
        ], []);

        $admin->delete();

        return redirect()
            ->route('super-admin.schools.show', $school)
            ->with('success', __('Admin removed successfully.'));
    }

    /**
     * Verify that a school's custom domain is correctly configured.
     */
    public function verifyDomain(School $school, DomainVerificationService $verifier): RedirectResponse
    {
        if (! $school->custom_domain) {
            return back()->with('error', __('No custom domain configured for this school.'));
        }

        $result = $verifier->verify($school);

        if ($result['verified']) {
            return back()->with('success', __('Domain verified! :domain is correctly configured and serving the portal.', ['domain' => $school->custom_domain]));
        }

        return back()->with('warning', $result['message']);
    }

    /**
     * Upload a school logo to Cloudinary.
     */
    public function uploadLogo(Request $request, School $school, FileUploadService $uploader): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ]);

        try {
            // Delete old logo if it exists
            if ($school->logo_public_id) {
                $uploader->delete($school->logo_public_id);
            }

            $result = $uploader->uploadSchoolLogo($request->file('logo'), $school->id);

            $school->logo_url = $result['url'];
            $school->logo_public_id = $result['public_id'];
            $school->save();

            \Log::info('Logo uploaded successfully', [
                'school_id' => $school->id,
                'logo_url' => $school->logo_url,
                'logo_public_id' => $school->logo_public_id,
            ]);

            return back()->with('success', __('School logo updated.'));
        } catch (\Throwable $e) {
            \Log::error('Logo upload failed', [
                'school_id' => $school->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', __('Logo upload failed: ').$e->getMessage());
        }
    }

    /**
     * Remove a school's logo.
     */
    public function removeLogo(School $school, FileUploadService $uploader): RedirectResponse
    {
        if ($school->logo_public_id) {
            $uploader->delete($school->logo_public_id);
        }

        $school->update([
            'logo_url' => null,
            'logo_public_id' => null,
        ]);

        return back()->with('success', __('School logo removed.'));
    }

    private function auditLog(Request $request, School $school, string $action, array $old, array $new): void
    {
        AuditLog::create([
            'school_id' => $school->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => 'school',
            'entity_id' => $school->id,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
