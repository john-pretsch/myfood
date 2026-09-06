<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeResource;
use App\Http\Resources\RecipeSummaryResource;
use App\Models\Ingredient;
use App\Models\Nutrition;
use App\Models\Recipe;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RecipeController extends Controller
{
    public function index(Request $request)
    {
        $recipes = Recipe::query()
            ->with('tags')
            ->when($request->string('search')->toString(), fn ($query, $search) => $query->where('title', 'like', "%{$search}%"))
            ->when($request->string('tag')->toString(), fn ($query, $tag) => $query->whereHas('tags', fn ($q) => $q->where('name', $tag)))
            ->latest()
            ->paginate(12);

        return RecipeSummaryResource::collection($recipes);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $recipe = Recipe::create([
            ...$data,
            'slug' => $this->uniqueSlug($data['title']),
        ]);

        $this->syncRelations($recipe, $data);

        return new RecipeResource($recipe->load(['recipeIngredients.ingredient', 'steps', 'tags', 'nutrition']));
    }

    public function show(Recipe $recipe)
    {
        return new RecipeResource($recipe->load(['recipeIngredients.ingredient', 'steps', 'tags', 'nutrition']));
    }

    public function update(Request $request, Recipe $recipe)
    {
        $data = $this->validated($request);

        $recipe->update($data);

        $this->syncRelations($recipe, $data);

        return new RecipeResource($recipe->load(['recipeIngredients.ingredient', 'steps', 'tags', 'nutrition']));
    }

    public function destroy(Recipe $recipe)
    {
        $recipe->delete();

        return response()->noContent();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'servings' => ['nullable', 'integer', 'min:1'],
            'prep_minutes' => ['nullable', 'integer', 'min:0'],
            'cook_minutes' => ['nullable', 'integer', 'min:0'],
            'total_minutes' => ['nullable', 'integer', 'min:0'],
            'difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard'])],
            'cuisine' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'source_url' => ['nullable', 'string', 'max:2048'],
            'notes' => ['nullable', 'string'],
            'ingredients' => ['array'],
            'ingredients.*.name' => ['required_with:ingredients', 'string', 'max:255'],
            'ingredients.*.quantity' => ['nullable', 'numeric'],
            'ingredients.*.unit' => ['nullable', 'string', 'max:50'],
            'ingredients.*.notes' => ['nullable', 'string', 'max:255'],
            'steps' => ['array'],
            'steps.*.instruction' => ['required_with:steps', 'string'],
            'steps.*.image_url' => ['nullable', 'string', 'max:2048'],
            'tags' => ['array'],
            'tags.*' => ['string', 'max:100'],
            'nutrition' => ['nullable', 'array'],
            'nutrition.calories' => ['nullable', 'integer', 'min:0'],
            'nutrition.protein_g' => ['nullable', 'numeric', 'min:0'],
            'nutrition.carbs_g' => ['nullable', 'numeric', 'min:0'],
            'nutrition.fat_g' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function syncRelations(Recipe $recipe, array $data): void
    {
        $recipe->recipeIngredients()->delete();

        foreach ($data['ingredients'] ?? [] as $index => $row) {
            $ingredient = Ingredient::firstOrCreate(['name' => trim($row['name'])]);

            $recipe->recipeIngredients()->create([
                'ingredient_id' => $ingredient->id,
                'quantity' => $row['quantity'] ?? null,
                'unit' => $row['unit'] ?? null,
                'notes' => $row['notes'] ?? null,
                'sort_order' => $index,
            ]);
        }

        $recipe->steps()->delete();

        foreach ($data['steps'] ?? [] as $index => $row) {
            $recipe->steps()->create([
                'step_number' => $index + 1,
                'instruction' => $row['instruction'],
                'image_url' => $row['image_url'] ?? null,
            ]);
        }

        $tagIds = collect($data['tags'] ?? [])
            ->filter()
            ->map(fn ($name) => Tag::firstOrCreate(['name' => trim($name)])->id);

        $recipe->tags()->sync($tagIds);

        if (! empty(array_filter($data['nutrition'] ?? []))) {
            Nutrition::updateOrCreate(
                ['recipe_id' => $recipe->id],
                $data['nutrition'],
            );
        } else {
            $recipe->nutrition()->delete();
        }
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 1;

        while (Recipe::where('slug', $slug)->exists()) {
            $slug = "{$base}-".++$suffix;
        }

        return $slug;
    }
}
