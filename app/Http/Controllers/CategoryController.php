<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\Concerns\SavesTranslations;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * GENRES du catalogue (cat.md : 15 genres).
 *
 * Suppression sous contrôle : `media.category_id` est en ON DELETE CASCADE,
 * supprimer un genre qui porte encore des films les effacerait. Un genre non
 * vide ne se supprime donc qu'en désignant le genre qui recueille ses
 * contenus.
 */
class CategoryController extends Controller
{
    use SavesTranslations;

    private const TRANSLATABLE = ['name', 'description'];

    public function index()
    {
        $genres = Category::genres()
            ->withCount([
                'media',
                'media as movies_count' => fn ($q) => $q->where('type', 'movie'),
                'media as series_count' => fn ($q) => $q->where('type', 'series'),
            ])
            ->with('translations')
            ->get();

        return view('categories.index', [
            'genres' => $genres,
            'reference' => Category::REFERENCE_GENRES,
            'missing' => collect(Category::REFERENCE_GENRES)
                ->keys()
                ->diff($genres->pluck('slug'))
                ->values(),
        ]);
    }

    public function create()
    {
        return view('categories.create', ['category' => new Category()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $category = Category::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'family' => Category::GENRE,
            'description' => $data['description'] ?? null,
            'sort_order' => (int) Category::where('family', Category::GENRE)->max('sort_order') + 10,
        ]);
        $this->saveEnglish($category, $request, self::TRANSLATABLE);

        return redirect()->route('categories.index')
            ->with('success', "Genre « {$category->name} » créé.");
    }

    public function edit(Category $category)
    {
        $category->load('translations')->loadCount('media');

        return view('categories.edit', [
            'category' => $category,
            'others' => Category::genres()->whereKeyNot($category->id)->get(),
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validated($request, $category);

        // Le slug ne change pas : il identifie le genre dans le référentiel
        // cat.md et dans les traductions.
        $category->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);
        $this->saveEnglish($category, $request, self::TRANSLATABLE);

        return redirect()->route('categories.index')
            ->with('success', "Genre « {$category->name} » mis à jour.");
    }

    public function destroy(Request $request, Category $category)
    {
        $count = $category->media()->count();

        if ($count > 0) {
            $target = Category::where('family', Category::GENRE)
                ->whereKeyNot($category->id)
                ->find($request->input('move_to'));

            if (! $target) {
                return back()->with('error', "« {$category->name} » contient {$count} contenu(s) : choisissez le genre qui les recevra avant de le supprimer.");
            }

            DB::transaction(function () use ($category, $target) {
                $category->media()->update(['category_id' => $target->id]);
                $category->translations()->delete();
                $category->delete();
            });

            return redirect()->route('categories.index')
                ->with('success', "Genre supprimé : ses {$count} contenu(s) ont rejoint « {$target->name} ».");
        }

        $category->translations()->delete();
        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Genre supprimé.');
    }

    /** POST /categories/reorder — ordre d'affichage (liste d'ids). */
    public function reorder(Request $request)
    {
        $ids = $request->validate(['order' => 'required|array', 'order.*' => 'integer'])['order'];

        foreach (array_values($ids) as $i => $id) {
            Category::whereKey($id)->update(['sort_order' => ($i + 1) * 10]);
        }

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('categories', 'name')->ignore($category?->id)],
            'description' => 'nullable|string|max:500',
        ] + $this->translationRules(self::TRANSLATABLE));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'genre';
        $slug = $base;
        $n = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$n);
        }

        return $slug;
    }
}
