<x-app-layout :error-summary="false">
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <h1 class="sc-title">Add a branch</h1>
                <p class="sc-subtitle">The branch starts closed. Next you set its map pin, delivery radius and stock, then open it.</p>
            </div>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container seller-workspace-page">
            <form method="POST" action="{{ route('seller.branches.store') }}" class="sc-card grid max-w-2xl gap-5 p-5 sm:p-6">
                @csrf
                @foreach ([
                    ['name', 'Branch name', 'organization', 120, 'text'],
                    ['phone', 'Phone', 'tel', 30, 'tel'],
                    ['address_line', 'Street address', 'street-address', 255, 'text'],
                    ['city', 'City', 'address-level2', 100, 'text'],
                ] as [$field, $label, $autocomplete, $max, $type])
                    <div>
                        <label for="{{ $field }}" class="block text-sm font-semibold text-ink">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field) }}" autocomplete="{{ $autocomplete }}" maxlength="{{ $max }}" required
                            @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror class="field-control mt-2 text-base lg:text-sm">
                        <x-input-error id="{{ $field }}-error" :messages="$errors->get($field)" class="mt-2" />
                    </div>
                @endforeach
                <div class="flex gap-3">
                    <button type="submit" class="seller-primary-button min-h-11">Add branch</button>
                    <a href="{{ route('seller.branches.index') }}" class="seller-outline-button min-h-11">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
