<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-[28px] font-bold leading-[1.35] tracking-[-0.02em] text-ink">Store settings</h1>
                <p class="mt-1 text-sm text-muted">Manage the details customers use to recognize and contact your store.</p>
            </div>
            <a href="{{ route('catalog.store', $store->slug) }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-[10px] border border-line bg-white px-4 text-sm font-semibold text-ink transition hover:border-brand hover:text-brand focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">View storefront <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5"/></svg></a>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container max-w-6xl">
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

            <div class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_19rem] xl:items-start">
            <form method="POST" action="{{ route('seller.store-settings.update') }}" enctype="multipart/form-data" class="min-w-0 space-y-5">
                @csrf
                @method('PATCH')
                <x-input-error :messages="$errors->get('settings')" class="rounded-[10px] border border-rose-200 bg-rose-50 px-4 py-3" />
                <x-input-error :messages="$errors->get('undo')" class="rounded-[10px] border border-rose-200 bg-rose-50 px-4 py-3" />

                <section class="section-card shadow-sm" aria-labelledby="store-details-heading">
                    <div class="flex items-start gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-[10px] bg-blue-50 text-sm font-bold text-brand" aria-hidden="true">01</span>
                        <div>
                            <h2 id="store-details-heading" class="text-lg font-semibold text-ink">Store profile</h2>
                            <p class="mt-1 text-sm text-muted">Introduce your store and keep its visual identity up to date.</p>
                        </div>
                    </div>

                    <div class="mt-6 space-y-6 border-t border-line pt-6">
                        <div>
                            <label for="description" class="block text-sm font-semibold text-ink">Store description</label>
                            <p id="description-help" class="mt-1 text-sm text-muted">Tell customers what you sell and what makes your store distinct. Up to 2,000 characters.</p>
                            <textarea id="description" name="description" rows="5" maxlength="2000" aria-describedby="description-help @error('description') description-error @enderror" @error('description') aria-invalid="true" @enderror class="field-control mt-3 resize-y">{{ old('description', $store->description) }}</textarea>
                            <x-input-error id="description-error" :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        <div class="border-t border-line pt-6">
                            <label for="logo" class="block text-sm font-semibold text-ink">Store logo</label>
                            <p id="logo-help" class="mt-1 text-sm text-muted">Use a clear square image. JPEG, PNG, or WebP; up to 2 MB.</p>
                            <div class="mt-4 flex flex-col gap-4 rounded-xl border border-line bg-canvas p-4 sm:flex-row sm:items-center">
                                @if ($store->logo_path)
                                    <img src="{{ Storage::disk('public')->url($store->logo_path) }}" alt="Current {{ $store->name }} logo" class="size-20 shrink-0 rounded-xl border border-line bg-white object-cover">
                                @else
                                    <div class="grid size-20 shrink-0 place-items-center rounded-xl border border-dashed border-line bg-white text-2xl font-bold text-muted" aria-hidden="true">{{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}</div>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-ink">{{ $store->logo_path ? 'Current logo' : 'No logo uploaded yet' }}</p>
                                    <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="logo-help @error('logo') logo-error @enderror" @error('logo') aria-invalid="true" @enderror class="mt-2 block min-h-11 w-full text-sm text-muted file:mr-3 file:min-h-11 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:font-semibold file:text-brand hover:file:bg-blue-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                                </div>
                            </div>
                            <x-input-error id="logo-error" :messages="$errors->get('logo')" class="mt-2" />
                            @if ($store->logo_path)
                                <label class="mt-3 inline-flex min-h-11 items-center gap-3 rounded-lg px-1 text-sm text-ink">
                                    <input type="checkbox" name="remove_logo" value="1" @checked(old('remove_logo')) @error('remove_logo') aria-describedby="remove-logo-error" aria-invalid="true" @enderror class="size-4 rounded border-line text-brand focus:ring-brand">
                                    Remove current logo when saved
                                </label>
                                <x-input-error id="remove-logo-error" :messages="$errors->get('remove_logo')" class="mt-2" />
                            @endif
                            <p class="mt-1 text-xs text-muted">Logo changes cannot be undone from this page.</p>
                        </div>
                    </div>
                </section>

                <section class="section-card shadow-sm" aria-labelledby="contact-details-heading">
                    <div class="flex items-start gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-[10px] bg-blue-50 text-sm font-bold text-brand" aria-hidden="true">02</span>
                        <div>
                            <h2 id="contact-details-heading" class="text-lg font-semibold text-ink">Contact and location</h2>
                            <p class="mt-1 text-sm text-muted">Keep these details accurate for customer and order follow-up.</p>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-5 border-t border-line pt-6 sm:grid-cols-2">
                        <div>
                            <label for="phone" class="text-sm font-semibold text-ink">Phone <span class="font-normal text-muted">(required)</span></label>
                            <input id="phone" name="phone" type="tel" autocomplete="tel" maxlength="30" required @error('phone') aria-describedby="phone-error" aria-invalid="true" @enderror value="{{ old('phone', $store->phone) }}" class="field-control mt-2">
                            <x-input-error id="phone-error" :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                        <div>
                            <label for="city" class="text-sm font-semibold text-ink">City <span class="font-normal text-muted">(required)</span></label>
                            <input id="city" name="city" autocomplete="address-level2" maxlength="100" required @error('city') aria-describedby="city-error" aria-invalid="true" @enderror value="{{ old('city', $store->city) }}" class="field-control mt-2">
                            <x-input-error id="city-error" :messages="$errors->get('city')" class="mt-2" />
                        </div>
                        <div class="sm:col-span-2">
                            <label for="address_line" class="text-sm font-semibold text-ink">Street address <span class="font-normal text-muted">(required)</span></label>
                            <input id="address_line" name="address_line" autocomplete="street-address" maxlength="255" required @error('address_line') aria-describedby="address-line-error" aria-invalid="true" @enderror value="{{ old('address_line', $store->address_line) }}" class="field-control mt-2">
                            <x-input-error id="address-line-error" :messages="$errors->get('address_line')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <div class="flex flex-col-reverse gap-3 rounded-[14px] border border-line bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-muted">Your changes appear on the storefront after you save.</p>
                    <div class="flex flex-col-reverse gap-3 sm:flex-row">
                        <a href="{{ route('seller.dashboard') }}" class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-line bg-white px-5 text-sm font-semibold text-ink transition hover:border-brand focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Cancel</a>
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Save store settings</button>
                    </div>
                </div>
            </form>
            <aside class="min-w-0 rounded-[14px] border border-line bg-white p-5 shadow-sm xl:sticky xl:top-6" aria-label="Store summary">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">Your storefront</p>
                <div class="mt-4 flex min-w-0 items-center gap-3">
                    @if ($store->logo_path)
                        <img src="{{ Storage::disk('public')->url($store->logo_path) }}" alt="" class="size-12 shrink-0 rounded-xl border border-line object-cover">
                    @else
                        <div class="grid size-12 shrink-0 place-items-center rounded-xl bg-blue-50 font-bold text-brand" aria-hidden="true">{{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}</div>
                    @endif
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-ink">{{ $store->name }}</p>
                        <p class="truncate text-xs text-muted">/stores/{{ $store->slug }}</p>
                    </div>
                </div>
                <p class="mt-5 border-t border-line pt-4 text-sm leading-6 text-muted">Your store name and URL are shown here for reference. Edit the profile and contact details on this page, then save to update your storefront.</p>
            </aside>
            </div>
        </div>
    </div>
</x-app-layout>
