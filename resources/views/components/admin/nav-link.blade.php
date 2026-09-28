{{-- Lien de la barre latérale. `active` : route courante ; `badge` : compteur
     à traiter (candidatures, modération…), masqué quand il vaut 0. --}}
@props(['href', 'icon', 'active' => false, 'badge' => null, 'badgeClass' => 'bg-amber-500 text-white'])
<a href="{{ $href }}"
   @class([
       'group flex items-center gap-3 px-3 py-2 text-[13px] rounded-lg transition-all',
       'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-md shadow-primary-900/30' => $active,
       'text-gray-300 hover:bg-dark-200 hover:text-white' => ! $active,
   ])>
    <i class="fas fa-{{ $icon }} w-4 text-center {{ $active ? 'text-white' : 'text-gray-500 group-hover:text-primary-300' }}"></i>
    <span class="flex-1 truncate">{{ $slot }}</span>
    @if($badge)
        <span class="text-[10px] font-bold px-1.5 min-w-[20px] text-center py-0.5 rounded-full {{ $badgeClass }}">{{ $badge }}</span>
    @endif
</a>
