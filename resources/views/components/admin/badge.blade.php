{{-- Pastille d'état. `tone` : gray, primary, emerald, amber, rose, violet, sky, gold. --}}
@props(['tone' => 'gray', 'icon' => null])
@php
    $tones = [
        'gray'    => 'bg-gray-500/15 text-gray-300 border-gray-500/30',
        'primary' => 'bg-primary-500/15 text-primary-300 border-primary-500/30',
        'emerald' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
        'amber'   => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
        'rose'    => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
        'violet'  => 'bg-violet-500/15 text-violet-300 border-violet-500/30',
        'sky'     => 'bg-sky-500/15 text-sky-300 border-sky-500/30',
        'gold'    => 'bg-yellow-500/15 text-yellow-300 border-yellow-500/40',
    ];
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold border whitespace-nowrap', $tones[$tone] ?? $tones['gray']]) }}>
    @if($icon)<i class="fas fa-{{ $icon }} text-[10px]"></i>@endif
    {{ $slot }}
</span>
