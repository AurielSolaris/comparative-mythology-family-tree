<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Religion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReligionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_a_religion_can_be_created_with_a_slug(): void
    {
        $this->post(route('admin.religions.store'), ['name' => 'Ancient Greek'])
            ->assertRedirect(route('admin.religions.index'));

        $this->assertDatabaseHas('religions', ['name' => 'Ancient Greek', 'slug' => 'ancient-greek']);
    }

    public function test_religion_names_are_unique(): void
    {
        Religion::factory()->create(['name' => 'Norse']);

        $this->post(route('admin.religions.store'), ['name' => 'Norse'])->assertSessionHasErrors('name');
    }

    public function test_a_religion_can_be_renamed(): void
    {
        $religion = Religion::factory()->create(['name' => 'Greek']);

        $this->put(route('admin.religions.update', $religion), ['name' => 'Hellenic'])
            ->assertRedirect(route('admin.religions.index'));

        $this->assertSame('hellenic', $religion->fresh()->slug);
    }

    public function test_a_religion_can_have_a_chosen_colour_or_an_automatic_one(): void
    {
        $religion = Religion::factory()->create(['name' => 'Norse']);

        $this->put(route('admin.religions.update', $religion), ['name' => 'Norse', 'color' => '#336699'])
            ->assertSessionHasNoErrors();
        $this->assertSame('#336699', $religion->fresh()->color);

        $this->put(route('admin.religions.update', $religion), ['name' => 'Norse', 'color' => '#336699', 'automatic_color' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertNull($religion->fresh()->color);

        $this->put(route('admin.religions.update', $religion), ['name' => 'Norse', 'color' => 'blue'])
            ->assertSessionHasErrors('color');
    }

    public function test_the_chosen_colour_is_sent_to_the_explorer(): void
    {
        $religion = Religion::factory()->create(['color' => '#aa3300']);
        Entity::factory()->for($religion)->create();

        $this->getJson(route('graph.religions', $religion))
            ->assertJsonPath('nodes.0.religion.color', '#aa3300');
    }

    public function test_a_religion_with_entities_cannot_be_deleted(): void
    {
        $religion = Religion::factory()->create();
        Entity::factory()->for($religion)->create();

        $this->delete(route('admin.religions.destroy', $religion))->assertSessionHas('error');

        $this->assertModelExists($religion);
    }

    public function test_an_empty_religion_can_be_deleted(): void
    {
        $religion = Religion::factory()->create();

        $this->delete(route('admin.religions.destroy', $religion))->assertRedirect(route('admin.religions.index'));

        $this->assertModelMissing($religion);
    }
}
