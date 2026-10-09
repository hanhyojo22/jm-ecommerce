<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-[28px] font-bold tracking-[-0.02em] text-ink leading-[1.55]">Audit log</h1>
            <p class="mt-2 text-sm text-muted">Review the administrator, action, target, time, and source address for recorded activity.</p>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container">
            <section class="overflow-hidden rounded-[14px] border border-line bg-surface" aria-label="Administrative activity log">
                <div class="table-scroll hidden lg:block">
                    <table class="min-w-[900px] divide-y divide-line text-sm">
                        <thead class="bg-canvas text-left text-xs font-semibold uppercase tracking-[0.1em] text-muted">
                            <tr>
                                <th scope="col" class="px-5 py-3.5">Time</th>
                                <th scope="col" class="px-5 py-3.5">Admin</th>
                                <th scope="col" class="px-5 py-3.5">Action</th>
                                <th scope="col" class="px-5 py-3.5">Target</th>
                                <th scope="col" class="px-5 py-3.5">IP address</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($logs as $log)
                                <tr class="transition-colors hover:bg-canvas/70">
                                    <td class="whitespace-nowrap px-5 py-4 text-muted">
                                        <time datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('M j, Y · g:i A') }}</time>
                                    </td>
                                    <td class="px-5 py-4">
                                        <p class="font-medium text-ink">{{ $log->actor?->name ?? 'System' }}</p>
                                        @if ($log->actor?->email)<p class="mt-0.5 text-xs text-muted">{{ $log->actor->email }}</p>@endif
                                    </td>
                                    <td class="px-5 py-4 font-medium text-ink">{{ str($log->action)->replace('.', ' ')->headline() }}</td>
                                    <td class="px-5 py-4">
                                        <p class="text-ink">{{ str(class_basename($log->entity_type ?? 'record'))->headline() }}</p>
                                        <p class="mt-0.5 max-w-56 truncate text-xs text-muted" title="{{ $log->entity_id }}">{{ $log->entity_id }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-muted">{{ $log->ip_address ?? 'Unavailable' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-6 py-16 text-center"><p class="font-semibold text-ink">No audit entries yet</p><p class="mt-2 text-sm text-muted">Administrative decisions will appear here when they are recorded.</p></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-line lg:hidden">
                    @forelse ($logs as $log)
                        <article class="space-y-3 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <p class="font-semibold text-ink">{{ str($log->action)->replace('.', ' ')->headline() }}</p>
                                <time class="text-xs text-muted" datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('M j, Y · g:i A') }}</time>
                            </div>
                            <dl class="grid grid-cols-2 gap-3 text-sm">
                                <div class="min-w-0"><dt class="text-xs font-medium text-muted">Admin</dt><dd class="mt-1 truncate font-medium text-ink">{{ $log->actor?->name ?? 'System' }}</dd></div>
                                <div class="min-w-0"><dt class="text-xs font-medium text-muted">Target</dt><dd class="mt-1 truncate font-medium text-ink" title="{{ $log->entity_id }}">{{ str(class_basename($log->entity_type ?? 'record'))->headline() }} · {{ $log->entity_id }}</dd></div>
                                <div class="min-w-0 col-span-2"><dt class="text-xs font-medium text-muted">IP address</dt><dd class="mt-1 font-medium text-ink">{{ $log->ip_address ?? 'Unavailable' }}</dd></div>
                            </dl>
                        </article>
                    @empty
                        <div class="px-6 py-14 text-center"><p class="font-semibold text-ink">No audit entries yet</p><p class="mt-2 text-sm text-muted">Administrative decisions will appear here when they are recorded.</p></div>
                    @endforelse
                </div>

                @if ($logs->hasPages())
                    <div class="border-t border-line bg-canvas px-4 py-4">{{ $logs->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
