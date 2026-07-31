<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Reservation;
use App\Models\Screening;
use App\Models\TicketType;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ScreeningApiController extends Controller
{
    public function __construct(private ReservationService $reservations)
    {
    }

    /**
     * Séances réservables à venir, paginées (10/page), avec recherche et
     * filtre par période.
     *
     * Query params :
     *   - page      : int (défaut 1)
     *   - q         : recherche sur le titre du film / cinéma / lieu
     *   - from      : date ISO — séances à partir de cette date
     *   - to        : date ISO — séances jusqu'à cette date (incluse, fin de journée)
     *   - period    : raccourci 'today' | 'week' | 'month' (ignoré si from/to fournis)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Screening::with('ticketTypes')
            ->where('status', 'published')
            ->where('starts_at', '>=', now());

        // Recherche plein-texte simple (film / cinéma / lieu).
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($sub) use ($q) {
                $sub->where('movie_title', 'like', "%{$q}%")
                    ->orWhere('cinema_name', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%");
            });
        }

        // Filtre par période.
        [$from, $to] = $this->resolvePeriod($request);
        if ($from) {
            $query->where('starts_at', '>=', $from);
        }
        if ($to) {
            $query->where('starts_at', '<=', $to);
        }

        $paginator = $query->orderBy('starts_at')->paginate(10);

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

        if ($from || $to) {
            return [
                $from ? \Illuminate\Support\Carbon::parse($from)->startOfDay() : null,
                $to ? \Illuminate\Support\Carbon::parse($to)->endOfDay() : null,
            ];
        }

        return match ($request->query('period')) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'week'  => [now()->startOfDay(), now()->endOfWeek()],
            'month' => [now()->startOfDay(), now()->endOfMonth()],
            default => [null, null],
        };
    }

    /**
     * Détail d'une séance.
     */
    public function show(Screening $screening): JsonResponse
    {
        $screening->load('ticketTypes');

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
        return [
            'id'           => $s->id,
            'movie_title'  => $s->movie_title,
            'cinema_name'  => $s->cinema_name,
            'location'     => $s->location,
            'starts_at'    => $s->starts_at->toIso8601String(),
            'ticket_types' => $s->ticketTypes->map(fn (TicketType $t) => [
                'id'              => $t->id,
                'name'            => $t->name,
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
            'screening'    => $r->relationLoaded('screening') && $r->screening ? [
                'id'          => $r->screening->id,
                'movie_title' => $r->screening->movie_title,
                'cinema_name' => $r->screening->cinema_name,
                'location'    => $r->screening->location,
                'starts_at'   => $r->screening->starts_at->toIso8601String(),
            ] : null,
            'ticket_type'  => $r->relationLoaded('ticketType') && $r->ticketType ? [
                'id'   => $r->ticketType->id,
                'name' => $r->ticketType->name,
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
        $currency = $code
            ? Currency::where('code', strtoupper($code))->first()
            : null;

        return [
            'currency_symbol'   => $currency?->symbol ?: ($code ?? ''),
            'currency_decimals' => (int) ($currency?->decimals ?? 0),
        ];
    }
}
