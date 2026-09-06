<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'servings',
        'prep_minutes',
        'cook_minutes',
        'total_minutes',
        'difficulty',
        'cuisine',
        'image_url',
        'source_url',
        'notes',
    ];

    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class)->orderBy('sort_order');
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'recipe_ingredients')
            ->withPivot(['quantity', 'unit', 'notes', 'sort_order'])
            ->withTimestamps();
    }

    public function steps(): HasMany
    {
        return $this->hasMany(Step::class)->orderBy('step_number');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function nutrition(): HasOne
    {
        return $this->hasOne(Nutrition::class);
    }
}
