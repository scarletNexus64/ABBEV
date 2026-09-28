@php
    [$tone, $icon] = match (true) {
        $call->status === 'draft' => ['gray', 'pen-ruler'],
        $call->isOpen() => ['emerald', 'circle-dot'],
        $call->status === 'open' => ['amber', 'hourglass-half'],
        $call->status === 'completed' => ['primary', 'flag-checkered'],
        default => ['rose', 'lock'],
    };
    $label = $call->status === 'open' && ! $call->isOpen()
        ? ($call->opens_at && $call->opens_at->isFuture() ? 'Programmé' : 'Date limite passée')
        : $call->statusLabel();
@endphp
<x-admin.badge :tone="$tone" :icon="$icon">{{ $label }}</x-admin.badge>
