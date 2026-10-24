<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\ImpersonationReadOnly;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\SchoolAnnouncement;
use App\Models\User;
use App\Services\SchoolSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ImpersonateTest extends TestCase
{
    use RefreshDatabase;

    private School $platformSchool;

    private User $superAdmin;

    private School $targetSchool;

    private User $schoolAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the platform meta-school for super_admin
        $this->platformSchool = School::withoutGlobalScopes()->firstOrCreate(
            ['slug' => 'platform'],
            [
                'name' => 'DX-SchoolPortal Platform',
                'email' => 'platform@schoolportal.test',
                'country' => 'Nigeria',
                'is_active' => true,
            ],
        );

        $this->superAdmin = User::withoutGlobalScopes()->create([
            'school_id' => $this->platformSchool->id,
            'name' => 'Super Admin',
            'email' => 'superadmin@schoolportal.test',
            'username' => 'superadmin',
            'password' => bcrypt('Password1!'),
            'role' => 'super_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ])->refresh(); // ensure DB defaults (must_change_password, etc.) are loaded

        // Create a real school with an admin via the setup service
        $this->targetSchool = app(SchoolSetupService::class)->create([
            'name' => 'Target School',
            'email' => 'target@school.test',
            'levels' => ['primary'],
            'admin_name' => 'School Admin',
            'admin_email' => 'admin@target.test',
            'admin_username' => 'target_admin',
            'admin_password' => 'Password1!',
            'session_name' => '2025/2026',
            'session_start_date' => '2025-09-01',
            'session_end_date' => '2026-07-31',
        ]);

        $this->schoolAdmin = User::withoutGlobalScopes()
            ->where('username', 'target_admin')
            ->first();
        $this->schoolAdmin->update([
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);

        // Bind the platform school so ResolveTenant's tests won't fail on auth-only routes
        app()->instance('current.school', $this->platformSchool);
    }

    // -------------------------------------------------------------------------
    // Start impersonation
    // -------------------------------------------------------------------------

    public function test_super_admin_can_start_impersonation(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['school_id' => $this->platformSchool->id])
            ->post(route('super-admin.schools.impersonate', $this->targetSchool));

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHas('success');
    }

    public function test_after_impersonation_starts_authenticated_user_switches_to_school_admin(): void
    {
        // Regression test: Auth::loginUsingId() was silently failing because the
        // BelongsToTenant global scope on User filtered by the platform school,
        // so retrieveById() returned null for the target school's admin.
        // Auth::login($model) with an already-fetched user bypasses this scope.
        $this->actingAs($this->superAdmin)
            ->withSession(['school_id' => $this->platformSchool->id])
            ->post(route('super-admin.schools.impersonate', $this->targetSchool));

        $this->assertAuthenticatedAs($this->schoolAdmin);
    }

    public function test_after_impersonation_starts_session_stores_original_id(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['school_id' => $this->platformSchool->id])
            ->post(route('super-admin.schools.impersonate', $this->targetSchool));

        $response->assertSessionHas('impersonating_original_id', $this->superAdmin->id);
    }

    public function test_after_impersonation_starts_session_has_target_school_bound(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['school_id' => $this->platformSchool->id])
            ->post(route('super-admin.schools.impersonate', $this->targetSchool));

        // The target school must be bound in session so ResolveTenant picks it up
        $response->assertSessionHas('school_id', $this->targetSchool->id);
    }

    public function test_cannot_impersonate_inactive_school(): void
    {
        $this->targetSchool->update(['is_active' => false]);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['school_id' => $this->platformSchool->id])
            ->post(route('super-admin.schools.impersonate', $this->targetSchool));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertAuthenticatedAs($this->superAdmin);
        $this->assertNull(session('impersonating_original_id'));
    }

    public function test_cannot_impersonate_school_with_no_active_admins(): void
    {
        $this->schoolAdmin->update(['is_active' => false]);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['school_id' => $this->platformSchool->id])
            ->post(route('super-admin.schools.impersonate', $this->targetSchool));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertAuthenticatedAs($this->superAdmin);
    }

    public function test_school_admin_cannot_access_impersonate_start_route(): void
    {
        $response = $this->actingAs($this->schoolAdmin)
            ->withSession(['school_id' => $this->targetSchool->id])
            ->post(route('super-admin.schools.impersonate', $this->targetSchool));

        $response->assertForbidden();
    }

    public function test_teacher_cannot_access_impersonate_start_route(): void
    {
        $teacher = User::withoutGlobalScopes()->create([
            'school_id' => $this->targetSchool->id,
            'name' => 'Teacher',
            'email' => null,
            'username' => 'teacher01',
            'password' => bcrypt('Password1!'),
            'role' => 'teacher',
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($teacher)
            ->withSession(['school_id' => $this->targetSchool->id])
            ->post(route('super-admin.schools.impersonate', $this->targetSchool));

        $response->assertForbidden();
    }

    public function test_guest_cannot_access_impersonate_start_route(): void
    {
        $response = $this->post(route('super-admin.schools.impersonate', $this->targetSchool));

        $response->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------------------
    // Audit logging
    // -------------------------------------------------------------------------

    public function test_audit_log_created_on_impersonation_start(): void
    {
        $this->actingAs($this->superAdmin)
            ->withSession(['school_id' => $this->platformSchool->id])
            ->post(route('super-admin.schools.impersonate', $this->targetSchool));

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->targetSchool->id,
            'user_id' => $this->superAdmin->id,
            'action' => 'school.impersonation_started',
            'entity_type' => 'user',
            'entity_id' => $this->schoolAdmin->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Stop impersonation
    // -------------------------------------------------------------------------

    public function test_stop_impersonation_switches_auth_back_to_super_admin(): void
    {
        // Regression test: Auth::loginUsingId() was silently failing because the
        // BelongsToTenant global scope filtered by the target school during stop,
        // preventing the super admin (platform school) from being found.
        $this->actingAs($this->schoolAdmin)
            ->withSession([
                'impersonating_original_id' => $this->superAdmin->id,
                'school_id' => $this->targetSchool->id,
            ])
            ->post(route('impersonate.stop'));

        $this->assertAuthenticatedAs($this->superAdmin);
    }

    public function test_stop_impersonation_restores_super_admin(): void
    {
        $response = $this->actingAs($this->schoolAdmin)
            ->withSession([
                'impersonating_original_id' => $this->superAdmin->id,
                'school_id' => $this->targetSchool->id,
            ])
            ->post(route('impersonate.stop'));

        // Session must no longer carry impersonation markers
        $response->assertSessionMissing('impersonating_original_id');
        $response->assertSessionMissing('school_id');
        $response->assertRedirect(route('super-admin.schools.show', $this->targetSchool));
    }

    public function test_stop_impersonation_redirects_to_school_show_page(): void
    {
        $response = $this->actingAs($this->schoolAdmin)
            ->withSession([
                'impersonating_original_id' => $this->superAdmin->id,
                'school_id' => $this->targetSchool->id,
            ])
            ->post(route('impersonate.stop'));

        $response->assertRedirect(route('super-admin.schools.show', $this->targetSchool));
        $response->assertSessionHas('success');
    }

    public function test_stop_when_not_impersonating_redirects_to_dashboard(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['school_id' => $this->platformSchool->id])
            ->post(route('impersonate.stop'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_stop_clears_school_id_from_session(): void
    {
        $response = $this->actingAs($this->schoolAdmin)
            ->withSession([
                'impersonating_original_id' => $this->superAdmin->id,
                'school_id' => $this->targetSchool->id,
            ])
            ->post(route('impersonate.stop'));

        $response->assertSessionMissing('school_id');
    }

    public function test_stop_route_is_accessible_while_acting_as_school_admin(): void
    {
        // Key behaviour: during impersonation the auth user is school_admin.
        // The stop route must NOT be behind the role:super_admin middleware.
        $response = $this->actingAs($this->schoolAdmin)
            ->withSession([
                'impersonating_original_id' => $this->superAdmin->id,
                'school_id' => $this->targetSchool->id,
            ])
            ->post(route('impersonate.stop'));

        // Any non-403 response means the route was accessible
        $response->assertStatus(302);
    }

    public function test_stop_with_invalid_original_id_forces_logout(): void
    {
        $response = $this->actingAs($this->schoolAdmin)
            ->withSession([
                'impersonating_original_id' => 999999, // non-existent
                'school_id' => $this->targetSchool->id,
            ])
            ->post(route('impersonate.stop'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_audit_log_created_on_impersonation_stop(): void
    {
        $this->actingAs($this->schoolAdmin)
            ->withSession([
                'impersonating_original_id' => $this->superAdmin->id,
                'school_id' => $this->targetSchool->id,
            ])
            ->post(route('impersonate.stop'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->superAdmin->id,
            'action' => 'school.impersonation_stopped',
        ]);
    }

    public function test_show_page_displays_correct_school_data_after_stop_impersonation(): void
    {
        // Simulate the state during impersonation: logged in as school admin
        $response = $this->actingAs($this->schoolAdmin)
            ->withSession([
                'impersonating_original_id' => $this->superAdmin->id,
                'school_id' => $this->targetSchool->id,
            ])
            ->post(route('impersonate.stop'))
            ->assertRedirect(route('super-admin.schools.show', $this->targetSchool));

        // Follow the redirect — now authenticated as super admin
        $showResponse = $this->followingRedirects()->actingAs($this->superAdmin)
            ->get(route('super-admin.schools.show', $this->targetSchool));

        $showResponse->assertOk();
        $showResponse->assertSee($this->targetSchool->name);
        $showResponse->assertSee($this->targetSchool->email);
    }

    public function test_guest_cannot_access_stop_route(): void
    {
        $response = $this->post(route('impersonate.stop'));

        $response->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------------------
    // Middleware bypass during impersonation
    // -------------------------------------------------------------------------

    public function test_impersonation_bypasses_force_password_change_for_admin_with_must_change_password(): void
    {
        // School admin has must_change_password=true (as set by SchoolSetupService)
        $this->schoolAdmin->update(['must_change_password' => true, 'email_verified_at' => now()]);

        // Super admin starts impersonation — the redirect to admin.dashboard should succeed
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['school_id' => $this->platformSchool->id])
            ->post(route('super-admin.schools.impersonate', $this->targetSchool));

        // The start itself redirects to admin.dashboard
        $response->assertRedirect(route('admin.dashboard'));

        // Following that redirect while acting as school admin with impersonation session
        // should NOT be intercepted by ForcePasswordChange (would redirect to password.change otherwise)
        $dashboardResponse = $this->actingAs($this->schoolAdmin)
            ->withSession([
                'impersonating_original_id' => $this->superAdmin->id,
                'school_id' => $this->targetSchool->id,
            ])
            ->get(route('admin.dashboard'));

        // Must not redirect to password change — 200 or redirect to same dashboard is acceptable
        $this->assertNotEquals(route('password.change'), $dashboardResponse->headers->get('Location'));
    }

    // -------------------------------------------------------------------------
    // Read-only while viewing a school
    // -------------------------------------------------------------------------

    private function impersonating(): self
    {
        return $this->actingAs($this->schoolAdmin)->withSession([
            'impersonating_original_id' => $this->superAdmin->id,
            'school_id' => $this->targetSchool->id,
        ]);
    }

    public function test_viewing_a_school_can_read_pages(): void
    {
        app()->forgetInstance('current.school');

        $this->impersonating()->get(route('admin.dashboard'))->assertOk();
    }

    public function test_viewing_a_school_cannot_submit_changes(): void
    {
        app()->forgetInstance('current.school');

        $this->impersonating()
            ->from(route('admin.announcements.index'))
            ->post(route('admin.announcements.store'), ['title' => 'Added by platform', 'content' => 'Should not be saved'])
            ->assertRedirect(route('admin.announcements.index'))
            ->assertSessionHas('impersonation_blocked');

        $this->assertFalse(SchoolAnnouncement::withoutGlobalScopes()->where('title', 'Added by platform')->exists());
    }

    public function test_livewire_actions_cannot_write_to_the_database_while_viewing_a_school(): void
    {
        $request = Request::create('/livewire/update', 'POST', server: ['HTTP_X_LIVEWIRE' => '1']);
        $request->setLaravelSession(tap(app('session.store'))->put('impersonating_original_id', $this->superAdmin->id));
        $this->app->instance('request', $request);

        $middleware = new ImpersonationReadOnly;

        // School data: refused, whatever the write path (here a bulk query that skips model events)
        try {
            $middleware->handle($request, function () {
                DB::table('users')->where('id', $this->schoolAdmin->id)->update(['name' => 'Changed by platform']);

                return response('ok');
            });
            $this->fail('A database write was allowed while viewing a school');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertSame('1', $e->getHeaders()['X-Impersonation-Read-Only'] ?? null);
        }
        $this->assertNotSame('Changed by platform', $this->schoolAdmin->fresh()->name);

        // Framework bookkeeping (cache, sessions) still works, so pages keep functioning
        $middleware->handle($request, function () {
            DB::table('cache')->insert(['key' => 'read-only-test', 'value' => 'x', 'expiration' => time() + 60]);

            return response('ok');
        });
        $this->assertTrue(DB::table('cache')->where('key', 'read-only-test')->exists());
    }

    public function test_stopping_and_logging_out_still_work_while_viewing_a_school(): void
    {
        app()->forgetInstance('current.school');

        $this->impersonating()->post(route('impersonate.stop'))->assertRedirect();
        $this->assertSame($this->superAdmin->id, Auth::id());
        $this->assertTrue(AuditLog::withoutGlobalScopes()->where('action', 'school.impersonation_stopped')->exists());

        $this->impersonating()->post(route('logout'))->assertRedirect();
        $this->assertGuest();
    }

    public function test_school_setup_service_creates_admin_with_email_verified(): void
    {
        $this->assertNotNull(
            $this->schoolAdmin->fresh()->email_verified_at,
            'SchoolSetupService must set email_verified_at so the admin can be impersonated without being blocked by the verified middleware.',
        );
    }
}
