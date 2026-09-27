<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Audit log</h2></x-slot>
    <div class="py-12"><div class="mx-auto max-w-6xl sm:px-6 lg:px-8"><div class="bg-white p-6 shadow-sm sm:rounded-lg">
        @forelse($logs as $log)
            <div class="border-b py-3"><b>{{ $log->action }}</b><span class="text-sm text-gray-500"> · {{ $log->actor?->email ?? 'System' }} · {{ $log->created_at }}</span></div>
        @empty
            <p>No audit entries.</p>
        @endforelse
        @if($logs->hasPages())<div class="pt-6">{{ $logs->links() }}</div>@endif
    </div></div></div>
</x-app-layout>
