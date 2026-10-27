@php
    $type = $type ?? 'text';
    $value = $value ?? $application?->{$name};
    $saved = $saved ?? false;
@endphp
<div @class([$class ?? null])>
    @if ($type === 'file')
        <div x-data="{ preview: null, fileName: '' }">
            <input id="{{ $name }}" name="{{ $name }}" type="file" @if ($accept ?? false) accept="{{ $accept }}" @endif @change="if (preview) URL.revokeObjectURL(preview); const file = $event.target.files[0]; fileName = file?.name ?? ''; preview = file?.type.startsWith('image/') ? URL.createObjectURL(file) : null; $dispatch('file-preview', { name: @js($name), url: preview })" class="peer sr-only">
            <label for="{{ $name }}" @class(['flex min-h-[72px] cursor-pointer items-center gap-3 rounded-xl border-[1.5px] p-4 transition peer-focus-visible:ring-2 peer-focus-visible:ring-brand peer-focus-visible:ring-offset-2', 'border-success/30 bg-success/5' => $saved, 'border-dashed border-slate-300 bg-slate-50/60 hover:border-brand hover:bg-brand/5' => ! $saved])>
                <span @class(['grid size-10 flex-none place-items-center rounded-[10px] border bg-white', 'border-success/30 text-success' => $saved, 'border-line text-brand' => ! $saved]) aria-hidden="true">
                    @if ($saved)
                        <svg class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12l5 5L20 7"/></svg>
                    @else
                        <svg class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 16V4m0 0L7 9m5-5 5 5M4 20h16"/></svg>
                    @endif
                </span>
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-ink">{{ $label }}</span>
                    <span class="block truncate text-xs text-muted" x-text="fileName || @js($saved ? 'Saved' : ($help ?? 'Tap to choose a file'))">{{ $saved ? 'Saved' : ($help ?? 'Tap to choose a file') }}</span>
                </span>
                <span @class(['ml-auto flex-none text-xs font-semibold', 'text-success' => $saved, 'text-brand' => ! $saved]) aria-hidden="true">{{ $saved ? 'Replace' : 'Browse' }}</span>
            </label>
            <img x-cloak x-show="preview" :src="preview" alt="Selected image preview" class="mt-2 size-20 rounded-lg border border-line object-cover">
        </div>
    @else
        <label for="{{ $name }}" class="mb-1.5 block text-sm font-semibold text-ink">{{ $label }}</label>
        @if ($textarea ?? false)
            <textarea id="{{ $name }}" name="{{ $name }}" rows="4" @if ($required ?? false) required @endif @if ($maxlength ?? false) maxlength="{{ $maxlength }}" @endif class="field-control">{{ old($name, $value) }}</textarea>
        @elseif ($prefix ?? false)
            <div class="flex overflow-hidden rounded-[10px] border border-line bg-white focus-within:border-brand focus-within:ring-1 focus-within:ring-brand">
                <span class="flex max-w-[55%] items-center truncate border-r border-line bg-slate-50 px-3 text-sm text-muted">{{ $prefix }}</span>
                <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @if ($required ?? false) required @endif class="block min-h-11 w-full min-w-0 border-0 text-sm text-ink focus:ring-0">
            </div>
        @else
            <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value instanceof \Carbon\CarbonInterface ? $value->toDateString() : $value) }}" @if ($required ?? false) required @endif @if ($inputmode ?? false) inputmode="{{ $inputmode }}" @endif @if ($placeholder ?? false) placeholder="{{ $placeholder }}" @endif class="field-control">
        @endif
        @if ($help ?? false)<p class="mt-1.5 text-xs text-muted">{{ $help }}</p>@endif
    @endif
    <x-input-error :messages="$errors->get($name)" class="mt-1" />
</div>
