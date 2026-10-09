<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('seller.dashboard') }}" class="text-sm font-semibold text-brand underline decoration-blue-300 underline-offset-4">Seller dashboard</a>
            <h1 class="mt-2 text-[28px] font-bold leading-[1.35] tracking-[-0.02em] text-ink">Store settings</h1>
            <p class="mt-2 text-sm text-muted">Update the details customers see on your {{ $store->name }} storefront.</p>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container max-w-4xl">
            @if (session('status') || $canUndo)
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-[14px] border border-success/20 bg-success/10 px-4 py-3" role="status">
                    <p class="text-sm font-medium text-success">{{ session('status', 'A recent text-only save can still be undone.') }}</p>
                    @if ($canUndo)
                        <form method="POST" action="{{ route('seller.store-settings.undo') }}">
                            @csrf
                            <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-[10px] bg-white px-4 text-sm font-semibold text-ink ring-1 ring-inset ring-success/30 transition hover:bg-success/10 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Undo last save</button>
                        </form>
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ route('seller.store-settings.update') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                @method('PATCH')
                <x-input-error :messages="$errors->get('settings')" class="rounded-[10px] border border-rose-200 bg-rose-50 px-4 py-3" />
                <x-input-error :messages="$errors->get('undo')" class="rounded-[10px] border border-rose-200 bg-rose-50 px-4 py-3" />

                <section class="rounded-[14px] border border-line bg-white p-5 shadow-sm sm:p-6" aria-labelledby="store-details-heading">
                    <h2 id="store-details-heading" class="text-lg font-semibold text-ink">Store details</h2>
                    <p class="mt-1 text-sm text-muted">These details appear on your public storefront.</p>

                    <div class="mt-5 space-y-5">
                        <div>
                            <label for="description" class="text-sm font-semibold text-ink">Store description</label>
                            <textarea id="description" name="description" rows="4" maxlength="2000" @error('description') aria-describedby="description-error" aria-invalid="true" @enderror class="mt-1.5 block w-full rounded-[10px] border-line text-sm shadow-sm focus:border-brand focus:ring-brand">{{ old('description', $store->description) }}</textarea>
                            <p class="mt-1 text-xs text-muted">Up to 2,000 characters.</p>
                            <x-input-error id="description-error" :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        <div>
                            <label for="logo" class="text-sm font-semibold text-ink">Store logo</label>
                            <p class="mt-1 text-sm text-muted">JPEG, PNG, or WebP, up to 2 MB.</p>
                            @if ($store->logo_path)
                                <img src="{{ Storage::disk('public')->url($store->logo_path) }}" alt="Current {{ $store->name }} logo" class="mt-3 size-20 rounded-xl border border-line object-cover">
                            @endif
                            <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" @error('logo') aria-describedby="logo-error" aria-invalid="true" @enderror class="mt-3 block min-h-11 w-full text-sm text-muted file:mr-4 file:min-h-10 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:font-semibold file:text-brand hover:file:bg-blue-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                            <x-input-error id="logo-error" :messages="$errors->get('logo')" class="mt-2" />
                            <p class="mt-1 text-xs text-muted">Choose a replacement or remove the current logo. Logo changes cannot be undone here.</p>
                            @if ($store->logo_path)
                                <label class="mt-3 inline-flex min-h-10 items-center gap-2 text-sm text-ink">
                                    <input type="checkbox" name="remove_logo" value="1" @checked(old('remove_logo')) @error('remove_logo') aria-describedby="remove-logo-error" aria-invalid="true" @enderror class="rounded border-line text-brand focus:ring-brand">
                                    Remove current logo when saved
                                </label>
                                <x-input-error id="remove-logo-error" :messages="$errors->get('remove_logo')" class="mt-2" />
                            @endif
                        </div>
                    </div>
                </section>

                <section class="rounded-[14px] border border-line bg-white p-5 shadow-sm sm:p-6" aria-labelledby="contact-details-heading">
                    <h2 id="contact-details-heading" class="text-lg font-semibold text-ink">Contact details</h2>
                    <p class="mt-1 text-sm text-muted">Keep these details current for store and order follow-up.</p>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="phone" class="text-sm font-semibold text-ink">Phone <span class="font-normal text-muted">(required)</span></label>
                            <input id="phone" name="phone" type="tel" autocomplete="tel" maxlength="30" required @error('phone') aria-describedby="phone-error" aria-invalid="true" @enderror value="{{ old('phone', $store->phone) }}" class="mt-1.5 block min-h-11 w-full rounded-[10px] border-line text-sm shadow-sm focus:border-brand focus:ring-brand">
                            <x-input-error id="phone-error" :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                        <div>
                            <label for="city" class="text-sm font-semibold text-ink">City <span class="font-normal text-muted">(required)</span></label>
                            <input id="city" name="city" autocomplete="address-level2" maxlength="100" required @error('city') aria-describedby="city-error" aria-invalid="true" @enderror value="{{ old('city', $store->city) }}" class="mt-1.5 block min-h-11 w-full rounded-[10px] border-line text-sm shadow-sm focus:border-brand focus:ring-brand">
                            <x-input-error id="city-error" :messages="$errors->get('city')" class="mt-2" />
                        </div>
                        <div class="sm:col-span-2">
                            <label for="address_line" class="text-sm font-semibold text-ink">Street address <span class="font-normal text-muted">(required)</span></label>
                            <input id="address_line" name="address_line" autocomplete="street-address" maxlength="255" required @error('address_line') aria-describedby="address-line-error" aria-invalid="true" @enderror value="{{ old('address_line', $store->address_line) }}" class="mt-1.5 block min-h-11 w-full rounded-[10px] border-line text-sm shadow-sm focus:border-brand focus:ring-brand">
                            <x-input-error id="address-line-error" :messages="$errors->get('address_line')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <a href="{{ route('seller.dashboard') }}" class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-line bg-white px-5 text-sm font-semibold text-ink transition hover:border-brand focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Cancel</a>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Save store settings</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
