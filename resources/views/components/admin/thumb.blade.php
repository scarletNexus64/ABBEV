{{-- Vignette d'image stockée (chemin du disque public ou URL externe), avec
     repli sur une icône quand elle manque ou ne charge pas. --}}
@props(['path' => null, 'icon' => 'image', 'alt' => ''])
@php
    $src = null;
    if ($path) {
        $src = str_starts_with($path, 'http') ? $path : asset('storage/' . ltrim($path, '/'));
    }
@endphp
<div {{ $attributes->class(['relative overflow-hidden bg-dark-200 flex items-center justify-center shrink-0']) }}>
    <i class="fas fa-{{ $icon }} text-gray-600"></i>
    @if($src)
        <img src="{{ $src }}" alt="{{ $alt }}" loading="lazy" class="absolute inset-0 w-full h-full object-cover" onerror="this.remove()">
    @endif
</div>
