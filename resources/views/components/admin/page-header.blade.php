{{-- En-tête de page : titre, sous-titre, fil d'Ariane éventuel, actions. --}}
@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => 'Retour'])
<div class="mb-6">
    @if($back)
        <a href="{{ $back }}" class="inline-flex items-center text-sm text-primary-400 hover:text-primary-300 transition mb-3">
            <i class="fas fa-arrow-left mr-2 text-xs"></i> {{ $backLabel }}
        </a>
    @endif
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div class="min-w-0">
            <h2 class="text-2xl font-bold text-white leading-tight">{{ $title }}</h2>
            @if($subtitle)
                <p class="text-gray-400 mt-1 max-w-3xl">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="flex flex-wrap items-center gap-2 shrink-0">{{ $actions }}</div>
        @endisset
    </div>
</div>
