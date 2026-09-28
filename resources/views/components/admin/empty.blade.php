{{-- État vide d'une liste. --}}
@props(['icon' => 'inbox', 'title', 'text' => null])
<div class="text-center py-14 px-6">
    <div class="w-16 h-16 rounded-2xl bg-primary-500/10 border border-primary-500/20 flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-{{ $icon }} text-2xl text-primary-400"></i>
    </div>
    <h3 class="text-white font-semibold">{{ $title }}</h3>
    @if($text)<p class="text-gray-400 text-sm mt-1 max-w-md mx-auto">{{ $text }}</p>@endif
    @if(! $slot->isEmpty())<div class="mt-5">{{ $slot }}</div>@endif
</div>
