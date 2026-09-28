{{-- Tuile de chiffre clé. `tone` : primary, amber, emerald, violet, rose, sky, slate. --}}
@props(['label', 'value', 'icon', 'tone' => 'primary', 'hint' => null, 'href' => null])
@php
    $tones = [
        'primary' => ['bg-primary-500/15 border-primary-500/30', 'text-primary-300'],
        'amber'   => ['bg-amber-500/15 border-amber-500/30', 'text-amber-300'],
        'emerald' => ['bg-emerald-500/15 border-emerald-500/30', 'text-emerald-300'],
        'violet'  => ['bg-violet-500/15 border-violet-500/30', 'text-violet-300'],
        'rose'    => ['bg-rose-500/15 border-rose-500/30', 'text-rose-300'],
        'sky'     => ['bg-sky-500/15 border-sky-500/30', 'text-sky-300'],
        'slate'   => ['bg-slate-500/15 border-slate-500/30', 'text-slate-300'],
    ];
    [$box, $ink] = $tones[$tone] ?? $tones['primary'];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif
    {{ $attributes->class(['bg-dark-100 rounded-xl border border-dark-200 p-5 flex items-center gap-4 transition', 'hover:border-primary-500/40 hover:-translate-y-0.5' => $href]) }}>
    <div class="w-11 h-11 rounded-lg border flex items-center justify-center shrink-0 {{ $box }}">
        <i class="fas fa-{{ $icon }} {{ $ink }}"></i>
    </div>
    <div class="min-w-0">
        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide truncate">{{ $label }}</p>
        <p class="text-2xl font-bold text-white leading-tight">{{ $value }}</p>
        @if($hint)
            <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $hint }}</p>
        @endif
    </div>
</{{ $tag }}>
