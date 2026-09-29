<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AwardEdition;
use App\Models\AwardNominee;
use App\Models\BunnyUpload;
use App\Models\CastingCall;
use App\Models\Course;
use App\Models\Episode;
use App\Models\Media;
use App\Models\Oeuvre;
use App\Models\ProjectCall;
use App\Models\Screening;
use App\Models\Talent;
use App\Models\TalentCredit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Transfert de données entre espaces (admin) : de la plateforme ou d'un
 * producteur vers un autre producteur, élément par élément.
 *
 * Ce qui suit automatiquement :
 *  - les enfants de chaque élément (saisons/épisodes, rôles et candidatures,
 *    prix/nommés/votes, leçons, candidatures/soutiens, tarifs/réservations),
 *    rattachés à leur parent ;
 *  - les uploads vidéo liés aux films transférés, pour que l'ancien
 *    propriétaire ne puisse pas supprimer une vidéo qui ne lui appartient plus ;
 *  - en option, l'agent des talents transférés.
 *
 * Revenus : ils se calculent en direct à partir des vues rémunérées
 * (`producer_views`) de chaque contenu, sans historique de versement. Par
 * défaut le compteur est remis à zéro, pour que le destinataire ne soit
 * rémunéré que sur les vues à venir.
 */
class DataTransferController extends Controller
{
    /** Types transférables : clé => [modèle, libellé, icône]. */
    private const TYPES = [
        'media' => [Media::class, 'Films & séries', 'film'],
        'oeuvres' => [Oeuvre::class, 'Œuvres adaptables', 'book-open'],
        'talents' => [Talent::class, 'Talents', 'id-badge'],
        'agents' => [Agent::class, 'Agents', 'user-tie'],
        'castings' => [CastingCall::class, 'Annonces casting', 'bullhorn'],
        'awards' => [AwardEdition::class, 'Éditions Lions Head Awards', 'trophy'],
        'courses' => [Course::class, 'Cours de cinéma', 'graduation-cap'],
        'calls' => [ProjectCall::class, 'Appels à projets', 'lightbulb'],
        'screenings' => [Screening::class, 'Séances & codes cinéma', 'ticket'],
    ];

    public function index(Request $request)
    {
        $producers = User::where('role', 'producer')->whereNull('producer_id')->orderBy('name')->get();

        $source = $this->resolveSource($request->query('source', 'platform'));
        $target = $this->resolveProducer($request->query('target'));

        $groups = [];
        if ($source !== false) {
            foreach (self::TYPES as $key => [$class, $label, $icon]) {
                $groups[$key] = [
                    'label' => $label,
                    'icon' => $icon,
                    'items' => $this->ownedBy($class, $source)->latest('id')->get()
                        ->map(fn (Model $m) => ['id' => $m->getKey()] + $this->describe($key, $m)),
                ];
            }
        }

        return view('transfers.index', [
            'producers' => $producers,
            'source' => $source,
            'sourceKey' => $source ? $source->getRouteKey() : 'platform',
            'target' => $target,
            'groups' => $groups,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'source' => 'required|string',
            'target' => 'required|string',
            'items' => 'required|array',
            'items.*' => 'array',
            'items.*.*' => 'integer',
            'reset_views' => 'boolean',
            'include_agents' => 'boolean',
        ], [
            'items.required' => 'Cochez au moins un élément à transférer.',
        ]);

        $source = $this->resolveSource($data['source']);
        $target = $this->resolveProducer($data['target']);

        if ($source === false || ! $target) {
            return back()->with('error', 'Source ou producteur destinataire introuvable.');
        }
        if ($source && $source->is($target)) {
            return back()->with('error', 'La source et le destinataire sont le même producteur.');
        }

        $moved = DB::transaction(function () use ($data, $source, $target, $request) {
            // Seuls les éléments appartenant réellement à la source sont pris.
            $ids = [];
            foreach (self::TYPES as $key => [$class]) {
                $wanted = array_map('intval', $data['items'][$key] ?? []);
                $ids[$key] = $wanted
                    ? $this->ownedBy($class, $source)->whereKey($wanted)->pluck('id')->all()
                    : [];
            }

            if ($request->boolean('include_agents') && $ids['talents']) {
                $agentIds = Talent::whereKey($ids['talents'])->whereNotNull('agent_id')->pluck('agent_id')->all();
                $ids['agents'] = array_values(array_unique(array_merge(
                    $ids['agents'],
                    $this->ownedBy(Agent::class, $source)->whereKey($agentIds)->pluck('id')->all(),
                )));
            }

            foreach (self::TYPES as $key => [$class]) {
                if ($ids[$key]) {
                    $class::whereKey($ids[$key])->update([$class::workspaceColumn() => $target->id]);
                }
            }

            if ($ids['media']) {
                $this->moveUploads($ids['media'], $target);
                if ($request->boolean('reset_views')) {
                    $this->resetPaidViews($ids['media']);
                }
            }

            return array_map('count', $ids);
        });

        $total = array_sum($moved);
        if ($total === 0) {
            return back()->with('error', "Aucun élément transféré : ils n'appartiennent plus à la source sélectionnée.");
        }

        $summary = collect($moved)->filter()
            ->map(fn (int $n, string $key) => $n . ' ' . mb_strtolower(self::TYPES[$key][1]))
            ->implode(', ');

        $redirect = redirect()->route('transfers.index', [
            'source' => $data['source'],
            'target' => $target->getRouteKey(),
        ])->with('success', "{$total} élément(s) transféré(s) à « {$target->name} » : {$summary}.");

        // Liens qui pointent désormais vers un autre espace : visibles dans
        // l'app, mais invisibles pour le producteur dans son espace.
        $warnings = $this->crossLinks($target);
        if ($source) {
            $warnings = array_merge($warnings, $this->crossLinks($source));
        }

        return $warnings ? $redirect->with('transfer_warnings', $warnings) : $redirect;
    }

