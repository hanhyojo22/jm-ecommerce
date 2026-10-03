<x-app-layout>
    <x-slot name="header">
        <div class="mx-auto max-w-3xl"><p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">Seller center</p><h2 class="mt-1 text-2xl font-semibold tracking-[-0.03em] text-slate-950">Start selling on JMMart</h2></div>
    </x-slot>

    <div class="min-h-screen bg-slate-50 py-8 sm:py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
                @if (session('status'))
                    <p class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status">{{ session('status') }}</p>
                @endif

                @if ($application?->status === 'pending')
                    <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">Your application is under review. We will notify you after it is approved.</p>
                @else
                    @if ($application?->status === 'rejected')
                        <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900"><p class="font-semibold">Your previous application was not approved.</p>@if (filled($application->review_note))<p class="mt-1">{{ $application->review_note }}</p>@endif<p class="mt-2">Update the details below and submit a new application.</p></div>
                    @elseif ($application?->status === 'approved')
                        <p class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">Your application has been approved. Your seller workspace will be available once the store is active.</p>
                    @endif
                    <form method="POST" action="{{ route('seller-application.store') }}" x-data="{ submitting: false }" @submit="submitting = true" class="space-y-6">
                        @csrf
                        <div>
                            <x-input-label for="store_name" value="Store name" />
                            <x-text-input id="store_name" name="store_name" class="mt-1 block w-full" value="{{ old('store_name', $application?->store_name) }}" required autofocus />
                            <x-input-error :messages="$errors->get('store_name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="store_description" value="Store description" />
                            <textarea id="store_description" name="store_description" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-emerald-700 focus:ring-emerald-700" rows="4">{{ old('store_description', $application?->store_description) }}</textarea>
                            <x-input-error :messages="$errors->get('store_description')" class="mt-2" />
                        </div>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <x-input-label for="seller_name" value="Seller contact name" />
                                <x-text-input id="seller_name" name="seller_name" class="mt-1 block w-full" value="{{ old('seller_name', $application?->seller_name ?? auth()->user()->name) }}" required />
                                <x-input-error :messages="$errors->get('seller_name')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="phone" value="Phone" />
                                <x-text-input id="phone" name="phone" class="mt-1 block w-full" value="{{ old('phone', $application?->phone) }}" required />
                                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="address_line" value="Address" />
                            <x-text-input id="address_line" name="address_line" class="mt-1 block w-full" value="{{ old('address_line', $application?->address_line) }}" required />
                            <x-input-error :messages="$errors->get('address_line')" class="mt-2" />
                        </div>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <x-input-label for="city" value="City" />
                                <x-text-input id="city" name="city" class="mt-1 block w-full" value="{{ old('city', $application?->city) }}" required />
                                <x-input-error :messages="$errors->get('city')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="store_category" value="Store category (optional)" />
                                <x-text-input id="store_category" name="store_category" class="mt-1 block w-full" value="{{ old('store_category', $application?->store_category) }}" />
                                <x-input-error :messages="$errors->get('store_category')" class="mt-2" />
                            </div>
                        </div>
                        <x-primary-button x-bind:disabled="submitting"><span x-show="!submitting">Submit application</span><span x-cloak x-show="submitting">Submitting…</span></x-primary-button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
