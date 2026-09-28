<?php

namespace App\Support;

use App\Models\Media;
use Illuminate\Support\Facades\DB;

/**
 * Formats de durée des contenus (cat.md : « Film » et « Série et feuilleton »).
 *
 * Les seuils suivent l'usage du secteur :
 *  - FILMS — le CNC ne connaît que le court (< 60 min) et le long métrage
 *    (≥ 60 min) ; le « moyen métrage » désigne couramment la tranche
 *    30–59 min. D'où : court < 30 min ≤ moyen < 60 min ≤ long.
 *  - SÉRIES — on juge la durée d'un ÉPISODE : les programmes courts
 *    (shortcoms, web-séries) tiennent en quelques minutes, les feuilletons
 *    quotidiens et sitcoms en 20–26 min, les séries « 52 minutes » au-delà.
 *    D'où : très court < 13 min ≤ court ≤ 30 min < moyen.
 *
 * Les durées sont stockées en SECONDES dans `media.duration` et
 * `episodes.duration` (l'admin saisit des minutes, Bunny fournit des
 * secondes : les deux convergent à l'enregistrement).
 */
class MediaFormat
{
    public const MOVIE = ['court', 'moyen', 'long'];

    public const SERIES = ['tres-court', 'court', 'moyen'];

    /** Seuils films, en secondes. */
    private const MOVIE_MEDIUM_FROM = 30 * 60;
    private const MOVIE_LONG_FROM = 60 * 60;

    /** Seuils séries (durée moyenne d'un épisode), en secondes. */
    private const SERIES_SHORT_FROM = 13 * 60;
    private const SERIES_MEDIUM_ABOVE = 30 * 60;

    /** Formats valides pour un type de média (`movie` ou `series`). */
    public static function optionsFor(string $type): array
    {
        return $type === 'series' ? self::SERIES : self::MOVIE;
    }

    public static function isValid(string $type, ?string $format): bool
    {
        return $format !== null && in_array($format, self::optionsFor($type), true);
    }

    /** Libellé français (admin). L'app traduit elle-même le slug. */
    public static function label(string $type, ?string $format): string
    {
        return match ([$type === 'series' ? 'series' : 'movie', $format]) {
            ['movie', 'court'] => 'Court métrage',
            ['movie', 'moyen'] => 'Moyen métrage',
            ['movie', 'long'] => 'Long métrage',
            ['series', 'tres-court'] => 'Très court',
            ['series', 'court'] => 'Court',
            ['series', 'moyen'] => 'Moyen',
            default => '—',
        };
    }

    /** Repère de durée affiché à côté du libellé (« < 30 min », « 30–59 min »…). */
    public static function hint(string $type, string $format): string
    {
        return match ([$type === 'series' ? 'series' : 'movie', $format]) {
            ['movie', 'court'] => '< 30 min',
            ['movie', 'moyen'] => '30 – 59 min',
            ['movie', 'long'] => '≥ 60 min',
            ['series', 'tres-court'] => '< 13 min / épisode',
            ['series', 'court'] => '13 – 30 min / épisode',
            ['series', 'moyen'] => '> 30 min / épisode',
            default => '',
        };
    }

    /** Format d'un film d'après sa durée (secondes), ou null si inconnue. */
    public static function forMovieSeconds(?int $seconds): ?string
    {
        if (! $seconds || $seconds <= 0) {
            return null;
        }

        return match (true) {
            $seconds < self::MOVIE_MEDIUM_FROM => 'court',
            $seconds < self::MOVIE_LONG_FROM => 'moyen',
            default => 'long',
        };
    }

    /** Format d'une série d'après la durée moyenne d'un épisode (secondes). */
    public static function forEpisodeSeconds(?int $seconds): ?string
    {
        if (! $seconds || $seconds <= 0) {
            return null;
        }

        return match (true) {
            $seconds < self::SERIES_SHORT_FROM => 'tres-court',
            $seconds <= self::SERIES_MEDIUM_ABOVE => 'court',
            default => 'moyen',
        };
    }

    /**
     * Format qu'implique la durée du contenu, sans tenir compte d'un éventuel
     * choix manuel.
     */
    public static function infer(Media $media): ?string
    {
        if ($media->type === 'series') {
            return self::forEpisodeSeconds(self::averageEpisodeSeconds($media->id));
        }

        return self::forMovieSeconds($media->duration ? (int) $media->duration : null);
    }

    /**
     * Recalcule et enregistre le format d'un contenu, SAUF s'il a été fixé à
     * la main (`format_locked`). Sans écriture si rien ne change.
     */
    public static function refresh(Media $media): void
    {
        if ($media->format_locked) {
            return;
        }

        $format = self::infer($media);
        if ($format !== $media->format) {
            // `saveQuietly` : pas d'événement, donc pas de boucle si un
            // observateur du modèle rappelait ce calcul.
            $media->forceFill(['format' => $format])->saveQuietly();
        }
    }

    /** Recalcule le format de tout le catalogue (migration, commande). */
    public static function backfill(): void
    {
        Media::query()
            ->withoutGlobalScopes()
            ->where('format_locked', false)
            ->orderBy('id')
            ->chunkById(200, function ($chunk) {
                foreach ($chunk as $media) {
                    self::refresh($media);
                }
            });
    }

    /** Durée moyenne (secondes) des épisodes d'une série, ou null. */
    private static function averageEpisodeSeconds(int $mediaId): ?int
    {
        $avg = DB::table('episodes')
            ->join('seasons', 'seasons.id', '=', 'episodes.season_id')
            ->where('seasons.media_id', $mediaId)
            ->whereNotNull('episodes.duration')
            ->where('episodes.duration', '>', 0)
            ->avg('episodes.duration');

        return $avg !== null ? (int) round((float) $avg) : null;
    }
}
