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

    private function recipe(string $slug): Recipe
    {
        return Recipe::create(['title' => $slug, 'slug' => $slug]);
    }

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

    public function test_tags_can_be_searched_and_sorted_by_popularity(): void
    {
        $dessert = Tag::create(['name' => 'dessert']);
        $quick = Tag::create(['name' => 'quick']);
        Tag::create(['name' => 'dinner']);
        $this->recipe('one')->tags()->attach($quick);
        $this->recipe('two')->tags()->attach($quick);
        $this->recipe('three')->tags()->attach($dessert);

        $this->getJson('/api/tags?sort=popular')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'quick')
            ->assertJsonPath('data.0.recipes_count', 2)
            ->assertJsonPath('data.1.name', 'dessert')
            ->assertJsonPath('data.2.name', 'dinner');

        $this->getJson('/api/tags?search=des')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'dessert');
    }

    public function test_recipes_can_be_filtered_by_any_of_multiple_tags(): void
    {
        $a = Tag::create(['name' => 'a']);
        $b = Tag::create(['name' => 'b']);
        $this->recipe('both')->tags()->attach([$a->id, $b->id]);
        $this->recipe('only-a')->tags()->attach($a);
        $this->recipe('only-b')->tags()->attach($b);
        $this->recipe('untagged');

        $this->getJson('/api/recipes?tag[]=a&tag[]=b')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/recipes?tag=a')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_new_recipes_are_auto_tagged_greedily_from_existing_tags(): void
    {
        foreach (['meat', 'fish', 'baking', 'soup', 'vegetarian', 'keto', 'no cook'] as $name) {
            Tag::create(['name' => $name]);
        }
        $this->actingAs(User::factory()->create(['role' => 'edit']));

        $tags = fn (array $payload) => collect($this->postJson('/api/recipes', $payload)->assertCreated()->json('data.tags'))->sort()->values()->all();

        $this->assertSame(['baking', 'meat'], $tags([
            'title' => 'Beef Pie',
            'ingredients' => [['name' => 'beef chuck'], ['name' => 'flour']],
            'steps' => [['instruction' => 'Preheat the oven.']],
        ]));

        $this->assertSame(['soup', 'vegetarian'], $tags([
            'title' => 'Tomato soup',
            'ingredients' => [['name' => 'tomatoes'], ['name' => 'onion']],
        ]));

        $this->assertSame(['fish', 'keto', 'no cook'], $tags([
            'title' => 'Tuna salad',
            'cook_minutes' => 0,
            'ingredients' => [['name' => 'canned tuna']],
            'nutrition' => ['carbs_g' => 3],
        ]));

        // Explicit tags are kept, and a recipe with nothing to go on gets nothing extra.
        $this->assertSame(['soup'], $tags(['title' => 'Mystery', 'tags' => ['soup']]));
    }

    public function test_recipes_can_create_new_tags_when_saved(): void
    {
        Tag::create(['name' => 'vegan']);
        $this->actingAs(User::factory()->create(['role' => 'edit']));

        $id = $this->postJson('/api/recipes', ['title' => 'Bowl', 'tags' => ['vegan', 'high protein']])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('tags', ['name' => 'high protein']);
        $this->assertSame(1, Tag::where('name', 'vegan')->count());

        $this->putJson("/api/recipes/{$id}", ['title' => 'Bowl', 'tags' => ['vegan', 'spicy']])
            ->assertOk()
            ->assertJsonCount(2, 'data.tags');
        $this->assertDatabaseHas('tags', ['name' => 'spicy']);
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
