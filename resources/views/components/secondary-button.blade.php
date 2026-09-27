<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 shadow-sm transition duration-200 hover:border-slate-950 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:opacity-25']) }}>
    {{ $slot }}
</button>
