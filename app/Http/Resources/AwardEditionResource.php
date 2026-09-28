<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesMediaUrls;
use App\Models\AwardCategory;
use App\Models\AwardNominee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Édition des Lions Head Awards, avec tous ses prix et nommés en une seule
 * réponse (l'écran de vote les parcourt tous).
 *
 * Les décomptes de voix ne sortent JAMAIS avant la publication des
 * résultats : afficher une tendance pendant le vote ferait voter pour le
 * favori plutôt que pour son préféré.
 */
class AwardEditionResource extends JsonResource
{
    use ResolvesMediaUrls;

    /** @var array<int, int> id de catégorie => id du nommé choisi */
    private array $myVotes = [];

    public function withVotesOf(array $myVotes): static
    {
        $this->myVotes = $myVotes;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $public = $this->resultsArePublic();

        return [
            'id' => (int) $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'year' => (int) $this->year,
            'tagline' => $this->t('tagline'),
            'description' => $this->t('description'),
            'cover_url' => $this->absoluteUrl($this->cover_path),
            'status' => $this->status(),
            'voting_starts_at' => $this->voting_starts_at?->toIso8601String(),
            'voting_ends_at' => $this->voting_ends_at?->toIso8601String(),
            'ceremony_at' => $this->ceremony_at?->toIso8601String(),
            'ceremony_venue' => $this->ceremony_venue,
            'results_published' => $public,
            'my_votes_count' => count($this->myVotes),
            // Un prix sans nommé n'est pas encore votable : il reste masqué.
            'categories' => $this->categories
                ->filter(fn (AwardCategory $category) => $category->nominees->isNotEmpty())
                ->map(fn (AwardCategory $category) => $this->category($category, $public))
                ->values(),
        ];
    }

    private function category(AwardCategory $category, bool $public): array
    {
        $total = $public ? max(1, (int) $category->nominees->sum('votes_count')) : 0;

        return [
            'id' => (int) $category->id,
            'slug' => $category->slug,
            'name' => $category->t('name'),
            'description' => $category->t('description'),
            'scope' => $category->scope,
            'nominee_type' => $category->nominee_type,
            'my_vote' => $this->myVotes[$category->id] ?? null,
            'total_votes' => $public ? (int) $category->nominees->sum('votes_count') : null,
            'nominees' => $category->nominees->map(fn (AwardNominee $n) => [
                'id' => (int) $n->id,
                'name' => $n->name,
                'subtitle' => $n->subtitle,
                'image_url' => $this->absoluteUrl($n->imagePath()),
                'media_id' => $n->media_id ? (string) $n->media_id : null,
                'media_type' => $n->media?->type,
                'talent_id' => $n->talent_id ? (int) $n->talent_id : null,
                'is_winner' => $public && (bool) $n->is_winner,
                'votes' => $public ? (int) $n->votes_count : null,
                'percent' => $public ? round($n->votes_count * 100 / $total, 1) : null,
            ])->values(),
        ];
    }
}
