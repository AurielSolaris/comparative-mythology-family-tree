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

    public function test_the_landing_page_is_also_served_at_slash_home(): void
    {
        $this->get('/home')->assertOk()->assertSee('one click away', false);
    }

    public function test_logged_in_users_still_see_the_landing_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('home'))->assertOk()->assertSee('one click away', false);
        $this->actingAs($admin)->get('/home')->assertOk()->assertSee('one click away', false);
    }
}
