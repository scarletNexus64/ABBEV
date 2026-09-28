<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesMediaUrls;
use App\Models\Currency;
use App\Models\Reservation;
use App\Models\Screening;
use App\Models\TicketType;
use App\Services\ReservationService;
use App\Support\BusinessTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;

class ScreeningApiController extends Controller
{
    use ResolvesMediaUrls;

    /** @var array<string, array{currency_symbol: string, currency_decimals: int}> */
    private array $currencyMetaCache = [];

    public function __construct(private ReservationService $reservations)
    {
    }

    /**
     * Offres de billetterie en vente, paginées (10/page), avec recherche et
     * filtre par période.
     *
     * Query params :
     *   - kind      : 'seance' (défaut) — séances datées en salle,
     *                 'code' — codes cinéma prépayés, sans séance fixe
     *   - page      : int (défaut 1)
     *   - q         : recherche sur le titre du film / cinéma / lieu
     *   - from      : date ISO — séances à partir de cette date
     *   - to        : date ISO — séances jusqu'à cette date (incluse, fin de journée)
     *   - period    : raccourci 'today' | 'week' | 'month' (ignoré si from/to fournis)
     */
    public function index(Request $request): JsonResponse
    {
        $kind = $request->query('kind') === 'code' ? 'code' : 'seance';

        $query = Screening::with(['ticketTypes', 'media'])
            ->onSale()
            ->where('kind', $kind);

        // Filtrer par pays du user connecté : n'afficher que les offres de son
        // pays. Route publique : le guard sanctum est interrogé explicitement,
        // `$request->user()` resterait null même avec un token valide.
        // Une offre sans pays n'est réservée à personne : elle reste visible.
        $user = $request->user('sanctum');
        if ($user && $user->country_code) {
            $query->where(fn ($q) => $q->whereNull('country_code')
                ->orWhere('country_code', $user->country_code));
        }

        // Recherche plein-texte simple (film / cinéma / lieu).
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($sub) use ($q) {
                $sub->where('movie_title', 'like', "%{$q}%")
                    ->orWhere('cinema_name', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%");
            });
        }

        if ($kind === 'seance') {
            // Filtre par période : n'a de sens que pour une séance datée.
            [$from, $to] = $this->resolvePeriod($request);
            if ($from) {
                $query->where('starts_at', '>=', $from);
            }
            if ($to) {
                $query->where('starts_at', '<=', $to);
            }
            $query->orderBy('starts_at');
        } else {
            // Codes : ceux qui expirent le plus tôt d'abord, les codes sans
            // date d'expiration en dernier.
            $query->orderByRaw('CASE WHEN valid_until IS NULL THEN 1 ELSE 0 END')
                ->orderBy('valid_until')
                ->orderBy('cinema_name');
        }

