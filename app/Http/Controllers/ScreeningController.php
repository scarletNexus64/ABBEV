<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Media;
use App\Models\Screening;
use App\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScreeningController extends Controller
{
    /**
     * Offres de billetterie, en deux onglets : séances en salle et codes
     * cinéma (cat.md : « Réservation ticket — salle cinéma, achat code »).
     */
    public function index(Request $request)
    {
        $kind = $request->query('kind') === 'code' ? 'code' : 'seance';

        $screenings = Screening::with(['media', 'ticketTypes', 'country'])
            ->withCount(['reservations as confirmed_reservations' => fn ($q) => $q->where('status', 'confirmed')])
            ->withSum(['reservations as redeemed_entries' => fn ($q) => $q->where('status', 'confirmed')], 'redeemed_quantity')
            ->where('kind', $kind)
            ->orderByDesc($kind === 'code' ? 'valid_until' : 'starts_at')
            ->get();

        $stats = [
            'total'     => Screening::where('kind', $kind)->count(),
            'published' => Screening::where('kind', $kind)->where('status', 'published')->count(),
            'upcoming'  => Screening::where('kind', $kind)->onSale()->count(),
            'revenue'   => \App\Models\Reservation::where('status', 'confirmed')
                ->whereHas('screening', fn ($q) => $q->where('kind', $kind))
                ->sum('total_amount'),
            'seances'   => Screening::where('kind', 'seance')->count(),
            'codes'     => Screening::where('kind', 'code')->count(),
        ];

        return view('screenings.index', compact('screenings', 'stats', 'kind'));
    }

    public function create(Request $request)
    {
        $movies = Media::where('type', 'movie')->orderBy('title')->get(['id', 'title']);
        $countries = Country::with('currency')->where('is_active', true)->orderBy('name')->get();
        $kind = $request->query('kind') === 'code' ? 'code' : 'seance';

        return view('screenings.create', compact('movies', 'countries', 'kind'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateScreening($request);
        $validated = $this->resolveTitle($validated);

        DB::transaction(function () use ($validated, $request) {
            $screening = Screening::create($this->offerAttributes($validated) + [
                'status'       => $request->input('status', 'published'),
                'created_by'   => $request->user()->id,
            ]);

            $currency = $this->currencyForCountry($validated['country_code']);
            $this->syncTicketTypes($screening, $validated['ticket_types'], $currency);
        });

        return redirect()->route('screenings.index', ['kind' => $validated['kind']])
            ->with('success', $validated['kind'] === 'code' ? 'Offre de codes cinéma créée.' : 'Séance créée avec succès.');
    }

    public function edit(Screening $screening)
    {
        $screening->load('ticketTypes');
        $movies = Media::where('type', 'movie')->orderBy('title')->get(['id', 'title']);
        $countries = Country::with('currency')->where('is_active', true)->orderBy('name')->get();

        return view('screenings.edit', compact('screening', 'movies', 'countries'));
    }

    public function update(Request $request, Screening $screening)
    {
        $validated = $this->validateScreening($request);
        $validated = $this->resolveTitle($validated);

        DB::transaction(function () use ($validated, $request, $screening) {
            $screening->update($this->offerAttributes($validated) + [
                'status'       => $request->input('status', $screening->status),
            ]);

            $currency = $this->currencyForCountry($validated['country_code']);
            $this->syncTicketTypes($screening, $validated['ticket_types'], $currency);
        });

        return redirect()->route('screenings.index', ['kind' => $validated['kind']])
            ->with('success', $validated['kind'] === 'code' ? 'Offre de codes cinéma mise à jour.' : 'Séance mise à jour avec succès.');
    }

    public function destroy(Screening $screening)
    {
        if ($screening->reservations()->where('status', 'confirmed')->exists()) {
            return redirect()->route('screenings.index')
                ->with('error', 'Impossible de supprimer : cette séance a des réservations payées.');
        }

        $screening->delete();

        return redirect()->route('screenings.index')
            ->with('success', 'Séance supprimée avec succès.');
    }

    public function cancel(Screening $screening)
    {
        $screening->update(['status' => 'canceled']);

        return redirect()->route('screenings.index', ['kind' => $screening->kind])
            ->with('success', 'Offre annulée : elle n\'est plus en vente.');
    }

    /**
     * Crée / met à jour / supprime les catégories de places d'une séance.
     * Une catégorie ayant déjà des places vendues ne peut pas être supprimée
     * ni voir sa capacité descendre sous le nombre déjà vendu.
     *
     * @param  array<int,array<string,mixed>>  $rows
     */
    private function syncTicketTypes(Screening $screening, array $rows, string $currency = 'XAF'): void
    {
        $keptIds = [];

        foreach ($rows as $row) {
            $id = $row['id'] ?? null;

            if ($id) {
                $type = $screening->ticketTypes()->find($id);
                if (! $type) {
                    continue;
                }
                // Ne jamais fixer une capacité inférieure aux places déjà vendues.
                $capacity = max((int) $row['capacity'], $type->sold_seats);
                $type->update([
                    'name'     => $row['name'],
                    'price'    => $row['price'],
                    'capacity' => $capacity,
                    'currency' => $currency,
                ]);
                $keptIds[] = $type->id;
            } else {
                $type = $screening->ticketTypes()->create([
                    'name'     => $row['name'],
                    'price'    => $row['price'],
                    'capacity' => (int) $row['capacity'],
                    'currency' => $currency,
                ]);
                $keptIds[] = $type->id;
            }
        }

        // Supprimer les catégories retirées du formulaire, sauf si des places
        // y ont déjà été vendues (intégrité des réservations existantes).
        $screening->ticketTypes()
            ->whereNotIn('id', $keptIds ?: [0])
            ->where('sold_seats', 0)
            ->delete();
    }

    /**
     * Colonnes communes à une séance et à une offre de codes. Pour un code,
     * `starts_at` marque l'ouverture de la vente (maintenant par défaut).
     *
     * @param  array<string,mixed>  $validated
     * @return array<string,mixed>
     */
    private function offerAttributes(array $validated): array
    {
        $isCode = $validated['kind'] === 'code';

        return [
            'kind'         => $validated['kind'],
            'media_id'     => $validated['media_id'] ?? null,
            'movie_title'  => $validated['movie_title'],
            'cinema_name'  => $validated['cinema_name'],
            'location'     => $validated['location'],
            'country_code' => $validated['country_code'],
            'starts_at'    => $isCode ? ($validated['starts_at'] ?? now()) : $validated['starts_at'],
            'valid_until'  => $isCode ? $validated['valid_until'] : null,
        ];
    }

    private function currencyForCountry(string $countryCode): string
    {
        return Country::where('code', $countryCode)->value('currency_code') ?? 'XAF';
    }

    /**
     * @return array<string,mixed>
     */
    private function validateScreening(Request $request): array
    {
        return $request->validate([
            'media_id'              => 'nullable|exists:media,id',
            'movie_title'           => 'nullable|string|max:255',
            'cinema_name'           => 'required|string|max:255',
            'location'              => 'required|string|max:255',
            'country_code'          => 'required|string|size:2|exists:countries,code',
            'kind'                  => 'required|in:seance,code',
            // Séance : date et heure de projection. Code : ouverture de la
            // vente (facultative), puis date de fin de validité.
            'starts_at'             => 'nullable|required_if:kind,seance|date',
            'valid_until'           => 'nullable|required_if:kind,code|date|after:now',
            'status'                => 'nullable|in:draft,published,canceled',
            'ticket_types'          => 'required|array|min:1',
            'ticket_types.*.id'       => 'nullable|integer',
            'ticket_types.*.name'     => 'required|string|max:100',
            'ticket_types.*.price'    => 'required|numeric|min:0',
            'ticket_types.*.capacity' => 'required|integer|min:1',
        ], [
            'ticket_types.required' => 'Ajoutez au moins une catégorie de place.',
            'ticket_types.min'      => 'Ajoutez au moins une catégorie de place.',
            'starts_at.required_if' => 'Indiquez la date et l\'heure de la séance.',
            'valid_until.required_if' => 'Indiquez jusqu\'à quand les codes sont valables.',
            'valid_until.after'     => 'La date de validité doit être dans le futur.',
        ]);
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function resolveTitle(array $data): array
    {
        if (! empty($data['media_id'])) {
            $media = Media::find($data['media_id']);
            if ($media && empty($data['movie_title'])) {
                $data['movie_title'] = $media->title;
            }
        }

        if (empty($data['movie_title'])) {
            throw ValidationException::withMessages([
                'movie_title' => ($data['kind'] ?? 'seance') === 'code'
                    ? "Donnez un nom à l'offre (ex. « Code cinéma — Duo »)."
                    : 'Choisissez un film du catalogue ou saisissez un titre.',
            ]);
        }

        return $data;
    }
}
