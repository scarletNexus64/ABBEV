@extends('admin.layouts.app')

@section('title', 'Bunny Library - ABBEV')
@section('header', 'Bunny Stream — Library')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <i class="fas fa-cloud text-primary-400"></i>
                    Bibliothèque Bunny Stream
                </h2>
                <p class="text-gray-400 text-sm mt-1">
                    Library #{{ config('services.bunny.library_id') }} —
                    <span class="text-white font-semibold">{{ $total }}</span> vidéos disponibles côté Bunny,
                    <span class="text-green-400 font-semibold">{{ $usedCount }}</span> utilisées dans le catalogue,
                    <span class="text-yellow-400 font-semibold">{{ $freeCount }}</span> libres.
                </p>
                <p class="text-gray-500 text-xs mt-2">
                    <i class="fas fa-info-circle"></i>
                    Les vidéos restent hébergées chez Bunny — on ne fait que les référencer.
                    Pour utiliser une vidéo libre, crée un film (catalogue) ou un épisode (série) et choisis-la dans le picker.
                </p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('admin.bunny.uploads.index') }}"
                   class="bg-primary-500 hover:bg-primary-600 text-white px-4 py-2 rounded-lg font-medium transition-all">
                    <i class="fas fa-cloud-arrow-up mr-2"></i>
                    Uploader une vidéo
                </a>
                <form action="{{ route('admin.bunny.refresh') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="bg-primary-500/20 hover:bg-primary-500/30 text-primary-200 px-4 py-2 rounded-lg font-medium transition-all">
                        <i class="fas fa-sync-alt mr-2"></i>
                        Rafraîchir
                    </button>
                </form>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="bg-green-500/10 border border-green-500/30 text-green-300 rounded-lg p-4">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="bg-red-500/10 border border-red-500/30 text-red-300 rounded-lg p-4">
            <i class="fas fa-exclamation-triangle mr-2"></i>{{ session('error') }}
        </div>
    @endif

    <!-- Recherche locale -->
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 p-4">
        <input id="bunny-filter" type="text" placeholder="Filtrer par titre ou GUID…"
               class="w-full bg-dark-50 border border-dark-200 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20" />
    </div>

    <!-- Videos table -->
    <div class="bg-dark-100 rounded-xl shadow-lg border border-dark-200 overflow-hidden">
        @if ($videos->isEmpty())
            <div class="p-12 text-center text-gray-400">
                <i class="fas fa-video-slash text-5xl mb-4 text-gray-600"></i>
                <p class="text-lg">Aucune vidéo dans la library Bunny.</p>
                <p class="text-sm mt-2">Uploadez des films/séries depuis
                    <a href="https://dash.bunny.net/stream/{{ config('services.bunny.library_id') }}/library/overview"
                       target="_blank" class="text-primary-400 underline">votre dashboard Bunny</a>.
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left" id="bunny-table">
                    <thead class="bg-dark-200 text-gray-300 text-sm uppercase">
                        <tr>
                            <th class="px-4 py-3">Vignette</th>
                            <th class="px-4 py-3">Titre & GUID</th>
                            <th class="px-4 py-3">Durée</th>
                            <th class="px-4 py-3">Taille</th>
                            <th class="px-4 py-3">Statut</th>
                            <th class="px-4 py-3">Utilisation</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-200" id="bunny-rows">
                        @foreach ($videos as $v)
                            <tr class="hover:bg-dark-200/40 bunny-row"
                                data-title="{{ Str::lower($v['title']) }}"
                                data-guid="{{ Str::lower($v['guid'] ?? '') }}">
                                <td class="px-4 py-3">
                                    @if ($v['thumb'])
                                        <img src="{{ $v['thumb'] }}" alt="" class="w-24 h-14 rounded object-cover bg-dark-300"
                                             onerror="this.style.opacity=0.2;this.src='data:image/svg+xml;utf8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 56%22%3E%3Crect width=%22100%22 height=%2256%22 fill=%22%23222%22/%3E%3C/svg%3E'">
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-white font-medium">
                                    {{ $v['title'] }}
                                    <p class="text-xs text-gray-500 font-mono">{{ $v['guid'] }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-300 text-sm">
                                    @if ($v['length'] > 0)
                                        {{ gmdate($v['length'] >= 3600 ? 'H:i:s' : 'i:s', $v['length']) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-400 text-sm">
                                    {{ number_format($v['storage'] / 1024 / 1024, 1) }} Mo
                                </td>
                                <td class="px-4 py-3">
                                    @if ($v['status'] === 4)
                                        <span class="inline-flex items-center gap-1 bg-green-500/10 text-green-300 px-2 py-1 rounded text-xs">
                                            <i class="fas fa-check-circle"></i> Prêt
                                        </span>
                                    @elseif (in_array($v['status'], [1,2,3]))
                                        <span class="inline-flex items-center gap-1 bg-yellow-500/10 text-yellow-300 px-2 py-1 rounded text-xs">
                                            <i class="fas fa-spinner fa-spin"></i> Encodage…
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 bg-gray-500/10 text-gray-300 px-2 py-1 rounded text-xs">
                                            <i class="fas fa-circle"></i> Code {{ $v['status'] }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($v['usage'])
                                        @if ($v['usage']['type'] === 'movie')
                                            <a href="{{ route('media.edit', $v['usage']['media']->id) }}"
                                               class="inline-flex items-center gap-1 bg-blue-500/10 hover:bg-blue-500/20 text-blue-300 px-3 py-1.5 rounded text-sm">
                                                <i class="fas fa-film"></i>
                                                {{ Str::limit($v['usage']['media']->title, 26) }}
                                            </a>
                                        @else
                                            <a href="{{ route('episodes.edit', $v['usage']['episode']->id) }}"
                                               class="inline-flex items-center gap-1 bg-purple-500/10 hover:bg-purple-500/20 text-purple-300 px-3 py-1.5 rounded text-sm">
                                                <i class="fas fa-tv"></i>
                                                {{ $v['usage']['media']?->title ?? 'Série' }} —
                                                S{{ $v['usage']['episode']->season?->season_number }}E{{ $v['usage']['episode']->episode_number }}
                                            </a>
                                        @endif
                                    @else
                                        <span class="text-yellow-300 text-sm">
                                            <i class="fas fa-circle text-xs mr-1"></i>
                                            Libre
                                        </span>
                                        <div class="mt-1 flex gap-2 text-xs">
                                            <a href="{{ route('media.create', ['bunny' => $v['guid']]) }}"
                                               class="text-primary-300 hover:underline">
                                                <i class="fas fa-plus"></i> Créer un film
                                            </a>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>

@push('scripts')
<script>
    document.getElementById('bunny-filter')?.addEventListener('input', (e) => {
        const q = e.target.value.toLowerCase().trim();
        document.querySelectorAll('#bunny-rows .bunny-row').forEach(row => {
            const t = row.dataset.title || '';
            const g = row.dataset.guid || '';
            row.style.display = (!q || t.includes(q) || g.includes(q)) ? '' : 'none';
        });
    });
</script>
@endpush
@endsection