        $paginator = $query->paginate(10);

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (Screening $s) => $this->presentScreening($s))
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'has_more'     => $paginator->hasMorePages(),
            ],
        ]);
    }

    /**
     * Résout les bornes de date à partir de `from`/`to` ou du raccourci
     * `period`. Retourne [from|null, to|null].
     *
     * @return array{0: ?\Illuminate\Support\Carbon, 1: ?\Illuminate\Support\Carbon}
     */
    private function resolvePeriod(Request $request): array
    {
        $from = $request->query('from');
        $to   = $request->query('to');

        // Les jours s'entendent en heure locale (« aujourd'hui » à Douala),
        // les bornes repassent en UTC pour la comparaison en base.
        $zone = BusinessTime::zone();
        $utc = fn (?Carbon $d) => $d?->utc();

        if ($from || $to) {
            return [
                $utc($from ? Carbon::parse($from, $zone)->startOfDay() : null),
                $utc($to ? Carbon::parse($to, $zone)->endOfDay() : null),
            ];
        }

        $now = BusinessTime::now();

        return match ($request->query('period')) {
            'today' => [$utc($now->copy()->startOfDay()), $utc($now->copy()->endOfDay())],
            'week'  => [$utc($now->copy()->startOfDay()), $utc($now->copy()->endOfWeek())],
            'month' => [$utc($now->copy()->startOfDay()), $utc($now->copy()->endOfMonth())],
            default => [null, null],
        };
    }

    /**
     * Détail d'une séance.
     */
    public function show(Screening $screening): JsonResponse
    {
        $screening->load(['ticketTypes', 'media']);

        return response()->json(['data' => $this->presentScreening($screening)]);
    }

    /**
     * Crée une réservation (en attente de paiement) sur une catégorie de place.
     */
    public function reserve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ticket_type_id' => 'required|exists:ticket_types,id',
            'quantity'       => 'required|integer|min:1|max:20',
        ]);

        $ticketType = TicketType::findOrFail($validated['ticket_type_id']);

        try {
            $result = $this->reservations->create(
                $request->user()->id,
                $ticketType,
                $validated['quantity']
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'     => __('messages.reservation.created'),
            'reservation' => $this->presentReservation($result['reservation']),
            'payment'     => [
                'transaction_id' => $result['transaction']->transaction_id,
                'amount'         => $result['transaction']->amount,
                'currency'       => $result['transaction']->currency,
                'status'         => $result['transaction']->status,
            ],
        ], 201);
    }

    /**
     * Confirme une réservation après paiement réussi (décrémente le stock).
     */
    public function confirm(Request $request, Reservation $reservation): JsonResponse
    {
        if ($reservation->user_id !== $request->user()->id) {
            return response()->json(['message' => __('messages.reservation.not_found')], 404);
        }

        try {
            $reservation = $this->reservations->confirm($reservation);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'     => __('messages.reservation.confirmed'),
            'reservation' => $this->presentReservation($reservation),
        ]);
    }

    /**
     * Liste des réservations de l'utilisateur connecté.
     */
    public function myReservations(Request $request): JsonResponse
    {
        $reservations = Reservation::with(['screening', 'ticketType'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Reservation $r) => $this->presentReservation($r));

        return response()->json(['data' => $reservations]);
    }

    /**
     * Annule une réservation de l'utilisateur connecté.
     */
    public function cancel(Request $request, Reservation $reservation): JsonResponse
    {
        if ($reservation->user_id !== $request->user()->id) {
            return response()->json(['message' => __('messages.reservation.not_found')], 404);
        }

        // Une entrée déjà validée au contrôle ne se « rend » pas : annuler
        // libérerait une place réellement consommée.
        if ((int) $reservation->redeemed_quantity > 0) {
            return response()->json(['message' => __('messages.reservation.already_used')], 422);
        }

        $reservation = $this->reservations->cancel($reservation);

        return response()->json([
            'message'     => __('messages.reservation.cancelled'),
            'reservation' => $this->presentReservation($reservation),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function presentScreening(Screening $s): array
    {
        $poster = $s->relationLoaded('media') && $s->media
            ? ($s->media->cover_path ?: $s->media->thumbnail_path)
            : null;

        return [
            'id'           => $s->id,
            'kind'         => $s->kind ?? 'seance',
            'movie_title'  => $s->movie_title,
            'cinema_name'  => $s->cinema_name,
            'location'     => $s->location,
            'country_code' => $s->country_code,
            'starts_at'    => $s->starts_at->toIso8601String(),
            'valid_until'  => $s->valid_until?->toIso8601String(),
            'poster_url'   => $poster ? $this->absoluteUrl($poster) : null,
            'ticket_types' => $s->ticketTypes->map(fn (TicketType $t) => [
                'id'              => $t->id,
                'name'            => $t->t('name'),
                'price'           => (float) $t->price,
                'currency'        => $t->currency,
                ...$this->currencyMeta($t->currency),
                'capacity'        => $t->capacity,
                'available_seats' => $t->availableSeats(),
                'sold_out'        => $t->availableSeats() <= 0,
            ])->values(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function presentReservation(Reservation $r): array
    {
        return [
            'id'           => $r->id,
            'reference'    => $r->reference,
            'status'       => $r->status,
            'quantity'     => $r->quantity,
            'unit_price'   => (float) $r->unit_price,
            'total_amount' => (float) $r->total_amount,
            'currency'     => $r->currency,
            ...$this->currencyMeta($r->currency),
            'confirmed_at' => $r->confirmed_at?->toIso8601String(),
            // Entrées déjà validées au contrôle (billet ou code cinéma).
            'redeemed_quantity' => (int) ($r->redeemed_quantity ?? 0),
            'screening'    => $r->relationLoaded('screening') && $r->screening ? [
                'id'          => $r->screening->id,
                'kind'        => $r->screening->kind ?? 'seance',
                'movie_title' => $r->screening->movie_title,
                'cinema_name' => $r->screening->cinema_name,
                'location'    => $r->screening->location,
                'starts_at'   => $r->screening->starts_at->toIso8601String(),
                'valid_until' => $r->screening->valid_until?->toIso8601String(),
            ] : null,
            'ticket_type'  => $r->relationLoaded('ticketType') && $r->ticketType ? [
                'id'   => $r->ticketType->id,
                'name' => $r->ticketType->t('name'),
            ] : null,
        ];
    }

    /**
     * Symbole + décimales d'affichage pour un code devise donné. Contrairement
     * aux abonnements, les prix des tickets sont en devise locale FIXE (celle
     * du cinéma, ex. XAF) : on ne convertit pas, on enrichit juste le code avec
     * son symbole pour que l'app affiche « 3 000 FCFA » plutôt que « 3 000 XAF ».
     *
     * @return array{currency_symbol: string, currency_decimals: int}
     */
    private function currencyMeta(?string $code): array
    {
        // Mémorisé par requête : une liste de séances répète la même devise
        // sur chaque tarif (une requête SQL par tarif auparavant).
        $key = strtoupper((string) $code);
        if (isset($this->currencyMetaCache[$key])) {
            return $this->currencyMetaCache[$key];
        }

        $currency = $code
            ? Currency::where('code', $key)->first()
            : null;

        return $this->currencyMetaCache[$key] = [
            'currency_symbol'   => $currency?->symbol ?: ($code ?? ''),
            'currency_decimals' => (int) ($currency?->decimals ?? 0),
        ];
    }
}
