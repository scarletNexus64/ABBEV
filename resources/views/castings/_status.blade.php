@php
    $open = $call->isOpen();
    [$tone, $label, $icon] = match (true) {
        $call->status === 'draft' => ['gray', 'Brouillon', 'pen-ruler'],
        $open => ['emerald', 'Ouverte', 'circle-dot'],
        $call->status === 'open' => ['amber', 'Date limite passée', 'hourglass-end'],
        default => ['rose', 'Clôturée', 'lock'],
    };
@endphp
<x-admin.badge :tone="$tone" :icon="$icon">{{ $label }}</x-admin.badge>
