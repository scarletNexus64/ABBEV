<?php

namespace App\Jobs;

use App\Models\Configuration;
use App\Models\Transaction;
use App\Services\KpayService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Réconciliation asynchrone d'une transaction KPay par polling
 * en mode "tick" : à chaque exécution, le Job vérifie une fois
 * GET /payments/:id ; s'il n'est pas terminal et que la deadline
 * (kpay_max_duration) n'est pas dépassée, il se re-dispatche avec
 * un délai. Aucun worker n'est bloqué longtemps.
 *
 * Idempotent : reconcileTransaction() ne re-finalise pas une
 * transaction déjà complétée par le polling client.
 */
class ReconcileKpayTransaction implements ShouldQueue
{
    use Queueable;

    /** Délai entre chaque tick de polling (secondes). */
    private const POLL_INTERVAL_SEC = 3;

    /** Limite haute par exécution (anti-blocage worker). */
    public int $timeout = 30;

    /**
     * Chaque tick est indépendant : un échec réseau ne doit pas tuer la
     * chaîne. On laisse Laravel ne pas retenter automatiquement ; c'est
     * le tick suivant (qu'on dispatche nous-mêmes) qui ré-essaie.
     */
    public int $tries = 1;

    public int $transactionId;

    /** Timestamp unix au-delà duquel on abandonne. Renseigné au 1er tick. */
    public ?int $deadlineTs;

    public function __construct(int $transactionId, ?int $deadlineTs = null)
    {
        $this->transactionId = $transactionId;
        $this->deadlineTs = $deadlineTs;
    }

    /**
     * Borne supérieure de réessai côté worker : Laravel refuse d'exécuter
     * le Job au-delà. On s'aligne sur la fenêtre du dashboard
     * (kpay_max_duration) + marge.
     */
    public function retryUntil(): \DateTime
    {
        $maxDuration = (int) Configuration::getValue('kpay_max_duration', 300);

        return now()->addSeconds(max(60, $maxDuration) + 60)->toDateTime();
    }

    public function handle(KpayService $kpay): void
    {
        $transaction = Transaction::find($this->transactionId);

        if (!$transaction) {
            Log::warning('[ReconcileKpayTransaction] Transaction introuvable', [
                'transaction_id' => $this->transactionId,
            ]);

            return;
        }

        if (in_array($transaction->status, ['completed', 'failed'], true)) {
            return;
        }

        $reference = $transaction->external_reference;

        if (!$reference) {
            Log::warning('[ReconcileKpayTransaction] Pas de référence KPay', [
                'transaction_id' => $transaction->transaction_id,
            ]);

            return;
        }

        // 1er tick : on fixe la deadline à partir de la config dashboard.
        if ($this->deadlineTs === null) {
            $this->deadlineTs = time() + max(60, $kpay->maxDuration());

            Log::info('[ReconcileKpayTransaction] Début polling', [
                'transaction_id' => $transaction->transaction_id,
                'reference' => $reference,
                'deadline_ts' => $this->deadlineTs,
            ]);
        }

        if (time() >= $this->deadlineTs) {
            Log::warning('[ReconcileKpayTransaction] Timeout polling, transaction laissée pending', [
                'transaction_id' => $transaction->transaction_id,
            ]);

            return;
        }

        $result = $kpay->getPayment($reference);

        if ($result['success']) {
            $kpayStatus = strtoupper((string) ($result['data']['status'] ?? 'PENDING'));

            if (in_array($kpayStatus, ['COMPLETED', 'FAILED', 'CANCELLED', 'EXPIRED', 'REFUNDED'], true)) {
                $localStatus = $kpay->reconcileTransaction($transaction, $kpayStatus);

                Log::info('[ReconcileKpayTransaction] Réconciliation terminée', [
                    'transaction_id' => $transaction->transaction_id,
                    'kpay_status' => $kpayStatus,
                    'local_status' => $localStatus,
                ]);

                return;
            }
        } else {
            Log::warning('[ReconcileKpayTransaction] getPayment échoué', [
                'transaction_id' => $transaction->transaction_id,
                'message' => $result['message'] ?? 'unknown',
            ]);
        }

        // Pas encore terminal : on planifie le prochain tick.
        self::dispatch($this->transactionId, $this->deadlineTs)
            ->delay(now()->addSeconds(self::POLL_INTERVAL_SEC));
    }
}
