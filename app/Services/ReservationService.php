<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\TicketType;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Logique métier des réservations de tickets de séances.
 *
 * Règle de stock : les places ne sont décomptées (sold_seats) qu'au moment
 * de la confirmation du paiement. Une réservation `pending` ne bloque rien.
 * La confirmation est atomique (verrou ligne) pour éviter la survente.
 */
class ReservationService
{
    /**
     * Crée une réservation en attente de paiement + sa transaction.
     *
     * Le `reservation_id` est stocké dans `metadata` de la transaction : c'est
     * le lien qui permet de confirmer automatiquement la réservation quand le
     * paiement (PayPal/KPay) aboutit, via [confirmFromTransaction()].
     *
     * @param  string  $paymentMethod  Moyen de paiement Transaction (paypal|kpay|mobile…)
     * @return array{reservation: Reservation, transaction: Transaction}
     */
    public function create(
        int $userId,
        TicketType $ticketType,
        int $quantity,
        string $paymentMethod = 'mobile'
    ): array {
        if ($quantity < 1) {
            throw new RuntimeException(__('messages.reservation.quantity_min'));
        }

        // Vérification indicative (le contrôle ferme se fait à la confirmation).
        if ($ticketType->availableSeats() < $quantity) {
            throw new RuntimeException(__('messages.reservation.not_enough_seats'));
        }

        $unitPrice = (float) $ticketType->price;
        $total     = $unitPrice * $quantity;

        return DB::transaction(function () use ($userId, $ticketType, $quantity, $unitPrice, $total, $paymentMethod) {
            $reservation = Reservation::create([
                'reference'      => 'ABBEV-' . strtoupper(Str::random(8)),
                'user_id'        => $userId,
                'screening_id'   => $ticketType->screening_id,
                'ticket_type_id' => $ticketType->id,
                'quantity'       => $quantity,
                'unit_price'     => $unitPrice,
                'total_amount'   => $total,
                'currency'       => $ticketType->currency,
                'status'         => 'pending',
            ]);

            $transaction = Transaction::create([
                'user_id'        => $userId,
                'transaction_id' => 'RES-' . strtoupper(Str::random(12)),
                'payment_method' => $paymentMethod,
                'type'           => 'purchase',
                'amount'         => $total,
                'fees'           => 0,
                'net_amount'     => $total,
                'currency'       => $ticketType->currency,
                'description'    => "Réservation {$quantity} place(s) — {$ticketType->name}",
                'status'         => 'pending',
                'metadata'       => [
                    'reservation_id'        => $reservation->id,
                    'reservation_reference' => $reservation->reference,
                    'screening_id'          => $ticketType->screening_id,
                ],
            ]);

            $reservation->update(['transaction_id' => $transaction->id]);

            return ['reservation' => $reservation->refresh(), 'transaction' => $transaction];
        });
    }

    /**
     * Confirme la réservation rattachée à une transaction payée (hook appelé
     * aux points de validation de paiement : capture PayPal, réconciliation
     * KPay, polling). No-op si la transaction n'est pas liée à une réservation.
     * Idempotent.
     */
    public function confirmFromTransaction(Transaction $transaction): ?Reservation
    {
        $reservationId = $transaction->metadata['reservation_id'] ?? null;
        if (! $reservationId) {
            return null;
        }

        $reservation = Reservation::find($reservationId);
        if (! $reservation) {
            return null;
        }

        return $this->confirm($reservation);
    }

    /**
     * Confirme une réservation payée : décrémente le stock de façon atomique
     * et marque la transaction complétée. Idempotent (rejoue sans effet).
     */
    public function confirm(Reservation $reservation): Reservation
    {
        if ($reservation->status === 'confirmed') {
            return $reservation;
        }

        if ($reservation->status === 'canceled') {
            throw new RuntimeException(__('messages.reservation.cancelled_cannot_confirm'));
        }

        return DB::transaction(function () use ($reservation) {
            // Verrou sur la catégorie pour empêcher deux confirmations
            // concurrentes de dépasser la capacité.
            $type = TicketType::whereKey($reservation->ticket_type_id)
                ->lockForUpdate()
                ->first();

            if (! $type || ($type->capacity - $type->sold_seats) < $reservation->quantity) {
                throw new RuntimeException(__('messages.reservation.not_enough_to_confirm'));
            }

            $type->increment('sold_seats', $reservation->quantity);

            $reservation->update([
                'status'       => 'confirmed',
                'confirmed_at' => now(),
            ]);

            if ($reservation->transaction) {
                $reservation->transaction->update([
                    'status'       => 'completed',
                    'completed_at' => now(),
                ]);
            }

            return $reservation->refresh();
        });
    }

    /**
     * Annule une réservation. Si elle était confirmée, libère les places.
     */
    public function cancel(Reservation $reservation): Reservation
    {
        if ($reservation->status === 'canceled') {
            return $reservation;
        }

        return DB::transaction(function () use ($reservation) {
            if ($reservation->status === 'confirmed') {
                $type = TicketType::whereKey($reservation->ticket_type_id)
                    ->lockForUpdate()
                    ->first();
                if ($type) {
                    $type->decrement('sold_seats', min($reservation->quantity, $type->sold_seats));
                }
            }

            $reservation->update(['status' => 'canceled']);

            if ($reservation->transaction && $reservation->transaction->status === 'pending') {
                $reservation->transaction->update(['status' => 'cancelled']);
            }

            return $reservation->refresh();
        });
    }
}
