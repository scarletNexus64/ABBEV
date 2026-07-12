<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    public function index()
    {
        $plans = SubscriptionPlan::orderBy('order')->get();
        $stats = [
            'total' => SubscriptionPlan::count(),
            'active' => SubscriptionPlan::where('is_active', true)->count(),
            'subscriptions' => \App\Models\UserSubscription::where('status', 'active')->count(),
            'revenue' => \App\Models\Transaction::where('status', 'completed')
                ->whereMonth('created_at', now()->month)
                ->sum('net_amount'),
        ];

        return view('subscription-plans.index', compact('plans', 'stats'));
    }

    public function create()
    {
        return view('subscription-plans.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tier' => 'required|in:classique,standard,premium',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'order' => 'nullable|integer',
        ]);

        $validated['features'] = $validated['features'] ?? [];
        $validated['is_active'] = $request->has('is_active');
        $validated['is_popular'] = $request->has('is_popular');

        SubscriptionPlan::create($validated);

        return redirect()->route('subscription-plans.index')
            ->with('success', 'Pack créé avec succès.');
    }

    public function edit(SubscriptionPlan $subscriptionPlan)
    {
        $plan = $subscriptionPlan;
        return view('subscription-plans.edit', compact('plan'));
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tier' => 'required|in:classique,standard,premium',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'order' => 'nullable|integer',
        ]);

        $validated['features'] = $validated['features'] ?? [];
        $validated['is_active'] = $request->has('is_active');
        $validated['is_popular'] = $request->has('is_popular');

        $subscriptionPlan->update($validated);

        return redirect()->route('subscription-plans.index')
            ->with('success', 'Pack mis à jour avec succès.');
    }

    public function destroy(SubscriptionPlan $subscriptionPlan)
    {
        $subscriptionPlan->delete();

        return redirect()->route('subscription-plans.index')
            ->with('success', 'Pack supprimé avec succès.');
    }
}
