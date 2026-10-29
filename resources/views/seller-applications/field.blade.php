@php
    $type = $type ?? 'text';
    $value = $value ?? $application?->{$name};
    $saved = $saved ?? false;
@endphp
<div @class([$class ?? null])>
    @if ($type === 'file')
        <div x-data="{ preview: null, fileName: '' }" class="onboarding-upload">
            <input id="{{ $name }}" name="{{ $name }}" type="file" @if ($accept ?? false) accept="{{ $accept }}" @endif @change="if (preview) URL.revokeObjectURL(preview); const file = $event.target.files[0]; fileName = file?.name ?? ''; preview = file?.type.startsWith('image/') ? URL.createObjectURL(file) : null; $dispatch('file-preview', { name: @js($name), url: preview })" class="peer sr-only">
            <label for="{{ $name }}" @class(['onboarding-upload-box', 'is-done' => $saved])>
                <span class="onboarding-upload-thumb" aria-hidden="true">
                    @if ($saved)
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12l5 5L20 7"/></svg>
                    @else
                        <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 16V4m-4 4 4-4 4 4"/><path d="M5 15v3a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-3"/></svg>
                    @endif
                </span>
                <span class="onboarding-upload-copy">
                    <span class="onboarding-upload-title">{{ $label }}@if (! $saved && ($required ?? false))<span class="onboarding-required-tag">Required</span>@elseif (! $saved && ($requiredWhen ?? false))<span x-show="{{ $requiredWhen }}" class="onboarding-required-tag">Required</span>@endif</span>
                    <span class="onboarding-upload-help" x-text="fileName || @js($saved ? 'Saved' : ($help ?? 'Tap to choose a file'))">{{ $saved ? 'Saved' : ($help ?? 'Tap to choose a file') }}</span>
                </span>
                <span class="onboarding-upload-action" aria-hidden="true">{{ $saved ? 'Replace' : 'Upload' }}</span>
            </label>
            <img x-cloak x-show="preview" :src="preview" alt="Selected image preview" class="onboarding-upload-preview">
        </div>
    @else
        <label for="{{ $name }}" class="mb-1.5 block text-sm font-semibold text-ink">{{ $label }}</label>
        @if ($textarea ?? false)
            <textarea id="{{ $name }}" name="{{ $name }}" rows="4" @if ($required ?? false) required @endif @if ($maxlength ?? false) maxlength="{{ $maxlength }}" @endif class="field-control">{{ old($name, $value) }}</textarea>
        @elseif ($prefix ?? false)
            <div class="flex overflow-hidden rounded-md border border-line bg-white focus-within:border-brand focus-within:ring-1 focus-within:ring-brand">
                <span class="flex shrink-0 items-center border-r border-line bg-canvas px-3 text-sm text-muted">{{ $prefix }}</span>
                <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @if ($required ?? false) required @endif class="block min-h-11 w-full min-w-0 border-0 text-sm text-ink focus:ring-0">
            </div>
        @else
            <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value instanceof \Carbon\CarbonInterface ? $value->toDateString() : $value) }}" @if ($required ?? false) required @endif @if ($requiredWhen ?? false) :required="{{ $requiredWhen }}" @endif @if ($inputmode ?? false) inputmode="{{ $inputmode }}" @endif @if ($placeholder ?? false) placeholder="{{ $placeholder }}" @endif class="field-control">
        @endif
        @if ($help ?? false)<p class="mt-1.5 text-xs text-muted">{{ $help }}</p>@endif
    @endif
    <x-input-error :messages="$errors->get($name)" class="mt-1" />
</div>
