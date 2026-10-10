<?php

namespace Tests\Feature;

use App\Models\Religion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_landing_page_with_links_into_the_explorer_and_exports(): void
    {
        $canaan = Religion::factory()->create(['name' => 'Canaan']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('one click away', false)
            ->assertSee(route('explore'))
            ->assertSee(route('religions.show', $canaan))
            ->assertSee(route('export', 'json'))
            ->assertSee(route('export', 'sqlite'));
    }

    public function test_admins_are_sent_to_the_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('home'))->assertRedirect(route('admin.dashboard'));
    }
}
