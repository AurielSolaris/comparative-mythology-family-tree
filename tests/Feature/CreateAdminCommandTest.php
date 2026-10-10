<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_administrator(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Email', 'admin@example.com')
            ->expectsQuestion('Name', 'Admin')
            ->expectsQuestion('Password', 'secret-password')
            ->assertSuccessful();

        $admin = User::query()->where('email', 'admin@example.com')->sole();

        $this->assertTrue($admin->is_admin);
        $this->assertTrue(Hash::check('secret-password', $admin->password));
    }

    public function test_it_can_promote_an_existing_user(): void
    {
        $user = User::factory()->create();

        $this->artisan('app:create-admin')
            ->expectsQuestion('Email', $user->email)
            ->expectsConfirmation("{$user->email} already has an account. Make it an administrator?", 'yes')
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->is_admin);
    }
}
