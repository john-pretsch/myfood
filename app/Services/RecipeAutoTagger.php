<?php

namespace App\Services;

use App\Models\Tag;
use Illuminate\Support\Str;

/**
 * Suggests existing tags for a new recipe by keyword matching its text. Deliberately greedy:
 * a single keyword hit is enough. It never creates tags, only picks from those admins made.
 */
class RecipeAutoTagger
{
    private const MEAT = [
        'meat', 'beef', 'steak', 'pork', 'bacon', 'ham', 'sausage', 'chicken', 'turkey', 'duck', 'lamb', 'veal',
        'mince', 'chorizo', 'prosciutto', 'salami', 'pepperoni', 'brisket', 'ribs', 'meatball', 'meatballs',
    ];

    private const FISH = [
        'fish', 'salmon', 'tuna', 'cod', 'haddock', 'trout', 'tilapia', 'halibut', 'sardine', 'anchovy', 'anchovies',
        'mackerel', 'shrimp', 'prawn', 'crab', 'lobster', 'scallop', 'mussel', 'clam', 'oyster', 'squid', 'seafood',
    ];

    /** Extra keywords per tag name (lowercase). A tag's own name always counts too. */
    private const SYNONYMS = [
        'meat' => self::MEAT,
        'fish' => self::FISH,
        'baking' => ['bake', 'baked', 'oven', 'preheat', 'bread', 'cake', 'cookie', 'muffin', 'pastry', 'dough', 'flour'],
        'soup' => ['broth', 'chowder', 'bisque', 'consomme'],
        'stew' => ['braise', 'braised', 'ragout', 'goulash', 'casserole'],
        'keto' => ['low carb', 'low-carb'],
        'no cook' => ['no-cook', 'no bake', 'no-bake', 'raw'],
        'vegetarian' => ['vegan', 'veggie', 'meatless'],
    ];

    /**
     * @param  array  $data  validated recipe data (title, ingredients, steps, nutrition, ...)
     * @return list<string> names of existing tags that fit
     */
    public function suggest(array $data): array
    {
        $corpus = $this->corpus($data);
        $ingredients = Str::lower(collect($data['ingredients'] ?? [])->pluck('name')->implode(' '));
        $carbs = $data['nutrition']['carbs_g'] ?? null;

        // Vegetarian is inferred from the absence of meat/fish, but only if we have ingredients to judge by.
        $meatless = $ingredients !== ''
            && ! $this->matches($ingredients.' '.Str::lower($data['title'] ?? ''), [...self::MEAT, ...self::FISH]);

        return Tag::pluck('name')
            ->filter(function (string $name) use ($corpus, $data, $carbs, $meatless) {
                $key = Str::lower($name);

                $hit = match ($key) {
                    'vegetarian' => $meatless,
                    'no cook' => ($data['cook_minutes'] ?? null) === 0,
                    'keto' => $carbs !== null && $carbs <= 10,
                    'ketoish' => $carbs !== null && $carbs <= 20,
                    default => false,
                };

                return $hit || $this->matches($corpus, $this->terms($key));
            })
            ->values()
            ->all();
    }

    private function terms(string $key): array
    {
        $singular = Str::singular($key);

        return array_values(array_unique([$key, $singular, ...(self::SYNONYMS[$key] ?? [])]));
    }

    private function corpus(array $data): string
    {
        return Str::lower(implode(' ', array_filter([
            $data['title'] ?? null,
            $data['description'] ?? null,
            $data['cuisine'] ?? null,
            $data['notes'] ?? null,
            collect($data['ingredients'] ?? [])->pluck('name')->implode(' '),
            collect($data['steps'] ?? [])->pluck('instruction')->implode(' '),
        ])));
    }

    private function matches(string $text, array $terms): bool
    {
        $pattern = '/\b('.implode('|', array_map(fn ($t) => preg_quote($t, '/'), $terms)).')(?:s|es)?\b/u';

        return (bool) preg_match($pattern, $text);
    }
}
