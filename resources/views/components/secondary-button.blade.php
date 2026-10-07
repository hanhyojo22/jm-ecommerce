<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex min-h-11 items-center justify-center rounded-[10px] border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition hover:bg-canvas focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60']) }}>
    {{ $slot }}
</button>
