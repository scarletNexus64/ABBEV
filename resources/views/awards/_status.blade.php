@php
    [$tone, $icon] = match ($edition->status()) {
        'voting' => ['emerald', 'circle-dot'],
        'upcoming' => ['sky', 'clock'],
        'closed' => ['amber', 'lock'],
        'results' => ['gold', 'trophy'],
        default => ['gray', 'pen-ruler'],
    };
@endphp
<x-admin.badge :tone="$tone" :icon="$icon">{{ $edition->statusLabel() }}</x-admin.badge>
