<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ProducerRevenueService;

/**
 * Comptes dus aux producteurs (admin) : évalue, pour chaque producteur, le
 * nombre de vues générées et le montant à verser (vues × tarif du tier),
 * d'après les tarifs configurés (groupe « revenue »). Inclut une simulation
 * pour vérifier les tarifs avant de les appliquer.
 */
class ProducerEarningsController extends Controller
{
    public function index(ProducerRevenueService $revenue)
    {
        $producers = User::where('role', 'producer')->orderBy('name')->get();

        $rows = $producers->map(function (User $p) use ($revenue) {
            $e = $revenue->earningsForProducer($p);

            return [
                'producer'     => $p,
                'total_views'  => $e['total_views'],
                'total_amount' => $e['total_amount'],
                'by_tier'      => $e['by_tier'],
            ];
        });

        return view('earnings.index', [
            'rows'        => $rows,
            'rates'       => $revenue->rates(),
            'currency'    => $revenue->currency(),
            'grandTotal'  => $rows->sum('total_amount'),
            'grandViews'  => $rows->sum('total_views'),
        ]);
    }
}
