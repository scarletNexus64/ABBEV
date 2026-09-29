<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Génère un `slug` unique à la création, à partir du champ déclaré par
 * `slugSource()` (le titre, le plus souvent).
 *
 * Le slug n'est PAS réécrit quand le titre change : il sert de clé stable
 * (liens partagés, dédoublonnage des seeders) et doit survivre aux
 * reformulations éditoriales.
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function ($model) {
            if (blank($model->slug)) {
                $model->slug = static::uniqueSlugFor((string) $model->slugSource());
            }
        });
    }

    /** Valeur à partir de laquelle le slug est construit. */
    abstract protected function slugSource(): string;

    public static function uniqueSlugFor(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: Str::lower(Str::random(8));
        $slug = $base;
        $n = 1;

        // Unicité sur toute la table, tous espaces producteurs confondus.
        while (static::query()->withoutGlobalScope('workspace')
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base . '-' . (++$n);
        }

        return $slug;
    }
}
