<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProducerPlan;
use App\Models\ProducerSubscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Configuration du pack producteur (admin) : un seul pack, dont le prix et la
 * période (mois ou année) s'appliquent à tous les producteurs.
 */
class ProducerPlanController extends Controller
{
    public function edit()
    {
        $plan = ProducerPlan::current() ?? new ProducerPlan([
            'name' => 'Pack Producteur',
            'price' => 20000,
            'billing_period' => 'month',
            'period_count' => 1,
            'features' => [],
            'is_active' => false,
        ]);

        $stats = [
            'producers' => User::where('role', 'producer')->whereNull('producer_id')->count(),
            'subscribed' => ProducerSubscription::unexpired()
                ->where('starts_at', '<=', now())
                ->distinct()->count('producer_id'),
            'revenue' => Transaction::where('type', 'producer_subscription')
                ->where('status', 'completed')->sum('amount'),
        ];

        $payments = Transaction::with('user')
            ->where('type', 'producer_subscription')
            ->latest()->limit(10)->get();

        return view('producer-plan.edit', compact('plan', 'stats', 'payments'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            // Minimum accepté par KPay pour un paiement Mobile Money.
            'price' => 'required|numeric|min:100',
            'billing_period' => 'required|in:' . implode(',', array_keys(ProducerPlan::PERIODS)),
            'period_count' => 'required|integer|min:1|max:24',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['features'] = array_values(array_filter(
            $validated['features'] ?? [],
            fn ($feature) => trim((string) $feature) !== ''
        ));
        $validated['is_active'] = $request->boolean('is_active');

        if ($plan = ProducerPlan::current()) {
            $plan->update($validated);
        } else {
            ProducerPlan::create($validated);
        }

        return redirect()->route('producer-plan.edit')->with('success', $validated['is_active']
            ? 'Pack producteur enregistré. Les espaces des producteurs sans abonnement en cours sont verrouillés.'
            : 'Pack producteur enregistré et désactivé : les espaces producteurs sont ouverts sans paiement.');
    }
}
