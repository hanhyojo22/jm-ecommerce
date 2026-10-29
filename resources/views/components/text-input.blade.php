@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'block min-h-11 w-full rounded-md border-line bg-white text-sm text-ink shadow-none placeholder:text-muted focus:border-brand focus:ring-brand disabled:cursor-not-allowed disabled:bg-canvas disabled:text-muted']) }}>
