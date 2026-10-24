<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\DeleteSchoolFiles;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use App\Services\SchoolDataExportService;
use App\Services\SchoolSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * S6 — Super Admin: School CRUD, Bulk Operations, Branding & System Health.
 *
 * Covers:
 *  - Access control (guest, school_admin, teacher, student → forbidden)
 *  - Schools index / show / create / store / edit / update
 *  - Per-school activate / deactivate / delete
 *  - Bulk activate / bulk deactivate (requires reason) / bulk adjust credits
 *  - Bulk toggle setting (enable_cbt_results_for_parents)
 *  - School settings update
 *  - System Health page
 */
class SuperAdminSchoolCrudTest extends TestCase
{
    use RefreshDatabase;

    private School $platformSchool;

    private User $superAdmin;

    private School $targetSchool;

    private User $schoolAdmin;

    // ─────────────────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

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
        ])->refresh();

        $setup = app(SchoolSetupService::class);

        $this->targetSchool = $setup->create([
            'name' => 'Sunrise Academy',
            'email' => 'info@sunrise.test',
            'levels' => ['primary'],
            'admin_name' => 'School Admin',
            'admin_email' => 'admin@sunrise.test',
            'admin_username' => 'sunrise_admin',
            'admin_password' => 'Password1!',
            'session_name' => '2025/2026',
            'session_start_date' => '2025-09-01',
            'session_end_date' => '2026-07-31',
        ]);

        $this->schoolAdmin = User::withoutGlobalScopes()
            ->where('school_id', $this->targetSchool->id)
            ->where('role', 'school_admin')
            ->firstOrFail();

        $this->schoolAdmin->update([
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Access Control
    // ─────────────────────────────────────────────────────────────────────────

    public function test_guest_cannot_access_super_admin_schools(): void
    {
        $this->get(route('super-admin.schools.index'))
            ->assertRedirectToRoute('login');
    }

    public function test_school_admin_cannot_access_super_admin_schools(): void
    {
        $this->actingAs($this->schoolAdmin)
            ->get(route('super-admin.schools.index'))
            ->assertForbidden();
    }

    public function test_teacher_cannot_access_super_admin_schools(): void
    {
        $teacher = User::withoutGlobalScopes()->create([
            'school_id' => $this->targetSchool->id,
            'name' => 'A Teacher',
            'username' => 'a_teacher',
            'password' => bcrypt('Password1!'),
            'role' => 'teacher',
            'is_active' => true,
            'email_verified_at' => now(),
        ])->refresh();

        $this->actingAs($teacher)
            ->get(route('super-admin.schools.index'))
            ->assertForbidden();
    }

    public function test_student_cannot_access_super_admin_schools(): void
    {
        $student = User::withoutGlobalScopes()->create([
            'school_id' => $this->targetSchool->id,
            'name' => 'A Student',
            'username' => 'a_student',
            'password' => bcrypt('Password1!'),
            'role' => 'student',
            'is_active' => true,
            'email_verified_at' => now(),
        ])->refresh();

        $this->actingAs($student)
            ->get(route('super-admin.schools.index'))
            ->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Schools Index
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_view_schools_index(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.schools.index'))
            ->assertOk()
            ->assertSee('Sunrise Academy');
    }

    public function test_schools_index_search_filters_by_name(): void
    {
        $setup = app(SchoolSetupService::class);
        $setup->create([
            'name' => 'Ocean View School',
            'email' => 'info@ocean.test',
            'levels' => ['nursery'],
            'admin_name' => 'Ocean Admin',
            'admin_email' => 'admin@ocean.test',
            'admin_username' => 'ocean_admin',
            'admin_password' => 'Password1!',
            'session_name' => '2025/2026',
            'session_start_date' => '2025-09-01',
            'session_end_date' => '2026-07-31',
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.schools.index', ['search' => 'Ocean']))
            ->assertOk()
            ->assertSee('Ocean View School')
            ->assertDontSee('Sunrise Academy');
    }

    public function test_schools_index_filters_by_active_status(): void
    {
        $this->targetSchool->update(['is_active' => false]);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.schools.index', ['status' => 'active']))
            ->assertOk()
            ->assertDontSee('Sunrise Academy');

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.schools.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('Sunrise Academy');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Schools Show
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_view_school_show_page(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.schools.show', $this->targetSchool))
            ->assertOk()
            ->assertSee('Sunrise Academy');
    }

    public function test_show_page_contains_preview_portal_button(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.schools.show', $this->targetSchool))
            ->assertOk()
            ->assertSee(__('Preview Portal'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create / Store
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_view_create_school_form(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.schools.create'))
            ->assertOk();
    }

    public function test_super_admin_can_create_a_school(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.schools.store'), [
                'name' => 'New Horizon School',
                'email' => 'info@newhorizon.test',
                'levels' => ['primary'],
                'admin_name' => 'NH Admin',
                'admin_email' => 'admin@newhorizon.test',
                'admin_username' => 'nh_admin',
                'admin_password' => 'Password1!',
                'admin_password_confirmation' => 'Password1!',
                'session_name' => '2025/2026',
                'session_start_date' => '2025-09-01',
                'session_end_date' => '2026-07-31',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('schools', ['name' => 'New Horizon School', 'email' => 'info@newhorizon.test']);
    }

    public function test_create_school_validates_required_fields(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.schools.store'), [])
            ->assertSessionHasErrors(['name', 'email']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Activate / Deactivate (per-school)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_deactivate_a_school(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.schools.deactivate', $this->targetSchool), [
                'deactivation_reason' => 'Subscription expired',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('schools', [
            'id' => $this->targetSchool->id,
            'is_active' => false,
        ]);
    }

    public function test_deactivate_requires_a_reason(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.schools.deactivate', $this->targetSchool), [])
            ->assertSessionHasErrors('deactivation_reason');

        $this->assertDatabaseHas('schools', ['id' => $this->targetSchool->id, 'is_active' => true]);
    }

    public function test_super_admin_can_activate_an_inactive_school(): void
    {
        $this->targetSchool->update(['is_active' => false]);

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.schools.activate', $this->targetSchool))
            ->assertRedirect();

        $this->assertDatabaseHas('schools', ['id' => $this->targetSchool->id, 'is_active' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // S25 — Bulk Activate
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_bulk_activate_schools(): void
    {
        $this->targetSchool->update(['is_active' => false]);

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.schools.bulk-activate'), [
                'school_ids' => [$this->targetSchool->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('schools', ['id' => $this->targetSchool->id, 'is_active' => true]);
    }

    public function test_bulk_activate_requires_at_least_one_school_id(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.schools.bulk-activate'), ['school_ids' => []])
            ->assertSessionHasErrors('school_ids');
    }

    public function test_bulk_activate_logs_audit_entries(): void
    {
        $this->targetSchool->update(['is_active' => false]);

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.schools.bulk-activate'), [
                'school_ids' => [$this->targetSchool->id],
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->targetSchool->id,
            'user_id' => $this->superAdmin->id,
            'action' => 'school.activated',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // S25 — Bulk Deactivate
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_bulk_deactivate_schools(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.schools.bulk-deactivate'), [
                'school_ids' => [$this->targetSchool->id],
                'deactivation_reason' => 'Non-payment of subscription',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('schools', ['id' => $this->targetSchool->id, 'is_active' => false]);
    }

    public function test_bulk_deactivate_requires_reason(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.schools.bulk-deactivate'), [
                'school_ids' => [$this->targetSchool->id],
                // missing deactivation_reason
            ])
            ->assertSessionHasErrors('deactivation_reason');

        // School should still be active
        $this->assertDatabaseHas('schools', ['id' => $this->targetSchool->id, 'is_active' => true]);
    }

    public function test_bulk_deactivate_requires_min_5_char_reason(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.schools.bulk-deactivate'), [
                'school_ids' => [$this->targetSchool->id],
                'deactivation_reason' => 'xyz',  // too short
            ])
            ->assertSessionHasErrors('deactivation_reason');
    }

    public function test_bulk_deactivate_logs_audit_entries(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.schools.bulk-deactivate'), [
                'school_ids' => [$this->targetSchool->id],
                'deactivation_reason' => 'Non-payment of subscription',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->targetSchool->id,
            'user_id' => $this->superAdmin->id,
            'action' => 'school.deactivated',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // S25 — Bulk Adjust Credits
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_bulk_adjust_free_credits(): void
    {
        $initial = $this->targetSchool->fresh()->ai_free_credits;

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.schools.bulk-adjust-credits'), [
                'school_ids' => [$this->targetSchool->id],
                'free_delta' => 5,
                'purchased_delta' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('schools', [
            'id' => $this->targetSchool->id,
            'ai_free_credits' => max(0, $initial + 5),
        ]);
    }

    public function test_super_admin_can_bulk_adjust_purchased_credits(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.schools.bulk-adjust-credits'), [
                'school_ids' => [$this->targetSchool->id],
                'free_delta' => 0,
                'purchased_delta' => 10,
            ])
            ->assertRedirect();

        $updated = School::withoutGlobalScopes()->find($this->targetSchool->id);
        $this->assertSame(10, $updated->ai_purchased_credits);
    }

    public function test_bulk_adjust_credits_requires_school_ids(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.schools.bulk-adjust-credits'), [
                'school_ids' => [],
                'free_delta' => 5,
                'purchased_delta' => 0,
            ])
            ->assertSessionHasErrors('school_ids');
    }

    public function test_bulk_adjust_credits_rejects_delta_above_max(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.schools.bulk-adjust-credits'), [
                'school_ids' => [$this->targetSchool->id],
                'free_delta' => 9999,   // exceeds max:500
                'purchased_delta' => 0,
            ])
            ->assertSessionHasErrors('free_delta');
    }

    public function test_bulk_adjust_credits_logs_audit_entry(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.schools.bulk-adjust-credits'), [
                'school_ids' => [$this->targetSchool->id],
                'free_delta' => 3,
                'purchased_delta' => 7,
                'reason' => 'Monthly bonus',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->targetSchool->id,
            'user_id' => $this->superAdmin->id,
            'action' => 'school.credits_adjusted',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Bulk Toggle Setting
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_bulk_enable_cbt_results(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.schools.bulk-toggle-setting'), [
                'school_ids' => [$this->targetSchool->id],
                'setting_key' => 'enable_cbt_results_for_parents',
                'setting_value' => '1',
            ])
            ->assertRedirect();

        $updated = School::withoutGlobalScopes()->find($this->targetSchool->id);
        $this->assertTrue((bool) $updated->setting('portal.enable_cbt_results_for_parents', false));
    }

    public function test_super_admin_can_bulk_disable_cbt_results(): void
    {
        // First enable it
        $this->targetSchool->update([
            'settings' => array_merge($this->targetSchool->settings ?? [], [
                'portal' => array_merge($this->targetSchool->settings['portal'] ?? [], [
                    'enable_cbt_results_for_parents' => true,
                ]),
            ]),
        ]);

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.schools.bulk-toggle-setting'), [
                'school_ids' => [$this->targetSchool->id],
                'setting_key' => 'enable_cbt_results_for_parents',
                'setting_value' => '0',
            ])
            ->assertRedirect();

        $updated = School::withoutGlobalScopes()->find($this->targetSchool->id);
        $this->assertFalse((bool) $updated->setting('portal.enable_cbt_results_for_parents', true));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // School Settings Update
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_update_school_portal_settings(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.schools.update-settings', $this->targetSchool), [
                'portal' => [
                    'enable_parent_portal' => '1',
                    'enable_quiz_generator' => '0',
                    'session_timeout_minutes' => '45',
                    'max_file_upload_mb' => '8',
                ],
            ])
            ->assertRedirect();

        $updated = School::withoutGlobalScopes()->find($this->targetSchool->id);
        $this->assertSame(45, (int) $updated->setting('portal.session_timeout_minutes'));
        $this->assertFalse((bool) $updated->setting('portal.enable_quiz_generator'));
    }

    public function test_settings_update_validates_session_timeout_range(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.schools.update-settings', $this->targetSchool), [
                'portal' => [
                    'session_timeout_minutes' => '1',  // below min (5)
                    'max_file_upload_mb' => '5',
                ],
            ])
            ->assertSessionHasErrors('portal.session_timeout_minutes');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Delete School
    // ─────────────────────────────────────────────────────────────────────────

    public function test_active_school_cannot_be_deleted(): void
    {
        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.schools.destroy', $this->targetSchool), [
                'name_confirmation' => $this->targetSchool->name,
                'reason' => 'Test deletion reason for this school.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('schools', ['id' => $this->targetSchool->id]);
    }

    public function test_recently_deactivated_school_cannot_be_deleted_yet(): void
    {
        $this->targetSchool->update(['is_active' => false, 'deactivated_at' => now()->subDays(10)]);

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.schools.destroy', $this->targetSchool), [
                'name_confirmation' => $this->targetSchool->name,
                'reason' => 'Test deletion reason for this school.',
            ])
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, now()->addDays(20)->format('j M Y')));

        $this->assertDatabaseHas('schools', ['id' => $this->targetSchool->id]);
    }

    public function test_super_admin_can_delete_a_school_with_correct_name(): void
    {
        Bus::fake([DeleteSchoolFiles::class]);

        // Deleting wipes everything, so the school must have been deactivated for 30+ days and its data exported
        $this->targetSchool->update(['is_active' => false, 'deactivated_at' => now()->subDays(31)]);
        $this->recordDataExport();
        $name = $this->targetSchool->name;
        $id = $this->targetSchool->id;

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.schools.destroy', $this->targetSchool), [
                'name_confirmation' => $name,
                'reason' => 'Test deletion reason for this school.',
            ])
            ->assertRedirect(route('super-admin.schools.index'));

        $this->assertDatabaseMissing('schools', ['id' => $id]);

        // Its Cloudinary files are removed in the background
        Bus::assertDispatched(DeleteSchoolFiles::class, fn (DeleteSchoolFiles $job) => $job->schoolId === $id);
    }

    public function test_school_cannot_be_deleted_before_its_data_is_exported(): void
    {
        $this->targetSchool->update(['is_active' => false, 'deactivated_at' => now()->subDays(31)]);

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.schools.destroy', $this->targetSchool), [
                'name_confirmation' => $this->targetSchool->name,
                'reason' => 'Test deletion reason for this school.',
            ])
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'data export'));

        $this->assertDatabaseHas('schools', ['id' => $this->targetSchool->id]);
    }

    public function test_export_downloads_every_school_record_without_passwords(): void
    {
        $this->targetSchool->update(['is_active' => false, 'deactivated_at' => now()->subDays(3)]);
        $otherSchool = School::factory()->create(['name' => 'Other School']);
        User::factory()->create(['school_id' => $otherSchool->id, 'name' => 'Not In This Export', 'role' => 'student']);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.schools.export-data', $this->targetSchool))
            ->assertOk()
            ->assertDownload();

        // Read the ZIP back
        $zipPath = $response->baseResponse->getFile()->getPathname();
        $copy = sys_get_temp_dir().'/export-test-'.uniqid().'.zip';
        copy($zipPath, $copy);
        $archive = new \PharData($copy);
        $users = (string) file_get_contents($archive['tables/users.csv']->getPathname());

        $this->assertTrue(isset($archive['README.txt']));
        $this->assertStringContainsString($this->schoolAdmin->username, $users);
        $this->assertStringNotContainsString('Not In This Export', $users);
        $header = str_getcsv(trim((string) strtok(ltrim($users, "\xEF\xBB\xBF"), "\n")));
        $this->assertNotContains('password', $header);
        $this->assertNotContains('two_factor_secret', $header);
        unset($archive);
        @unlink($copy);

        // The export is recorded, which unlocks deletion once the waiting period is over
        $this->assertNotNull(app(SchoolDataExportService::class)->exportSinceDeactivation($this->targetSchool->fresh()));
    }

    public function test_active_school_data_cannot_be_exported_for_deletion(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.schools.export-data', $this->targetSchool))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_delete_school_fails_with_wrong_name(): void
    {
        $this->targetSchool->update(['is_active' => false, 'deactivated_at' => now()->subDays(31)]);
        $this->recordDataExport();

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.schools.destroy', $this->targetSchool), [
                'name_confirmation' => 'Wrong Name',
                'reason' => 'Test deletion reason for this school.',
            ])
            ->assertSessionHasErrors('name_confirmation');

        $this->assertDatabaseHas('schools', ['id' => $this->targetSchool->id]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tenant Isolation — bulk ops cannot touch the platform school
    // ─────────────────────────────────────────────────────────────────────────

    public function test_bulk_activate_ignores_platform_school(): void
    {
        // Platform school starts active. Passing its ID to bulk-activate should be silently ignored
        // (tenants() scope excludes it), so is_active stays true and no audit is logged for platform.
        $beforeCount = AuditLog::where('school_id', $this->platformSchool->id)
            ->where('action', 'school.activated')->count();

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.schools.bulk-activate'), [
                'school_ids' => [$this->platformSchool->id, $this->targetSchool->id],
            ])
            ->assertRedirect();

        // Platform school audit count unchanged — it was not processed
        $afterCount = AuditLog::where('school_id', $this->platformSchool->id)
            ->where('action', 'school.activated')->count();
        $this->assertSame($beforeCount, $afterCount);
    }

    private function recordDataExport(): void
    {
        AuditLog::withoutGlobalScopes()->create([
            'school_id' => $this->platformSchool->id,
            'user_id' => $this->superAdmin->id,
            'action' => SchoolDataExportService::AUDIT_ACTION,
            'entity_type' => 'school',
            'entity_id' => $this->targetSchool->id,
        ]);
    }
}
