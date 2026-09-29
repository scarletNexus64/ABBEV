{{-- Cases à cocher des modules délégués à un membre de l'équipe. --}}
@php $checked = old('permissions', $member->permissions ?? []); @endphp
<div x-data="{ all() { $root.querySelectorAll('input[name=\'permissions[]\']').forEach(c => c.checked = true) },
               none() { $root.querySelectorAll('input[name=\'permissions[]\']').forEach(c => c.checked = false) } }">
    <div class="flex items-center justify-between mb-3">
        <p class="text-sm text-gray-300">Modules accessibles <span class="text-red-400">*</span></p>
        <div class="flex gap-3 text-xs">
            <button type="button" @click="all()" class="text-primary-400 hover:text-primary-300">Tout cocher</button>
            <button type="button" @click="none()" class="text-gray-400 hover:text-gray-300">Tout décocher</button>
        </div>
    </div>
    <div class="grid sm:grid-cols-2 gap-2">
        @foreach($modules as $key => $module)
            <label class="flex items-center gap-3 bg-dark-50 border border-dark-200 hover:border-primary-500/40 rounded-lg px-4 py-3 cursor-pointer transition has-[:checked]:border-primary-500/60 has-[:checked]:bg-primary-500/10">
                <input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, $checked, true))
                       class="rounded border-dark-200 bg-dark-100 text-primary-500 focus:ring-primary-500">
                <i class="fas fa-{{ $module['icon'] }} w-4 text-center text-primary-400"></i>
                <span class="text-sm text-gray-200">{{ $module['label'] }}</span>
            </label>
        @endforeach
    </div>
    @error('permissions')<p class="mt-2 text-sm text-red-400"><i class="fas fa-exclamation-circle mr-1"></i> Cochez au moins un module.</p>@enderror
    @error('permissions.*')<p class="mt-2 text-sm text-red-400"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>@enderror
</div>
