@extends('admin.layouts.app')

@section('title', 'Revenus producteurs - ABBEV')
@section('header', 'Revenus producteurs')

@section('content')
@php
    $fmt = fn ($n) => number_format((float) $n, 2, ',', ' ');
@endphp

<div class="space-y-6" id="earnings-app">

    {{-- Simulateur de tarifs (garde-fou : voir l'impact AVANT d'appliquer) --}}
    <div class="bg-dark-100 rounded-xl border border-dark-200 p-5">
        <div class="flex items-center justify-between gap-3 mb-4">
            <h3 class="text-white font-semibold"><i class="fas fa-calculator mr-2 text-primary-400"></i>Simulateur de tarifs</h3>
            <a href="{{ route('configuration.index') }}#" class="text-xs text-primary-400 hover:underline">Modifier les tarifs (Configuration → Revenue)</a>
        </div>
        <p class="text-gray-400 text-sm mb-4">
            Ajustez les tarifs ci-dessous pour <span class="text-white">simuler</span> le montant total à verser.
            Les tarifs réels se règlent dans <span class="text-white">Configuration → Revenue</span>. Devise : <span class="text-white">{{ $currency }}</span>.
        </p>
        <div class="grid sm:grid-cols-3 gap-4">
            @foreach(['classique','standard','premium'] as $t)
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Tarif/vue — {{ ucfirst($t) }}</label>
                    <input type="number" step="0.01" min="0" data-rate="{{ $t }}"
                           value="{{ $rates[$t] }}"
                           class="w-full bg-dark-50 border border-dark-200 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500">
                </div>
            @endforeach
        </div>
        <div class="mt-4 flex items-center justify-between bg-dark-200/40 rounded-lg px-4 py-3">
            <span class="text-gray-300 text-sm">Total simulé à verser ({{ $grandViews }} vues)</span>
            <span id="sim-grand-total" class="text-primary-300 font-bold text-lg">{{ $fmt($grandTotal) }} {{ $currency }}</span>
        </div>
        <p class="text-amber-300/80 text-xs mt-2"><i class="fas fa-triangle-exclamation mr-1"></i>Simulation uniquement — n'enregistre rien. Réglez les tarifs définitifs dans la Configuration.</p>
    </div>

    {{-- Comptes par producteur --}}
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-dark-200 flex items-center justify-between">
            <h3 class="text-white font-semibold"><i class="fas fa-coins mr-2 text-amber-400"></i>Comptes dus aux producteurs</h3>
            <span class="text-sm text-gray-400">Total : <span id="tbl-grand-total" class="text-white font-semibold">{{ $fmt($grandTotal) }} {{ $currency }}</span></span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-dark-200/40 text-gray-400 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3">Producteur</th>
                        <th class="text-right px-3 py-3">Vues Classique</th>
                        <th class="text-right px-3 py-3">Vues Standard</th>
                        <th class="text-right px-3 py-3">Vues Premium</th>
                        <th class="text-right px-4 py-3">Montant dû</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-dark-200/70">
                    @forelse($rows as $row)
                        <tr class="hover:bg-dark-200/30 earnings-row"
                            data-classique="{{ $row['by_tier']['classique']['views'] }}"
                            data-standard="{{ $row['by_tier']['standard']['views'] }}"
                            data-premium="{{ $row['by_tier']['premium']['views'] }}">
                            <td class="px-4 py-3 text-white font-medium">{{ $row['producer']->name }}</td>
                            <td class="px-3 py-3 text-right text-gray-300">{{ $row['by_tier']['classique']['views'] }}</td>
                            <td class="px-3 py-3 text-right text-gray-300">{{ $row['by_tier']['standard']['views'] }}</td>
                            <td class="px-3 py-3 text-right text-gray-300">{{ $row['by_tier']['premium']['views'] }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-amber-300 row-amount">{{ $fmt($row['total_amount']) }} {{ $currency }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">Aucun producteur.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const CUR = @json($currency);
    const fmt = (n) => n.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const rateInputs = () => Array.from(document.querySelectorAll('[data-rate]'));

    function rates() {
        const r = {};
        rateInputs().forEach(i => r[i.dataset.rate] = parseFloat(i.value) || 0);
        return r;
    }

    function recompute() {
        const r = rates();
        let grand = 0;
        document.querySelectorAll('.earnings-row').forEach(row => {
            const amount = (+row.dataset.classique) * r.classique
                         + (+row.dataset.standard) * r.standard
                         + (+row.dataset.premium) * r.premium;
            grand += amount;
            const cell = row.querySelector('.row-amount');
            if (cell) cell.textContent = fmt(amount) + ' ' + CUR;
        });
        const g = fmt(grand) + ' ' + CUR;
        const a = document.getElementById('sim-grand-total'); if (a) a.textContent = g;
        const b = document.getElementById('tbl-grand-total'); if (b) b.textContent = g;
    }

    rateInputs().forEach(i => i.addEventListener('input', recompute));
})();
</script>
@endpush
