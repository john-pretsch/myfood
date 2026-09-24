<?php

namespace Tests\Feature;

use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_anyone_can_list_tags(): void
    {
        Tag::create(['name' => 'dessert']);
        Tag::create(['name' => 'breakfast']);

        $this->getJson('/api/tags')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'breakfast')
            ->assertJsonPath('data.1.name', 'dessert');
    }

    public function test_admins_can_create_and_delete_tags(): void
    {
        $this->actingAs($this->admin());

        $id = $this->postJson('/api/tags', ['name' => 'vegan'])->assertCreated()->json('data.id');
        $this->postJson('/api/tags', ['name' => 'vegan'])->assertUnprocessable();

        $this->deleteJson("/api/tags/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('tags', ['name' => 'vegan']);
    }

    public function test_non_admins_cannot_manage_tags(): void
    {
        $tag = Tag::create(['name' => 'vegan']);

        $this->postJson('/api/tags', ['name' => 'dessert'])->assertUnauthorized();

        $this->actingAs(User::factory()->create(['role' => 'edit']));
        $this->postJson('/api/tags', ['name' => 'dessert'])->assertForbidden();
        $this->deleteJson("/api/tags/{$tag->id}")->assertForbidden();
    }

    public function test_recipes_can_only_use_existing_tags(): void
    {
        Tag::create(['name' => 'vegan']);
        $recipe = ['title' => 'Salad', 'ingredients' => [], 'steps' => []];
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/recipes', [...$recipe, 'tags' => ['made-up']])->assertUnprocessable();

        $this->postJson('/api/recipes', [...$recipe, 'tags' => ['vegan']])
            ->assertCreated()
            ->assertJsonPath('data.tags', ['vegan']);

        $this->assertSame(1, Tag::count());
    }

    public function test_deleting_a_tag_removes_it_from_recipes(): void
    {
        $this->actingAs($this->admin());
        $tag = Tag::create(['name' => 'vegan']);
        $recipe = Recipe::create(['title' => 'Salad', 'slug' => 'salad']);
        $recipe->tags()->attach($tag);

        $this->deleteJson("/api/tags/{$tag->id}")->assertNoContent();

        $this->assertSame(0, $recipe->tags()->count());
    }
}
