<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-11 items-center justify-center rounded-[10px] border border-transparent bg-danger px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-danger focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60']) }}>
    {{ $slot }}
</button>
