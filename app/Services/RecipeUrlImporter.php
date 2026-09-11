<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class RecipeUrlImporter
{
    /**
     * Fetch a URL and build recipe payload data from its schema.org JSON-LD.
     *
     * @return array<string, mixed>
     */
    public function import(string $url): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; MyFoodBot/1.0; +https://myfood.jepflow.io)',
                'Accept' => 'text/html,application/xhtml+xml',
            ])->timeout(15)->get($url);
        } catch (ConnectionException) {
            throw new RuntimeException('Could not reach that URL.');
        }

        if (! $response->successful()) {
            throw new RuntimeException("Could not fetch the page (HTTP {$response->status()}). Some sites block automated requests — try pasting the page's saved HTML instead.");
        }

        return $this->importFromHtml($response->body(), $url);
    }

    /**
     * Parse recipe data from HTML the caller already has (e.g. pasted page
     * source), without fetching anything. Useful for sites whose bot
     * protection blocks server-side requests but not a normal page load.
     */
    public function importFromHtml(string $html, ?string $sourceUrl = null): array
    {
        $recipe = $this->findRecipeNode($this->extractJsonLd($html));

        if ($recipe === null) {
            throw new RuntimeException('No recipe data (schema.org/Recipe) was found on that page.');
        }

        return $this->map($recipe, $sourceUrl);
    }

    /**
     * Decode every <script type="application/ld+json"> block in the HTML.
     *
     * @return array<int, mixed>
     */
    private function extractJsonLd(string $html): array
    {
        if (! preg_match_all('#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $matches)) {
            return [];
        }

        $documents = [];

        foreach ($matches[1] as $raw) {
            $decoded = json_decode(trim(html_entity_decode($raw)), true);

            if (is_array($decoded)) {
                $documents[] = $decoded;
            }
        }

        return $documents;
    }

    /**
     * @param  array<int, mixed>  $documents
     * @return array<string, mixed>|null
     */
    private function findRecipeNode(array $documents): ?array
    {
        foreach ($documents as $document) {
            if ($found = $this->searchForRecipe($document)) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function searchForRecipe(mixed $node): ?array
    {
        if (! is_array($node)) {
            return null;
        }

        $types = array_map('strtolower', array_map('strval', Arr::wrap($node['@type'] ?? null)));

        if (in_array('recipe', $types, true)) {
            return $node;
        }

        foreach ($node as $value) {
            if (is_array($value) && $found = $this->searchForRecipe($value)) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $recipe
     * @return array<string, mixed>
     */
    private function map(array $recipe, ?string $sourceUrl): array
    {
        $prep = $this->minutes($recipe['prepTime'] ?? null);
        $cook = $this->minutes($recipe['cookTime'] ?? null);
        $total = $this->minutes($recipe['totalTime'] ?? null)
            ?? (($prep === null && $cook === null) ? null : (int) $prep + (int) $cook);

        return [
            'title' => Str::limit($this->text($recipe['name'] ?? null) ?? 'Untitled recipe', 255, ''),
            'description' => $this->text($recipe['description'] ?? null),
            'servings' => $this->servings($recipe['recipeYield'] ?? null),
            'prep_minutes' => $prep,
            'cook_minutes' => $cook,
            'total_minutes' => $total,
            'difficulty' => null,
            'cuisine' => $this->text(Arr::first(Arr::wrap($recipe['recipeCuisine'] ?? null))),
            'image_url' => $this->image($recipe['image'] ?? null),
            'source_url' => $sourceUrl,
            'notes' => null,
            'ingredients' => $this->ingredients($recipe['recipeIngredient'] ?? $recipe['ingredients'] ?? []),
            'steps' => $this->steps($recipe['recipeInstructions'] ?? []),
            'tags' => $this->tags($recipe['keywords'] ?? null),
            'nutrition' => $this->nutrition($recipe['nutrition'] ?? null),
        ];
    }

    private function text(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['@value'] ?? $value['name'] ?? Arr::first($value);
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $clean = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $value))));

        return $clean === '' ? null : $clean;
    }

    private function minutes(mixed $duration): ?int
    {
        if (! is_string($duration) || ! preg_match('/^P(?:([\d.]+)D)?(?:T(?:([\d.]+)H)?(?:([\d.]+)M)?(?:([\d.]+)S)?)?$/i', $duration, $m)) {
            return null;
        }

        $total = (int) round(
            (float) ($m[1] ?? 0) * 1440
            + (float) ($m[2] ?? 0) * 60
            + (float) ($m[3] ?? 0)
            + (float) ($m[4] ?? 0) / 60
        );

        return $total > 0 ? $total : null;
    }

    private function servings(mixed $yield): ?int
    {
        if (is_array($yield)) {
            $yield = Arr::first($yield);
        }

        return preg_match('/\d+/', (string) $yield, $m) ? ((int) $m[0] ?: null) : null;
    }

    private function image(mixed $image): ?string
    {
        if (is_string($image)) {
            return Str::limit($image, 2048, '') ?: null;
        }

        if (is_array($image)) {
            $url = $image['url'] ?? (is_array($first = Arr::first($image)) ? ($first['url'] ?? null) : $first);

            return is_string($url) ? Str::limit($url, 2048, '') : null;
        }

        return null;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function ingredients(mixed $ingredients): array
    {
        return collect(Arr::wrap($ingredients))
            ->map(fn ($line) => $this->text($line))
            ->filter()
            ->map(fn ($name) => ['name' => Str::limit($name, 255, '')])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function steps(mixed $instructions): array
    {
        $steps = [];
        $this->flattenInstructions($instructions, $steps);

        return array_map(fn ($text) => ['instruction' => $text], $steps);
    }

    /**
     * @param  array<int, string>  $steps
     */
    private function flattenInstructions(mixed $node, array &$steps): void
    {
        if (is_string($node)) {
            foreach (preg_split('/\r\n|\r|\n/', $node) as $line) {
                if ($text = $this->text($line)) {
                    $steps[] = $text;
                }
            }

            return;
        }

        if (! is_array($node)) {
            return;
        }

        $type = strtolower((string) Arr::first(Arr::wrap($node['@type'] ?? '')));

        if ($type === 'howtostep' || (isset($node['text']) && ! isset($node['itemListElement']))) {
            if ($text = $this->text($node['text'] ?? $node['name'] ?? null)) {
                $steps[] = $text;
            }

            return;
        }

        foreach ($node['itemListElement'] ?? $node as $child) {
            $this->flattenInstructions($child, $steps);
        }
    }

    /**
     * @return array<int, string>
     */
    private function tags(mixed $keywords): array
    {
        if (is_string($keywords)) {
            $keywords = explode(',', $keywords);
        }

        return collect(Arr::wrap($keywords))
            ->map(fn ($tag) => $this->text($tag))
            ->filter()
            ->map(fn ($tag) => Str::limit($tag, 100, ''))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, int|float>
     */
    private function nutrition(mixed $nutrition): array
    {
        if (! is_array($nutrition)) {
            return [];
        }

        $values = [
            'calories' => $this->number($nutrition['calories'] ?? null),
            'protein_g' => $this->number($nutrition['proteinContent'] ?? null),
            'carbs_g' => $this->number($nutrition['carbohydrateContent'] ?? null),
            'fat_g' => $this->number($nutrition['fatContent'] ?? null),
        ];

        if ($values['calories'] !== null) {
            $values['calories'] = (int) $values['calories'];
        }

        return array_filter($values, fn ($v) => $v !== null);
    }

    private function number(mixed $value): int|float|null
    {
        if ((! is_string($value) && ! is_numeric($value)) || ! preg_match('/[\d.]+/', (string) $value, $m)) {
            return null;
        }

        return str_contains($m[0], '.') ? (float) $m[0] : (int) $m[0];
    }
}
