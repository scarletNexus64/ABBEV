<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\TicketCheckService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Contrôle à l'entrée : l'agent saisit la référence d'un billet ou d'un code
 * cinéma (ABBEV-XXXXXXXX), voit s'il est valable, puis valide les entrées.
 */
class TicketCheckController extends Controller
{
    public function __construct(private TicketCheckService $tickets)
    {
    }

    public function index(Request $request)
    {
        $code = trim((string) $request->query('code', ''));
        $reservation = $code !== '' ? $this->tickets->find($code) : null;

        return view('tickets.check', [
            'code' => $code,
            'reservation' => $reservation,
            'verdict' => $reservation ? $this->tickets->verdict($reservation) : null,
            'recent' => Reservation::with(['screening', 'redeemer'])
                ->whereNotNull('redeemed_at')
                ->latest('redeemed_at')
                ->take(8)
                ->get(),
        ]);
    }

    public function redeem(Request $request, Reservation $reservation)
    {
        $entries = (int) $request->validate(['entries' => 'required|integer|min:1|max:50'])['entries'];

        try {
            $updated = $this->tickets->redeem($reservation, $entries, $request->user());
        } catch (RuntimeException $e) {
            return redirect()->route('tickets.check', ['code' => $reservation->reference])->with('error', $e->getMessage());
        }

        $left = $updated->remainingEntries();

        return redirect()->route('tickets.check', ['code' => $updated->reference])
            ->with('success', "Entrée validée pour {$updated->reference}. " . ($left > 0 ? "Encore {$left} entrée(s) disponible(s)." : 'Billet entièrement utilisé.'));
    }
}
