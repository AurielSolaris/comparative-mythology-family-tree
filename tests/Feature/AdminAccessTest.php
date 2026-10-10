<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_users_who_are_not_admins_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.entities.index'))
            ->assertForbidden();
    }

    public function test_admins_can_open_every_admin_page(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.entities.index'))->assertOk();
        $this->get(route('admin.entities.create'))->assertOk();
        $this->get(route('admin.religions.index'))->assertOk();
        $this->get(route('admin.religions.create'))->assertOk();
    }

    public function test_admins_can_log_in_and_out(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post(route('login'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->from(route('login'))
            ->post(route('login'), ['email' => $admin->email, 'password' => 'wrong'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login'), ['email' => $admin->email, 'password' => 'wrong']);
        }

        $this->post(route('login'), ['email' => $admin->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
