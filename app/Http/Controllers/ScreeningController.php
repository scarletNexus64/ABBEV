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
    public function index()
    {
        $screenings = Screening::with(['media', 'ticketTypes', 'country'])
            ->withCount(['reservations as confirmed_reservations' => fn ($q) => $q->where('status', 'confirmed')])
            ->orderByDesc('starts_at')
            ->get();

        $stats = [
            'total'     => Screening::count(),
            'published' => Screening::where('status', 'published')->count(),
            'upcoming'  => Screening::where('starts_at', '>=', now())->count(),
            'revenue'   => \App\Models\Reservation::where('status', 'confirmed')->sum('total_amount'),
        ];

        return view('screenings.index', compact('screenings', 'stats'));
    }

    public function create()
    {
        $movies = Media::where('type', 'movie')->orderBy('title')->get(['id', 'title']);
        $countries = Country::with('currency')->where('is_active', true)->orderBy('name')->get();

        return view('screenings.create', compact('movies', 'countries'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateScreening($request);
        $validated = $this->resolveTitle($validated);

        DB::transaction(function () use ($validated, $request) {
            $screening = Screening::create([
                'media_id'     => $validated['media_id'] ?? null,
                'movie_title'  => $validated['movie_title'],
                'cinema_name'  => $validated['cinema_name'],
                'location'     => $validated['location'],
                'country_code' => $validated['country_code'],
                'starts_at'    => $validated['starts_at'],
                'status'       => $request->input('status', 'published'),
                'created_by'   => $request->user()->id,
            ]);

            $currency = $this->currencyForCountry($validated['country_code']);
            $this->syncTicketTypes($screening, $validated['ticket_types'], $currency);
        });

        return redirect()->route('screenings.index')
            ->with('success', 'Séance créée avec succès.');
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
            $screening->update([
                'media_id'     => $validated['media_id'] ?? null,
                'movie_title'  => $validated['movie_title'],
                'cinema_name'  => $validated['cinema_name'],
                'location'     => $validated['location'],
                'country_code' => $validated['country_code'],
                'starts_at'    => $validated['starts_at'],
                'status'       => $request->input('status', $screening->status),
            ]);

            $currency = $this->currencyForCountry($validated['country_code']);
            $this->syncTicketTypes($screening, $validated['ticket_types'], $currency);
        });

        return redirect()->route('screenings.index')
            ->with('success', 'Séance mise à jour avec succès.');
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

        return redirect()->route('screenings.index')
            ->with('success', 'Séance annulée. Elle n\'est plus réservable.');
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
            'starts_at'             => 'required|date',
            'status'                => 'nullable|in:draft,published,canceled',
            'ticket_types'          => 'required|array|min:1',
            'ticket_types.*.id'       => 'nullable|integer',
            'ticket_types.*.name'     => 'required|string|max:100',
            'ticket_types.*.price'    => 'required|numeric|min:0',
            'ticket_types.*.capacity' => 'required|integer|min:1',
        ], [
            'ticket_types.required' => 'Ajoutez au moins une catégorie de place.',
            'ticket_types.min'      => 'Ajoutez au moins une catégorie de place.',
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
                'movie_title' => 'Choisissez un film du catalogue ou saisissez un titre.',
            ]);
        }

        return $data;
    }
}
