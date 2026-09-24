<?php

namespace Tests\Feature;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecipeImageTest extends TestCase
{
    use RefreshDatabase;

    private function recipe(array $attributes = []): Recipe
    {
        return Recipe::create(['title' => 'Pancakes', 'slug' => 'pancakes', ...$attributes]);
    }

    public function test_guests_cannot_change_the_image(): void
    {
        $recipe = $this->recipe();

        $this->postJson("/api/recipes/{$recipe->id}/image", ['image_url' => 'https://example.com/a.jpg'])
            ->assertUnauthorized();
    }

    public function test_image_can_be_replaced_with_a_url(): void
    {
        $this->actingAs(User::factory()->create());
        $recipe = $this->recipe(['image_url' => 'https://example.com/old.jpg']);

        $this->postJson("/api/recipes/{$recipe->id}/image", ['image_url' => 'https://example.com/new.jpg'])
            ->assertOk()
            ->assertJsonPath('data.image_url', 'https://example.com/new.jpg');
    }

    public function test_image_can_be_replaced_with_an_upload_and_old_upload_is_removed(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $recipe = $this->recipe();

        $this->post("/api/recipes/{$recipe->id}/image", ['image' => UploadedFile::fake()->image('first.jpg')])->assertOk();
        $first = $recipe->fresh()->image_url;

        $this->post("/api/recipes/{$recipe->id}/image", ['image' => UploadedFile::fake()->image('second.jpg')])->assertOk();

        Storage::disk('public')->assertMissing('recipe-images/'.basename($first));
        Storage::disk('public')->assertExists('recipe-images/'.basename($recipe->fresh()->image_url));
    }

    public function test_non_image_upload_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $recipe = $this->recipe();

        $this->postJson("/api/recipes/{$recipe->id}/image", ['image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
            ->assertUnprocessable();
    }
}
