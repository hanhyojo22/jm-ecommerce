<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-11 items-center justify-center rounded-[10px] border border-transparent bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60']) }}>
    {{ $slot }}
</button>
