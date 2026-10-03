@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'block min-h-11 w-full rounded-lg border-slate-300 text-sm text-slate-950 shadow-sm placeholder:text-slate-400 focus:border-emerald-700 focus:ring-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500']) }}>
