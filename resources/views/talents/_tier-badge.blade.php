{{-- Pastille de rang A² … D, de l'or (icône) au gris (amateur). --}}
@php
    $tierStyles = [
        'A2' => 'bg-gradient-to-r from-yellow-400 to-amber-500 text-black border-yellow-300',
        'A1' => 'bg-amber-500/20 text-amber-200 border-amber-400/50',
        'B'  => 'bg-violet-500/20 text-violet-200 border-violet-400/40',
        'C'  => 'bg-sky-500/15 text-sky-200 border-sky-400/40',
        'D'  => 'bg-gray-500/15 text-gray-300 border-gray-500/40',
    ];
    $tierShort = ['A2' => 'A²', 'A1' => 'A¹', 'B' => 'B', 'C' => 'C', 'D' => 'D'];
@endphp
<span class="inline-flex items-center justify-center min-w-[30px] h-6 px-2 rounded-md border text-xs font-extrabold {{ $tierStyles[$tier] ?? $tierStyles['D'] }}"
      title="{{ \App\Models\Talent::TIER_LABELS[$tier] ?? $tier }}">{{ $tierShort[$tier] ?? $tier }}</span>
