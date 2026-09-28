{{-- Interrupteur booléen. Un champ caché envoie 0 quand il est décoché :
     sans lui, une case décochée n'est tout simplement pas transmise. --}}
@props(['name', 'label', 'checked' => false, 'hint' => null])
@php $isOn = (bool) old($name, $checked); @endphp
<label {{ $attributes->class(['flex items-start gap-3 cursor-pointer select-none group']) }}>
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1" class="peer sr-only" @checked($isOn)>
    <span class="relative mt-0.5 w-10 h-6 shrink-0 rounded-full bg-dark-300 peer-checked:bg-primary-500 transition
                 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:rounded-full after:bg-white after:transition
                 peer-checked:after:translate-x-4"></span>
    <span>
        <span class="block text-sm font-medium text-gray-200">{{ $label }}</span>
        @if($hint)<span class="block text-xs text-gray-500 mt-0.5">{{ $hint }}</span>@endif
    </span>
</label>
