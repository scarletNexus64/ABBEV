@extends('admin.layouts.app')

@section('title', 'Talents')
@section('header', 'Talents & casting')

@section('content')
<x-admin.page-header title="Talents"
    subtitle="L'annuaire des acteurs, actrices et techniciens, classés par rang (A² icône → D amateur). Chaque fiche publiée est consultable dans l'application.">
    <x-slot:actions>
        <a href="{{ route('agents.index') }}" class="inline-flex items-center gap-2 bg-dark-200 hover:bg-dark-300 text-gray-200 px-4 py-2.5 rounded-lg transition"><i class="fas fa-user-tie text-sm"></i> Agents</a>
        <a href="{{ route('talents.create') }}" class="inline-flex items-center gap-2 bg-primary-500 hover:bg-primary-600 text-white px-5 py-2.5 rounded-lg font-medium transition"><i class="fas fa-plus text-sm"></i> Nouveau talent</a>
    </x-slot:actions>
</x-admin.page-header>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-admin.stat label="Acteurs & actrices" :value="$stats['actors']" icon="masks-theater" :href="route('talents.index', ['kind' => 'acteur'])" />
    <x-admin.stat label="Techniciens" :value="$stats['technicians']" icon="video" tone="sky" :href="route('talents.index', ['kind' => 'technicien'])" />
    <x-admin.stat label="Agents" :value="$stats['agents']" icon="user-tie" tone="violet" :href="route('agents.index')" />
    <x-admin.stat label="Fiches non publiées" :value="$stats['drafts']" icon="eye-slash" tone="amber" :href="route('talents.index', ['status' => 'draft'])" />
</div>

{{-- Filtres --}}
<div class="flex flex-col lg:flex-row lg:items-center gap-3 mb-5">
    <div class="flex flex-wrap gap-2">
        @php $base = array_filter(['kind' => $filters['kind'] ?? null, 'q' => $filters['q'] ?? null]); @endphp
        <a href="{{ route('talents.index', $base) }}"
           @class(['px-3 py-1.5 rounded-lg text-sm border transition', 'bg-white text-dark-100 border-white font-semibold' => empty($filters['tier']), 'border-dark-200 text-gray-300 hover:border-primary-500/50' => ! empty($filters['tier'])])>Tous les rangs</a>
        @foreach(\App\Models\Talent::TIER_LABELS as $tier => $label)
            <a href="{{ route('talents.index', $base + ['tier' => $tier]) }}"
               @class(['px-3 py-1.5 rounded-lg text-sm border transition', 'bg-white text-dark-100 border-white font-semibold' => ($filters['tier'] ?? null) === $tier, 'border-dark-200 text-gray-300 hover:border-primary-500/50' => ($filters['tier'] ?? null) !== $tier])>
                {{ $label }} <span class="opacity-60 ml-1">{{ $tierCounts[$tier] ?? 0 }}</span>
            </a>
        @endforeach
    </div>
    <form method="GET" action="{{ route('talents.index') }}" class="abbev-search lg:ml-auto lg:w-72">
        @foreach(array_filter(['kind' => $filters['kind'] ?? null, 'tier' => $filters['tier'] ?? null]) as $k => $v)
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endforeach
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher un nom…"
               class="w-full bg-dark-100 border border-dark-200 rounded-lg px-4 py-2.5 text-white placeholder-gray-600 focus:outline-none focus:border-primary-500">
        <i class="fas fa-magnifying-glass"></i>
    </form>
</div>

<div class="bg-dark-100 rounded-xl border border-dark-200 overflow-hidden">
    @if($talents->isEmpty())
        <x-admin.empty icon="id-badge" title="Aucun talent" text="Aucune fiche ne correspond à ces filtres.">
            <a href="{{ route('talents.create') }}" class="inline-flex items-center gap-2 bg-primary-500 hover:bg-primary-600 text-white px-5 py-2.5 rounded-lg transition"><i class="fas fa-plus"></i> Créer une fiche</a>
        </x-admin.empty>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-dark-50 text-gray-500 uppercase text-[11px] tracking-wider">
                    <tr>
                        <th class="px-6 py-3 text-left">Talent</th>
                        <th class="px-4 py-3 text-left">Rang</th>
                        <th class="px-4 py-3 text-left">Métier</th>
                        <th class="px-4 py-3 text-left">Agent</th>
                        <th class="px-4 py-3 text-left">Lieu</th>
                        <th class="px-4 py-3 text-left">Statut</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-dark-200">
                    @foreach($talents as $t)
                        <tr class="hover:bg-dark-50/40 transition">
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-3">
                                    <x-admin.thumb :path="$t->photo_path" icon="user" class="w-10 h-12 rounded-lg" />
                                    <div class="min-w-0">
                                        <p class="text-white font-medium truncate flex items-center gap-1.5">
                                            {{ $t->displayName() }}
                                            @if($t->is_featured)<i class="fas fa-star text-amber-400 text-[10px]" title="Mis en avant"></i>@endif
                                        </p>
                                        <p class="text-xs text-gray-500 truncate max-w-xs">{{ $t->headline }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">@include('talents._tier-badge', ['tier' => $t->tier])</td>
                            <td class="px-4 py-3 text-gray-300">{{ $t->professionLabel() }}</td>
                            <td class="px-4 py-3 text-gray-400">{{ $t->agent?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-400">{{ collect([$t->city, $t->country_code])->filter()->implode(', ') ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @if($t->is_published)
                                    <x-admin.badge tone="emerald" icon="eye">Publiée</x-admin.badge>
                                @else
                                    <x-admin.badge tone="gray" icon="eye-slash">Brouillon</x-admin.badge>
                                @endif
                            </td>
                            <td class="px-6 py-3">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('talents.edit', $t) }}" class="bg-primary-500/15 hover:bg-primary-500 text-primary-300 hover:text-white px-3 py-2 rounded-lg transition" title="Modifier"><i class="fas fa-pen"></i></a>
                                    <form action="{{ route('talents.destroy', $t) }}" method="POST"
                                          data-confirm="Supprimer la fiche de {{ $t->displayName() }} ? Elle disparaîtra de l'application." data-confirm-type="danger" data-confirm-title="Supprimer la fiche" data-confirm-confirm="Supprimer">
                                        @csrf @method('DELETE')
                                        <button class="bg-rose-500/15 hover:bg-rose-500 text-rose-300 hover:text-white px-3 py-2 rounded-lg transition" title="Supprimer"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($talents->hasPages())
            <div class="px-6 py-4 border-t border-dark-200">{{ $talents->links() }}</div>
        @endif
    @endif
</div>
@endsection
