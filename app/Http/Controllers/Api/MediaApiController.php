<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\MovieResource;
use App\Http\Resources\SerieResource;
use App\Models\Category;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints publics consommés par l'app Flutter.
 *
 * Conventions :
 * - Pagination via `?page=` et `?per_page=`.
 * - "popular" trie sur views_count décroissant.
 * - "trending" = populaire sur la fenêtre récente (publié dans les 30 derniers jours).
 * - "new-releases" = trié par published_at desc.
 * - "featured" = is_featured = 1.
 * - Les ressources renvoient des URLs absolues (videoUrl, posterUrl, ...).
 */
class MediaApiController extends Controller
{
    // ============================================================
    //   Compat : ancienne liste générique
    // ============================================================
    public function index(Request $request): JsonResponse
    {
        $q = Media::with('category')->published();

        if ($request->filled('type')) {
            $q->where('type', $request->type);
        }
        if ($request->filled('category_id')) {
            $q->where('category_id', $request->category_id);
        }
        if ($request->filled('is_featured')) {
            $q->where('is_featured', (bool) $request->is_featured);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn ($x) => $x->where('title', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%"));
        }

        $items = $q->latest('published_at')->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => collect($items->items())->map(fn ($m) => $m->type === 'series'
                ? (new SerieResource($m))->resolve()
                : (new MovieResource($m))->resolve()),
            'meta' => $this->paginationMeta($items),
        ]);
    }

    // ============================================================
    //   Films
    // ============================================================
    public function movies(Request $request): JsonResponse
    {
        $items = $this->baseMovieQuery($request)->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => MovieResource::collection($items->items()),
            'meta' => $this->paginationMeta($items),
        ]);
    }

    public function popularMovies(Request $request): JsonResponse
    {
        $items = $this->baseMovieQuery($request)
            ->orderByDesc('views_count')
            ->limit($request->get('limit', 20))
            ->get();

        return response()->json(['data' => MovieResource::collection($items)]);
    }

    public function trendingMovies(Request $request): JsonResponse
    {
        $items = $this->baseMovieQuery($request)
            ->where('published_at', '>=', now()->subDays(30))
            ->orderByDesc('views_count')
            ->limit($request->get('limit', 20))
            ->get();

        if ($items->count() < 4) {
            $items = $this->baseMovieQuery($request)
                ->orderByDesc('views_count')
                ->limit($request->get('limit', 20))
                ->get();
        }

        return response()->json(['data' => MovieResource::collection($items)]);
    }

    public function newReleases(Request $request): JsonResponse
    {
        $items = $this->baseMovieQuery($request)
            ->orderByDesc('published_at')
            ->limit($request->get('limit', 20))
            ->get();

        return response()->json(['data' => MovieResource::collection($items)]);
    }

    public function featuredMovies(Request $request): JsonResponse
    {
        $items = $this->baseMovieQuery($request)
            ->where('is_featured', true)
            ->orderByDesc('published_at')
            ->limit($request->get('limit', 10))
            ->get();

        return response()->json(['data' => MovieResource::collection($items)]);
    }

    public function movieShow(Media $movie): JsonResponse
    {
        abort_if($movie->type !== 'movie', 404);
        abort_unless($movie->isApproved(), 404); // contenu en modération invisible
        $movie->load('category');
        $movie->increment('views_count');

        return response()->json(['data' => new MovieResource($movie)]);
    }

    public function moviesByCategory(Request $request, Category $category): JsonResponse
    {
        $items = $this->baseMovieQuery($request)
            ->where('category_id', $category->id)
            ->orderByDesc('published_at')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'category' => new CategoryResource($category),
            'data' => MovieResource::collection($items->items()),
            'meta' => $this->paginationMeta($items),
        ]);
    }

    // ============================================================
    //   Séries
    // ============================================================
    public function series(Request $request): JsonResponse
    {
        $items = $this->baseSerieQuery($request)->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => SerieResource::collection($items->items()),
            'meta' => $this->paginationMeta($items),
        ]);
    }

    public function popularSeries(Request $request): JsonResponse
    {
        $items = $this->baseSerieQuery($request)
            ->orderByDesc('views_count')
            ->limit($request->get('limit', 20))
            ->get();

        return response()->json(['data' => SerieResource::collection($items)]);
    }

    public function featuredSeries(Request $request): JsonResponse
    {
        $items = $this->baseSerieQuery($request)
            ->where('is_featured', true)
            ->orderByDesc('published_at')
            ->limit($request->get('limit', 10))
            ->get();

        return response()->json(['data' => SerieResource::collection($items)]);
    }

    public function serieShow(Media $series): JsonResponse
    {
        abort_if($series->type !== 'series', 404);
        abort_unless($series->isApproved(), 404); // contenu en modération invisible
        $series->load(['category', 'seasonsRelation.episodes']);
        $series->increment('views_count');

        return response()->json(['data' => new SerieResource($series)]);
    }

    public function seriesByCategory(Request $request, Category $category): JsonResponse
    {
        $items = $this->baseSerieQuery($request)
            ->where('category_id', $category->id)
            ->orderByDesc('published_at')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'category' => new CategoryResource($category),
            'data' => SerieResource::collection($items->items()),
            'meta' => $this->paginationMeta($items),
        ]);
    }

    // ============================================================
    //   Catégories / Recherche / Featured global
    // ============================================================
    public function categories(): JsonResponse
    {
        $cats = Category::withCount('media')->orderBy('name')->get();

        return response()->json(['data' => CategoryResource::collection($cats)]);
    }

    public function categoryMedia(Request $request, Category $category): JsonResponse
    {
        $items = Media::with('category')
            ->where('category_id', $category->id)
            ->published()
            ->orderByDesc('published_at')
            ->paginate($request->get('per_page', 20));

        $movies = collect($items->items())->where('type', 'movie')->values();
        $series = collect($items->items())->where('type', 'series')->values();

        return response()->json([
            'category' => new CategoryResource($category),
            'movies' => MovieResource::collection($movies),
            'series' => SerieResource::collection($series),
            'meta' => $this->paginationMeta($items),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = (string) $request->get('q', $request->get('query', ''));
        $type = $request->get('type');

        $base = Media::with('category')->published();
        if ($q !== '') {
            $base->where(function ($x) use ($q) {
                $x->where('title', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%");
            });
        }
        if ($type) {
            $base->where('type', $type);
        }

        $items = $base->orderByDesc('published_at')->limit(100)->get();

        return response()->json([
            'movies' => MovieResource::collection($items->where('type', 'movie')->values()),
            'series' => SerieResource::collection($items->where('type', 'series')->values()),
        ]);
    }

    public function featured(): JsonResponse
    {
        $items = Media::with('category')
            ->where('is_featured', true)
            ->published()
            ->orderByDesc('published_at')
            ->limit(20)
            ->get();

        return response()->json([
            'movies' => MovieResource::collection($items->where('type', 'movie')->values()),
            'series' => SerieResource::collection($items->where('type', 'series')->values()),
        ]);
    }

    // ============================================================
    //   Compat ancienne route /media/{slug}
    // ============================================================
    public function show($slug): JsonResponse
    {
        $media = Media::with(['category', 'seasonsRelation.episodes'])
            ->where('slug', $slug)
            ->published()
            ->firstOrFail();

        $media->increment('views_count');

        if ($media->type === 'series') {
            return response()->json(['data' => new SerieResource($media)]);
        }

        return response()->json(['data' => new MovieResource($media)]);
    }

    // ============================================================
    //   Helpers
    // ============================================================
    protected function baseMovieQuery(Request $request)
    {
        $q = Media::with('category')
            ->where('type', 'movie')
            ->published();
        if ($request->filled('category_id')) {
            $q->where('category_id', $request->category_id);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn ($x) => $x->where('title', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%"));
        }

        return $q;
    }

    protected function baseSerieQuery(Request $request)
    {
        $q = Media::with(['category', 'seasonsRelation'])
            ->where('type', 'series')
            ->published();
        if ($request->filled('category_id')) {
            $q->where('category_id', $request->category_id);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn ($x) => $x->where('title', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%"));
        }

        return $q;
    }

    protected function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
