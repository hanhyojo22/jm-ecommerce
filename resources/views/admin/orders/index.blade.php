<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Marketplace orders</h2></x-slot>
    <div class="py-12"><div class="mx-auto max-w-6xl sm:px-6 lg:px-8"><div class="bg-white p-6 shadow-sm sm:rounded-lg">
        @forelse($orders as $order)
            <div class="flex justify-between border-b py-4"><div><p class="font-semibold">{{ $order->number }}</p><p class="text-sm text-gray-500">{{ $order->recipient_name }} · {{ ucfirst($order->status) }} · COD {{ ucfirst($order->payment->status) }}</p></div><p class="font-semibold">₱{{ number_format($order->total,2) }}</p></div>
        @empty <p class="text-gray-600">No orders yet.</p>@endforelse
        @if ($orders->hasPages())<div class="pt-6">{{ $orders->links() }}</div>@endif
    </div></div></div>
</x-app-layout>
