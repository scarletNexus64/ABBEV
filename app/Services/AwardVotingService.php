<?php

namespace App\Services;

use App\Models\AwardCategory;
use App\Models\AwardEdition;
use App\Models\AwardNominee;
use App\Models\AwardVote;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Règles du vote des Lions Head Awards.
 *
 *  - Il faut un compte (l'OTP e-mail de l'inscription fait office de
 *    vérification d'identité) ; un compte = une voix par catégorie.
 *  - Tant que le vote est ouvert, on peut CHANGER d'avis : la voix est
 *    déplacée, jamais dupliquée.
 *  - Les compteurs `votes_count` évoluent dans la MÊME transaction que le
 *    vote, sous verrou : les résultats n'ont rien à recompter.
 */
class AwardVotingService
{
    public function cast(User $user, AwardNominee $nominee, ?string $ip = null): AwardVote
    {
        $category = $nominee->category()->with('edition')->firstOrFail();

        if (! $category->edition->isVotingOpen()) {
            throw new RuntimeException(__('messages.awards.voting_closed'));
        }

        try {
            return $this->record($user, $category, $nominee, $ip);
        } catch (UniqueConstraintViolationException) {
            // Deux premiers votes simultanés du même compte : l'un a gagné
            // la course à l'insertion. On rejoue, ce qui passe alors par la
            // branche « changement de vote ».
            return $this->record($user, $category, $nominee, $ip);
        }
    }

    private function record(User $user, AwardCategory $category, AwardNominee $nominee, ?string $ip): AwardVote
    {
        return DB::transaction(function () use ($user, $category, $nominee, $ip) {
            $existing = AwardVote::where('award_category_id', $category->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($existing && (int) $existing->award_nominee_id === (int) $nominee->id) {
                return $existing;
            }

            if ($existing) {
                AwardNominee::whereKey($existing->award_nominee_id)
                    ->where('votes_count', '>', 0)
                    ->decrement('votes_count');

                $existing->update(['award_nominee_id' => $nominee->id, 'ip_address' => $ip]);
                AwardNominee::whereKey($nominee->id)->increment('votes_count');

                return $existing->refresh();
            }

            $vote = AwardVote::create([
                'award_category_id' => $category->id,
                'award_nominee_id' => $nominee->id,
                'user_id' => $user->id,
                'ip_address' => $ip,
            ]);
            AwardNominee::whereKey($nominee->id)->increment('votes_count');

            return $vote;
        });
    }

    /**
     * Nommés d'une catégorie avec leur part des voix, du plus au moins voté.
     *
     * @return list<array{nominee: AwardNominee, votes: int, percent: float}>
     */
    public function standings(AwardCategory $category): array
    {
        $nominees = $category->relationLoaded('nominees')
            ? $category->nominees
            : $category->nominees()->get();
        $total = max(0, (int) $nominees->sum('votes_count'));

        return $nominees
            ->sortByDesc('votes_count')
            ->values()
            ->map(fn (AwardNominee $n) => [
                'nominee' => $n,
                'votes' => (int) $n->votes_count,
                'percent' => $total > 0 ? round($n->votes_count * 100 / $total, 1) : 0.0,
            ])
            ->all();
    }

    /**
     * Publie le palmarès. Sans choix du jury (aucun lauréat déjà désigné
     * dans une catégorie), le nommé le plus voté l'emporte ; les ex æquo
     * sont tous déclarés lauréats plutôt que départagés arbitrairement.
     */
    public function publishResults(AwardEdition $edition): void
    {
        DB::transaction(function () use ($edition) {
            foreach ($edition->categories()->with('nominees')->get() as $category) {
                if ($category->nominees->contains('is_winner', true)) {
                    continue;
                }

                $top = (int) $category->nominees->max('votes_count');
                if ($top <= 0) {
                    continue;
                }

                AwardNominee::where('award_category_id', $category->id)
                    ->where('votes_count', $top)
                    ->update(['is_winner' => true]);
            }

            $edition->forceFill(['results_published_at' => now()])->save();
        });
    }

    /** Retire la publication (les lauréats désignés sont conservés). */
    public function unpublishResults(AwardEdition $edition): void
    {
        $edition->forceFill(['results_published_at' => null])->save();
    }
}
