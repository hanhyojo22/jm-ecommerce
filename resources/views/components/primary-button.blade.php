<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-11 items-center rounded-xl border border-transparent bg-emerald-700 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-200 hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 active:scale-[0.99]']) }}>
    {{ $slot }}
</button>
