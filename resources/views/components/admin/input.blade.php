{{-- Champ texte (ou date, nombre, url…) avec libellé, aide et erreur. --}}
@props(['name', 'label', 'value' => null, 'type' => 'text', 'required' => false, 'hint' => null, 'placeholder' => null, 'prefix' => null])
@php $key = str_replace(['[', ']'], ['.', ''], $name); @endphp
@php $id = $attributes->get('id', 'f_' . str_replace(['[', ']', '.'], '_', $name)); @endphp
<div {{ $attributes->only('class')->class(['space-y-1.5']) }}>
    <label for="{{ $id }}" class="block text-sm font-medium text-gray-300">
        {{ $label }} @if($required)<span class="text-rose-400">*</span>@endif
    </label>
    <div class="relative">
        @if($prefix)
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm pointer-events-none">{{ $prefix }}</span>
        @endif
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($key, $value) }}"
               @if($required) required @endif @if($placeholder) placeholder="{{ $placeholder }}" @endif
               {{ $attributes->except(['class', 'id']) }}
               @class([
                   'w-full bg-dark-50 border rounded-lg px-4 py-2.5 text-white placeholder-gray-600 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition',
                   'pl-12' => $prefix,
                   'border-rose-500' => $errors->has($key),
                   'border-dark-200' => ! $errors->has($key),
               ])>
    </div>
    @if($hint && ! $errors->has($key))<p class="text-xs text-gray-500">{{ $hint }}</p>@endif
    @error($key)<p class="text-xs text-rose-400"><i class="fas fa-circle-exclamation mr-1"></i>{{ $message }}</p>@enderror
</div>
