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
        <div class="page-container seller-workspace-page">
            <form method="POST" action="{{ route('seller.branches.store') }}"
                x-data="{ saving: false }" @submit="saving = true"
                class="sc-card grid max-w-2xl gap-5 p-5 sm:p-6">
                @csrf

                <ol class="sc-branch-steps" aria-label="Branch setup steps">
                    <li class="is-current"><span>1</span> Branch details</li>
                    <li><span>2</span> Map pin &amp; hours</li>
                    <li><span>3</span> Open to customers</li>
                </ol>

                @php
                    $fields = [
                        ['name' => 'name', 'label' => 'Branch name', 'autocomplete' => 'organization', 'max' => 120, 'type' => 'text', 'placeholder' => 'e.g. Everyday Pharmacy — Cebu Capitol', 'wide' => false],
                        ['name' => 'phone', 'label' => 'Phone', 'autocomplete' => 'tel', 'max' => 30, 'type' => 'tel', 'placeholder' => '+63 917 123 4567', 'wide' => false],
                        ['name' => 'address_line', 'label' => 'Street address', 'autocomplete' => 'street-address', 'max' => 255, 'type' => 'text', 'placeholder' => 'e.g. Escario St., Capitol Site', 'wide' => true],
                        ['name' => 'city', 'label' => 'City', 'autocomplete' => 'address-level2', 'max' => 100, 'type' => 'text', 'placeholder' => 'e.g. Cebu City', 'wide' => true],
                    ];
                @endphp

                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach ($fields as $field)
                        <div @class(['min-w-0', 'sm:col-span-2' => $field['wide']])>
                            <label for="{{ $field['name'] }}" class="block text-sm font-semibold text-ink">{{ $field['label'] }}</label>
                            <input id="{{ $field['name'] }}" name="{{ $field['name'] }}" type="{{ $field['type'] }}" value="{{ old($field['name']) }}"
                                autocomplete="{{ $field['autocomplete'] }}" maxlength="{{ $field['max'] }}" placeholder="{{ $field['placeholder'] }}" required
                                @error($field['name']) aria-invalid="true" aria-describedby="{{ $field['name'] }}-error" @enderror
                                class="field-control mt-2 text-base lg:text-sm">
                            <x-input-error id="{{ $field['name'] }}-error" :messages="$errors->get($field['name'])" class="mt-2" />
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-wrap gap-3 border-t border-line pt-5">
                    <button type="submit" :disabled="saving" :aria-busy="saving" class="seller-primary-button min-h-11 disabled:opacity-60">
                        <span x-show="!saving">Add branch</span>
                        <span x-cloak x-show="saving">Adding branch…</span>
                    </button>
                    <a href="{{ route('seller.branches.index') }}" class="seller-outline-button min-h-11">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
