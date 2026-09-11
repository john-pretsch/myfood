<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecipeImportTest extends TestCase
{
    use RefreshDatabase;

    private function pageWithRecipeJsonLd(): string
    {
        $jsonLd = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => 'Test Pancakes',
            'description' => 'Fluffy weekend pancakes.',
            'image' => ['https://example.com/pancakes.jpg'],
            'recipeYield' => '4 servings',
            'prepTime' => 'PT10M',
            'cookTime' => 'PT15M',
            'recipeCuisine' => 'American',
            'keywords' => 'breakfast, easy, brunch',
            'recipeIngredient' => ['2 cups flour', '2 eggs', '1.5 cups milk'],
            'recipeInstructions' => [
                ['@type' => 'HowToStep', 'text' => 'Mix the dry ingredients.'],
                ['@type' => 'HowToStep', 'text' => 'Whisk in eggs and milk.'],
                ['@type' => 'HowToStep', 'text' => 'Cook on a hot griddle.'],
            ],
            'nutrition' => [
                '@type' => 'NutritionInformation',
                'calories' => '320 kcal',
                'proteinContent' => '9 g',
            ],
        ]);

        return "<html><head><script type=\"application/ld+json\">{$jsonLd}</script></head><body>x</body></html>";
    }

    public function test_it_imports_a_recipe_from_a_url(): void
    {
        Http::fake([
            'recipes.test/*' => Http::response($this->pageWithRecipeJsonLd()),
        ]);

        $response = $this->postJson('/api/recipes/import', [
            'url' => 'https://recipes.test/pancakes',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Test Pancakes')
            ->assertJsonPath('data.servings', 4)
            ->assertJsonPath('data.prep_minutes', 10)
            ->assertJsonPath('data.cook_minutes', 15)
            ->assertJsonPath('data.total_minutes', 25)
            ->assertJsonPath('data.cuisine', 'American')
            ->assertJsonPath('data.source_url', 'https://recipes.test/pancakes')
            ->assertJsonCount(3, 'data.ingredients')
            ->assertJsonCount(3, 'data.steps')
            ->assertJsonPath('data.steps.0.instruction', 'Mix the dry ingredients.')
            ->assertJsonPath('data.nutrition.calories', 320);

        $this->assertDatabaseHas('recipes', ['title' => 'Test Pancakes', 'slug' => 'test-pancakes']);
        $this->assertDatabaseHas('ingredients', ['name' => '2 cups flour']);
        $this->assertEqualsCanonicalizing(
            ['breakfast', 'easy', 'brunch'],
            $response->json('data.tags'),
        );
    }

    public function test_it_returns_422_when_no_recipe_data_is_present(): void
    {
        Http::fake([
            'recipes.test/*' => Http::response('<html><body>Just a blog post.</body></html>'),
        ]);

        $this->postJson('/api/recipes/import', ['url' => 'https://recipes.test/no-recipe'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'No recipe data (schema.org/Recipe) was found on that page.');
    }

    public function test_it_validates_the_url(): void
    {
        $this->postJson('/api/recipes/import', ['url' => 'not-a-url'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('url');
    }

    public function test_it_requires_a_url_or_html(): void
    {
        $this->postJson('/api/recipes/import', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['url', 'html']);
    }

    public function test_it_imports_from_pasted_html_without_fetching(): void
    {
        Http::fake(fn () => throw new \RuntimeException('HTTP should not be called for pasted HTML'));

        $response = $this->postJson('/api/recipes/import', [
            'html' => $this->pageWithRecipeJsonLd(),
            'url' => 'https://recipes.test/pancakes',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Test Pancakes')
            ->assertJsonPath('data.source_url', 'https://recipes.test/pancakes');
    }
}
