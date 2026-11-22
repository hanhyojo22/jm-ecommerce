@php
    $steps = [
        ['Branch details', 'Name, phone, and address.'],
        ['Map pin & hours', 'Set the delivery radius and opening hours.'],
        ['Stock & staff', 'Assign stock, then add the people who work there.'],
        ['Open to customers', 'Open the branch when it is ready.'],
    ];
    $fields = [
        ['name' => 'name', 'label' => 'Branch name', 'autocomplete' => 'organization', 'max' => 120, 'type' => 'text', 'placeholder' => 'e.g. Everyday Pharmacy — Cebu Capitol', 'help' => 'Shown to customers in search, checkout, and receipts.', 'wide' => true],
        ['name' => 'phone', 'label' => 'Phone', 'autocomplete' => 'tel', 'max' => 30, 'type' => 'tel', 'placeholder' => '+63 917 123 4567', 'help' => null, 'wide' => false],
        ['name' => 'city', 'label' => 'City', 'autocomplete' => 'address-level2', 'max' => 100, 'type' => 'text', 'placeholder' => 'e.g. Cebu City', 'help' => null, 'wide' => false],
        ['name' => 'address_line', 'label' => 'Street address', 'autocomplete' => 'street-address', 'max' => 255, 'type' => 'text', 'placeholder' => 'e.g. Escario St., Capitol Site', 'help' => null, 'wide' => true],
    ];
@endphp

<x-app-layout :error-summary="false">
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <a href="{{ route('seller.branches.index') }}" class="sc-back">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    Branches
                </a>
                <h1 class="sc-title">Add a branch</h1>
                <p class="sc-subtitle">The branch starts closed. Next you set its map pin, delivery radius and stock, then open it.</p>
            </div>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container seller-workspace-page sc-add-branch"
            x-data="{ name: @js(old('name', '')), phone: @js(old('phone', '')), city: @js(old('city', '')), address_line: @js(old('address_line', '')) }">
            <form method="POST" action="{{ route('seller.branches.store') }}"
                x-data="{ saving: false }" @submit="saving = true"
                class="sc-card sc-add-branch-form">
                @csrf

                <div class="sc-add-branch-body">
                    <h2>Branch details</h2>
                    <p>Only what customers need to recognize this location.</p>

                    <div class="sc-add-branch-fields">
                        @foreach ($fields as $field)
                            <div @class(['min-w-0', 'sm:col-span-2' => $field['wide']])>
                                <label for="{{ $field['name'] }}" class="block text-sm font-semibold text-ink">{{ $field['label'] }}</label>
                                <input id="{{ $field['name'] }}" name="{{ $field['name'] }}" type="{{ $field['type'] }}" value="{{ old($field['name']) }}"
                                    x-model="{{ $field['name'] }}"
                                    autocomplete="{{ $field['autocomplete'] }}" maxlength="{{ $field['max'] }}" placeholder="{{ $field['placeholder'] }}" required
                                    @php($describedBy = trim(($field['help'] ? $field['name'].'-help ' : '').($errors->has($field['name']) ? $field['name'].'-error' : '')))
                                    @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                                    @error($field['name']) aria-invalid="true" @enderror
                                    class="field-control mt-2 text-base lg:text-sm">
                                @if ($field['help'])<p id="{{ $field['name'] }}-help" class="mt-1.5 text-xs text-muted">{{ $field['help'] }}</p>@endif
                                <x-input-error id="{{ $field['name'] }}-error" :messages="$errors->get($field['name'])" class="mt-2" />
                            </div>
                        @endforeach
                    </div>

                    <p class="sc-add-branch-note">
                        <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg>
                        <span><strong>Added as closed.</strong> Set the map pin, delivery radius, and stock before opening it to customers.</span>
                    </p>
                </div>

                <div class="sc-add-branch-foot">
                    <a href="{{ route('seller.branches.index') }}" class="seller-outline-button">Cancel</a>
                    <button type="submit" :disabled="saving" :aria-busy="saving" class="seller-primary-button disabled:opacity-60">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        <span x-show="!saving">Add branch</span>
                        <span x-cloak x-show="saving">Adding branch…</span>
                    </button>
                </div>
            </form>

            <aside class="sc-add-branch-rail" aria-label="About the new branch">
                <section class="sc-card sc-add-branch-rail-card" aria-labelledby="branch-preview-title">
                    <h2 id="branch-preview-title" class="sc-add-branch-rail-label">Preview in the directory</h2>
                    <div class="sc-add-branch-preview">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="sc-directory-tile" data-tone="neutral">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 9.5 5 4h14l2 5.5M4 10h16v10H4zM9 20v-6h6v6M3 9.5a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/></svg>
                            </span>
                            <div class="min-w-0">
                                <p class="sc-add-branch-preview-name" x-text="name.trim() || 'New branch'">New branch</p>
                                <p class="sc-add-branch-preview-meta" x-text="[address_line.trim(), city.trim()].filter(Boolean).join(', ') || 'Street address, city'">Street address, city</p>
                            </div>
                        </div>
                        <span class="sc-badge mt-3" data-tone="neutral">Closed</span>
                        <dl class="sc-add-branch-preview-facts">
                            <div><dt class="sr-only">Phone</dt><dd x-text="phone.trim() || 'Phone not set'">Phone not set</dd></div>
                            <div><dt class="sr-only">Map</dt><dd>No map pin yet</dd></div>
                        </dl>
                    </div>
                    <p class="sc-add-branch-rail-note">Preview only. The branch appears in the directory as soon as you save it.</p>
                </section>

                <section class="sc-card sc-add-branch-rail-card" aria-labelledby="branch-next-title">
                    <h2 id="branch-next-title" class="sc-add-branch-rail-label">What happens next</h2>
                    <ol class="sc-add-branch-steps">
                        @foreach ($steps as [$title, $detail])
                            <li @class(['is-current' => $loop->first]) @if ($loop->first) aria-current="step" @endif>
                                <span class="sc-add-branch-step-num">{{ $loop->iteration }}</span>
                                <span><strong>{{ $title }}</strong><span>{{ $detail }}</span></span>
                            </li>
                        @endforeach
                    </ol>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
