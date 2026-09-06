<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'servings' => $this->servings,
            'prep_minutes' => $this->prep_minutes,
            'cook_minutes' => $this->cook_minutes,
            'total_minutes' => $this->total_minutes,
            'difficulty' => $this->difficulty,
            'cuisine' => $this->cuisine,
            'image_url' => $this->image_url,
            'source_url' => $this->source_url,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'ingredients' => $this->recipeIngredients->map(fn ($ri) => [
                'id' => $ri->id,
                'ingredient_id' => $ri->ingredient_id,
                'name' => $ri->ingredient->name,
                'category' => $ri->ingredient->category,
                'quantity' => $ri->quantity,
                'unit' => $ri->unit,
                'notes' => $ri->notes,
                'sort_order' => $ri->sort_order,
            ]),
            'steps' => $this->steps->map(fn ($step) => [
                'id' => $step->id,
                'step_number' => $step->step_number,
                'instruction' => $step->instruction,
                'image_url' => $step->image_url,
            ]),
            'tags' => $this->tags->pluck('name'),
            'nutrition' => $this->nutrition ? [
                'calories' => $this->nutrition->calories,
                'protein_g' => $this->nutrition->protein_g,
                'carbs_g' => $this->nutrition->carbs_g,
                'fat_g' => $this->nutrition->fat_g,
            ] : null,
        ];
    }
}
