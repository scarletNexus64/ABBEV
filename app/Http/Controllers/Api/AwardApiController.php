<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AwardEditionResource;
use App\Models\AwardEdition;
use App\Models\AwardNominee;
use App\Models\AwardVote;
use App\Services\AwardVotingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Lions Head Awards côté app : consultation de l'édition en cours et vote.
 *
 * La consultation est publique (un visiteur découvre les nommés) ; voter
 * exige un compte.
 */
class AwardApiController extends Controller
{
    public function __construct(private AwardVotingService $voting)
    {
    }

    /** GET /awards/current — l'édition mise en avant, prix et nommés compris. */
    public function current(Request $request): JsonResponse
    {
        $edition = AwardEdition::where('is_current', true)->first();

        if (! $edition) {
            return response()->json([
                'data' => null,
                'message' => __('messages.awards.no_edition'),
            ]);
        }

        return $this->present($request, $edition);
    }

    /** GET /awards/editions/{edition} — une édition précise (palmarès passés). */
    public function show(Request $request, AwardEdition $edition): JsonResponse
    {
        return $this->present($request, $edition);
    }

    /** POST /awards/nominees/{nominee}/vote */
    public function vote(Request $request, AwardNominee $nominee): JsonResponse
    {
        $user = $request->user();

        $previous = AwardVote::where('award_category_id', $nominee->award_category_id)
            ->where('user_id', $user->id)
            ->value('award_nominee_id');

        try {
            $vote = $this->voting->cast($user, $nominee, $request->ip());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $editionId = $nominee->category()->value('award_edition_id');

        return response()->json([
            'message' => $previous && (int) $previous !== (int) $nominee->id
                ? __('messages.awards.vote_changed')
                : __('messages.awards.voted'),
            'category_id' => (int) $vote->award_category_id,
            'nominee_id' => (int) $vote->award_nominee_id,
            'my_votes_count' => AwardVote::where('user_id', $user->id)
                ->whereHas('category', fn ($q) => $q->where('award_edition_id', $editionId))
                ->count(),
        ]);
    }

    private function present(Request $request, AwardEdition $edition): JsonResponse
    {
        $edition->load([
            'categories.nominees.media',
            'categories.nominees.talent',
        ]);

        $myVotes = [];
        if ($user = $request->user('sanctum')) {
            $myVotes = AwardVote::where('user_id', $user->id)
                ->whereIn('award_category_id', $edition->categories->pluck('id'))
                ->pluck('award_nominee_id', 'award_category_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return response()->json([
            'data' => (new AwardEditionResource($edition))->withVotesOf($myVotes)->resolve($request),
        ]);
    }
}
