@props(['status'])
<span {{ $attributes->class(['status-badge', match ($status) {
    'active' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
    'pending' => 'bg-blue-50 text-blue-800 ring-blue-200',
    'changes_required' => 'bg-amber-50 text-amber-900 ring-amber-200',
    'rejected', 'inactive' => 'bg-rose-50 text-rose-800 ring-rose-200',
    default => 'bg-slate-100 text-slate-700 ring-slate-200',
}]) }}>{{ \App\Models\Product::STATUSES[$status] }}</span>
