{{-- Groupe repliable de la barre latérale (élément <details> natif : aucun
     JavaScript requis pour l'ouvrir). Le groupe de la page courante est
     toujours ouvert ; l'état des autres est mémorisé par navigateur. --}}
@props(['key', 'label', 'active' => false])
<details class="abbev-nav-group mt-4" data-nav-group="{{ $key }}" open @if($active) data-active="1" @endif>
    <summary class="flex items-center justify-between px-3 py-1.5 rounded-md cursor-pointer select-none text-[10.5px] font-semibold text-gray-500 uppercase tracking-[0.12em] hover:text-gray-300">
        <span>{{ $label }}</span>
        <i class="fas fa-chevron-down text-[9px] transition-transform duration-200"></i>
    </summary>
    <div class="mt-1 space-y-0.5">{{ $slot }}</div>
</details>
