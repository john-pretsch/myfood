<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(Request $request)
    {
        $tags = Tag::query()
            ->withCount('recipes')
            ->when($request->string('search')->toString(), fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->when(
                $request->query('sort') === 'popular',
                fn ($query) => $query->orderByDesc('recipes_count')->orderBy('name'),
                fn ($query) => $query->orderBy('name'),
            )
            ->get(['id', 'name']);

        return ['data' => $tags->map(fn (Tag $tag) => [
            'id' => $tag->id,
            'name' => $tag->name,
            'recipes_count' => $tag->recipes_count,
        ])];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:tags,name'],
        ]);

        $tag = Tag::create(['name' => trim($data['name'])]);

        return response()->json(['data' => $tag->only('id', 'name')], 201);
    }

    public function destroy(Tag $tag)
    {
        $tag->delete();

        return response()->noContent();
    }
}
