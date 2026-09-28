<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Contrôle à l'entrée de la salle : vérifie un billet de séance ou un code
 * cinéma (tous deux portés par la référence de la réservation) et enregistre
 * les entrées consommées.
 *
 * Une réservation couvre `quantity` entrées : un code « Duo » se valide en
 * une ou deux fois, mais jamais au-delà.
 */
class TicketCheckService
{
    /** Tolérance après le début d'une séance pour laisser entrer un retardataire. */
    private const LATE_ENTRY_HOURS = 6;

    /** Retrouve une réservation par sa référence, saisie avec ou sans « ABBEV- ». */
    public function find(string $code): ?Reservation
    {
        $reference = strtoupper(trim(preg_replace('/\s+/', '', $code)));
        if ($reference === '') {
            return null;
        }
        if (! str_starts_with($reference, 'ABBEV-')) {
            $reference = 'ABBEV-' . $reference;
        }

        return Reservation::with(['screening', 'ticketType', 'user', 'redeemer'])
            ->where('reference', $reference)
            ->first();
    }

    /**
     * Le billet peut-il être validé maintenant ?
     *
     * @return array{ok: bool, reason: ?string}
     */
    public function verdict(Reservation $reservation): array
    {
        if ($reservation->status === 'pending') {
            return ['ok' => false, 'reason' => "Paiement non confirmé : ce billet n'est pas encore valable."];
        }
        if ($reservation->status !== 'confirmed') {
            return ['ok' => false, 'reason' => 'Billet annulé.'];
        }
        if ($reservation->remainingEntries() <= 0) {
            return ['ok' => false, 'reason' => 'Toutes les entrées de ce billet ont déjà été utilisées.'];
        }

        $offer = $reservation->screening;
        if ($offer?->isCode()) {
            if ($offer->valid_until !== null && $offer->valid_until->isPast()) {
                return ['ok' => false, 'reason' => 'Code expiré le ' . $offer->valid_until->format('d/m/Y') . '.'];
            }
        } elseif ($offer && $offer->starts_at->lt(now()->subHours(self::LATE_ENTRY_HOURS))) {
            return ['ok' => false, 'reason' => 'Séance terminée (' . $offer->starts_at->format('d/m/Y H:i') . ').'];
        }

        return ['ok' => true, 'reason' => null];
    }

    /** Valide `$entries` entrées (bornées au reste disponible), sous verrou. */
    public function redeem(Reservation $reservation, int $entries, User $staff): Reservation
    {
        return DB::transaction(function () use ($reservation, $entries, $staff) {
            $fresh = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $fresh->load('screening');

            $verdict = $this->verdict($fresh);
            if (! $verdict['ok']) {
                throw new RuntimeException($verdict['reason']);
            }

            $entries = max(1, min($entries, $fresh->remainingEntries()));

            $fresh->update([
                'redeemed_quantity' => $fresh->redeemed_quantity + $entries,
                'redeemed_at' => now(),
                'redeemed_by' => $staff->id,
            ]);

            return $fresh->refresh();
        });
    }
}
