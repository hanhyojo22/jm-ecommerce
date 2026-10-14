@php
    $type = $type ?? 'text';
    $value = $value ?? $application?->{$name};
    $inputClass = 'mt-1 block min-h-11 w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand';
@endphp
<div>
    <label for="{{ $name }}" class="block text-sm font-semibold text-ink">{{ $label }}</label>
    @if ($textarea ?? false)
        <textarea id="{{ $name }}" name="{{ $name }}" rows="4" @if ($required ?? false) required @endif class="{{ $inputClass }}">{{ old($name, $value) }}</textarea>
    @else
        @if ($type === 'file')
            <div x-data="{ preview: null }"><input id="{{ $name }}" name="{{ $name }}" type="file" @if ($accept ?? false) accept="{{ $accept }}" @endif @change="if (preview) URL.revokeObjectURL(preview); preview = $event.target.files[0]?.type.startsWith('image/') ? URL.createObjectURL($event.target.files[0]) : null" class="{{ $inputClass }}"><img x-cloak x-show="preview" :src="preview" alt="Selected image preview" class="mt-2 size-24 rounded-lg border border-line object-cover"></div>
        @else
            <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value instanceof \Carbon\CarbonInterface ? $value->toDateString() : $value) }}" @if ($required ?? false) required @endif class="{{ $inputClass }}">
        @endif
    @endif
    @if ($help ?? false)<p class="mt-1 text-xs text-muted">{{ $help }}</p>@endif
    <x-input-error :messages="$errors->get($name)" class="mt-1" />
</div>
