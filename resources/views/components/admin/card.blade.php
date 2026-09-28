{{-- Carte de contenu, avec en-tête optionnel (titre, icône, actions). --}}
@props(['title' => null, 'icon' => null, 'subtitle' => null, 'padding' => 'p-6'])
<section {{ $attributes->class(['bg-dark-100 rounded-xl border border-dark-200 shadow-lg shadow-black/10']) }}>
    @if($title)
        <header class="flex items-center justify-between gap-3 px-6 py-4 border-b border-dark-200">
            <div class="min-w-0">
                <h3 class="text-white font-semibold flex items-center gap-2">
                    @if($icon)<i class="fas fa-{{ $icon }} text-primary-400 text-sm"></i>@endif
                    {{ $title }}
                </h3>
                @if($subtitle)<p class="text-xs text-gray-500 mt-0.5">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)<div class="flex items-center gap-2 shrink-0">{{ $actions }}</div>@endisset
        </header>
    @endif
    <div class="{{ $padding }}">{{ $slot }}</div>
</section>
