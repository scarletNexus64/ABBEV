<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesMediaUrls;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Appel à projets (financement, écriture, musique).
 *
 * Les champs propres à une famille valent `null` pour les autres : l'app lit
 * `type` et n'affiche que ce qui la concerne.
 */
class ProjectCallResource extends JsonResource
{
    use ResolvesMediaUrls;

    private bool $detailed = false;

    /** Participation de l'utilisateur connecté (candidature ou promesse). */
    private ?array $mine = null;

    public function detailed(?array $mine = null): static
    {
        $this->detailed = true;
        $this->mine = $mine;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $money = Money::meta($this->currency);
        $funding = $this->isFunding();

        $base = [
            'id' => (int) $this->id,
            'slug' => $this->slug,
            'type' => $this->type,
            'target' => $this->target,
            'title' => $this->t('title'),
            'summary' => $this->t('summary'),
            'cover_url' => $this->absoluteUrl($this->cover_path),
            'organizer' => $this->organizer,
            'opens_at' => $this->opens_at?->toIso8601String(),
            'closes_at' => $this->closes_at?->toIso8601String(),
            'status' => $this->status,
            'is_open' => $this->isOpen(),
            'is_featured' => (bool) $this->is_featured,
            'prize' => $funding ? null : $this->prize,
            // Financement : objectif, montant réuni, progression.
            'currency' => $this->currency,
            'currency_symbol' => $money['symbol'],
            'currency_decimals' => $money['decimals'],
            'goal_amount' => $funding && $this->goal_amount !== null ? (float) $this->goal_amount : null,
            'raised_amount' => $funding ? $this->raisedAmount() : null,
            'progress_percent' => $funding ? $this->progressPercent() : null,
            'backers_count' => $funding ? $this->backersCount() : null,
            'entries_count' => $funding ? null : (int) ($this->submissions_count ?? $this->submissions()->count()),
        ];

        if (! $this->detailed) {
            return $base;
        }

        return $base + [
            'description' => $this->t('description'),
            'requirements' => $this->t('requirements'),
            'genre' => $this->genre,
            'max_pages' => $this->max_pages,
            'music_style' => $this->music_style,
            'max_duration_minutes' => $this->max_duration_minutes,
            'min_pledge' => $funding && $this->min_pledge !== null ? (float) $this->min_pledge : null,
            'rewards' => $funding
                ? collect($this->rewards ?? [])->map(fn ($r) => [
                    'amount' => isset($r['amount']) ? (float) $r['amount'] : null,
                    'title' => (string) ($r['title'] ?? ''),
                    'description' => (string) ($r['description'] ?? ''),
                ])->filter(fn ($r) => $r['title'] !== '')->values()
                : [],
            'rules_url' => $this->rules_url,
            'my_participation' => $this->mine,
        ];
    }
}
