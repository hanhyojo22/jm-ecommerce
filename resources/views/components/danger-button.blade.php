<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-11 items-center rounded-xl border border-transparent bg-red-700 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-200 hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
