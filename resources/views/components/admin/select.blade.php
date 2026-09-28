{{-- Liste déroulante. `options` : [valeur => libellé] ; `placeholder` ajoute
     une première option vide. --}}
@props(['name', 'label', 'options' => [], 'value' => null, 'required' => false, 'hint' => null, 'placeholder' => null])
@php $key = str_replace(['[', ']'], ['.', ''], $name); @endphp
@php
    $id = 'f_' . str_replace(['[', ']', '.'], '_', $name);
    $current = (string) old($key, $value);
@endphp
<div {{ $attributes->only('class')->class(['space-y-1.5']) }}>
    <label for="{{ $id }}" class="block text-sm font-medium text-gray-300">
        {{ $label }} @if($required)<span class="text-rose-400">*</span>@endif
    </label>
    <select id="{{ $id }}" name="{{ $name }}" @if($required) required @endif {{ $attributes->except('class') }}
            @class([
                'w-full bg-dark-50 border rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition',
                'border-rose-500' => $errors->has($key),
                'border-dark-200' => ! $errors->has($key),
            ])>
        @if($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected($current === (string) $optValue)>{{ $optLabel }}</option>
        @endforeach
    </select>
    @if($hint && ! $errors->has($key))<p class="text-xs text-gray-500">{{ $hint }}</p>@endif
    @error($key)<p class="text-xs text-rose-400"><i class="fas fa-circle-exclamation mr-1"></i>{{ $message }}</p>@enderror
</div>
