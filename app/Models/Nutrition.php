<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Nutrition extends Model
{
    use HasFactory;

    protected $table = 'nutrition';

    protected $fillable = [
        'recipe_id',
        'calories',
        'protein_g',
        'carbs_g',
        'fat_g',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
