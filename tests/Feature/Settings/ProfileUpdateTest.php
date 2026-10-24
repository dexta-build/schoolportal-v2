<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\WithSchoolContext;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;
    use WithSchoolContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolContext();
    }

    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('profile.edit'))->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $this->actingAs($this->admin);

        $response = Livewire::test('pages::settings.profile')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->call('updateProfileInformation');

        $response->assertHasNoErrors();

        $this->admin->refresh();

        $this->assertEquals('Test User', $this->admin->name);
        $this->assertEquals('test@example.com', $this->admin->email);
        $this->assertNull($this->admin->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_email_address_is_unchanged(): void
    {
        $this->actingAs($this->admin);

        $response = Livewire::test('pages::settings.profile')
            ->set('name', 'Test User')
            ->set('email', $this->admin->email)
            ->call('updateProfileInformation');

        $response->assertHasNoErrors();

        $this->assertNotNull($this->admin->refresh()->email_verified_at);
    }

    public function test_users_cannot_delete_their_own_account(): void
    {
        // Accounts are managed by the school admin; deleting would cascade to a student's records
        foreach ([$this->admin, $this->createSchoolUser('teacher'), $this->createSchoolUser('student')] as $user) {
            $this->actingAs($user);

            Livewire::test('pages::settings.delete-user-modal')
                ->set('password', 'Password1!')
                ->call('deleteUser')
                ->assertForbidden();

            $this->assertNotNull($user->fresh());
        }
    }

    public function test_profile_page_does_not_offer_account_deletion(): void
    {
        $this->actingAs($this->admin)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('Delete account');
    }
}
