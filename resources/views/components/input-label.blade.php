@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-semibold text-muted']) }}>
    {{ $value ?? $slot }}
</label>