    /**
     * 'platform' → null ; clé d'un producteur titulaire → User ;
     * sinon false (source invalide).
     */
    private function resolveSource(?string $value): User|null|false
    {
        if ($value === null || $value === 'platform') {
            return null;
        }

        return $this->resolveProducer($value) ?? false;
    }

    private function resolveProducer(?string $value): ?User
    {
        if (! $value) {
            return null;
        }

        $user = (new User())->resolveRouteBinding($value);

        return $user?->isProducerOwner() ? $user : null;
    }

    /**
     * Éléments d'un espace. La plateforme regroupe ce qui n'a pas de
     * producteur, ainsi que les contenus créés par un administrateur (leur
     * `user_id` est celui de l'admin).
     */
    private function ownedBy(string $class, ?User $owner): Builder
    {
        $column = $class::workspaceColumn();
        $query = $class::query();

        if ($owner) {
            return $query->where($column, $owner->id);
        }

        if ($class === Media::class) {
            return $query->where(fn ($q) => $q->whereNull($column)
                ->orWhereIn($column, User::where('role', 'admin')->select('id')));
        }

        return $query->whereNull($column);
    }

    /** Libellé et précision affichés pour un élément. */
    private function describe(string $key, Model $m): array
    {
        return match ($key) {
            'media' => [
                'title' => $m->title,
                'meta' => ($m->type === 'series' ? 'Série' : 'Film') . ' · '
                    . (['pending' => 'en attente', 'approved' => 'publié', 'rejected' => 'rejeté'][$m->moderation_status] ?? $m->moderation_status),
            ],
            'oeuvres' => ['title' => $m->title, 'meta' => $m->author],
            'talents' => ['title' => $m->displayName(), 'meta' => 'Rang ' . $m->tier],
            'agents' => ['title' => $m->name, 'meta' => null],
            'castings' => ['title' => $m->title, 'meta' => CastingCall::STATUSES[$m->status] ?? $m->status],
            'awards' => ['title' => $m->name, 'meta' => $m->is_current ? "{$m->year} · affichée dans l'app" : (string) $m->year],
            'courses' => ['title' => $m->title, 'meta' => Course::TYPES[$m->type] ?? $m->type],
            'calls' => ['title' => $m->title, 'meta' => ProjectCall::STATUSES[$m->status] ?? $m->status],
            'screenings' => [
                'title' => $m->movie_title ?: 'Séance',
                'meta' => collect([$m->cinema_name, $m->starts_at?->format('d/m/Y')])->filter()->implode(' · '),
            ],
        };
    }

    /** Uploads vidéo (Bunny ou fichier local) utilisés par ces contenus. */
    private function moveUploads(array $mediaIds, User $target): void
    {
        $episodes = Episode::whereIn('season_id', fn ($q) => $q->select('id')->from('seasons')->whereIn('media_id', $mediaIds))
            ->get(['video_provider', 'video_id', 'video_path']);
        $videos = Media::whereKey($mediaIds)->get(['video_provider', 'video_id', 'video_path'])->concat($episodes);

        $guids = $videos->where('video_provider', 'bunny')->pluck('video_id')->filter()->unique()->values();
        $paths = $videos->where('video_provider', 'local')->pluck('video_path')->filter()->unique()->values();

        if ($guids->isEmpty() && $paths->isEmpty()) {
            return;
        }

        BunnyUpload::where(fn ($q) => $q->whereIn('bunny_guid', $guids)->orWhereIn('local_path', $paths))
            ->update(['user_id' => $target->id]);
    }

    private function resetPaidViews(array $mediaIds): void
    {
        Media::whereKey($mediaIds)->update(['producer_views' => 0]);
        Episode::whereIn('season_id', fn ($q) => $q->select('id')->from('seasons')->whereIn('media_id', $mediaIds))
            ->update(['producer_views' => 0]);
    }

    /**
     * Éléments d'un producteur qui pointent vers une donnée d'un autre espace.
     *
     * @return list<string>
     */
    private function crossLinks(User $owner): array
    {
        $elsewhere = fn (string $column) => fn ($q) => $q->where(fn ($q) => $q->whereNull($column)->orWhere($column, '!=', $owner->id));

        $counts = [
            'talent(s) rattaché(s) à un agent' => Talent::where('producer_id', $owner->id)
                ->whereHas('agent', $elsewhere('producer_id'))->count(),
            'film(s) cité(s) dans la filmographie de ses talents' => TalentCredit::whereHas('talent', fn ($q) => $q->where('producer_id', $owner->id))
                ->whereHas('media', $elsewhere('user_id'))->count(),
            'nommé(s) aux Awards (talent ou film)' => AwardNominee::whereHas('category.edition', fn ($q) => $q->where('producer_id', $owner->id))
                ->where(fn ($q) => $q->whereHas('talent', $elsewhere('producer_id'))->orWhereHas('media', $elsewhere('user_id')))
                ->count(),
            'séance(s) liée(s) à un film' => Screening::where('producer_id', $owner->id)
                ->whereHas('media', $elsewhere('user_id'))->count(),
        ];

        return collect($counts)->filter()
            ->map(fn (int $n, string $label) => "{$owner->name} : {$n} {$label} d'un autre espace")
            ->values()
            ->all();
    }
}
