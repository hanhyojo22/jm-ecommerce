<x-app-layout :error-summary="false">
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <h1 class="sc-title">Other names</h1>
                <p class="sc-subtitle">Names customers type for an active ingredient. Searching any of them finds every brand with that generic name, on the website and in the app.</p>
            </div>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container seller-workspace-page sc-stack">
            @if (session('status'))<p class="sc-notice" data-tone="success" role="status">{{ session('status') }}</p>@endif

            <form method="POST" action="{{ route('seller.ingredient-aliases.store') }}" class="sc-card flex flex-wrap items-end gap-4 p-4" aria-labelledby="add-alias-heading">
                @csrf
                <h2 id="add-alias-heading" class="w-full font-semibold text-ink">Add another name</h2>
                <div class="min-w-48 flex-1">
                    <label for="alias" class="block text-sm font-semibold text-ink">Customers type</label>
                    <input id="alias" name="alias" required maxlength="100" autocomplete="off" value="{{ old('alias') }}" placeholder="e.g. acetaminophen" @error('alias') aria-invalid="true" aria-describedby="alias-error" @enderror class="field-control mt-2 w-full text-base lg:text-sm">
                    <x-input-error id="alias-error" :messages="$errors->get('alias')" class="mt-2" />
                </div>
                <div class="min-w-48 flex-1">
                    <label for="ingredient" class="block text-sm font-semibold text-ink">Find products with generic name</label>
                    <input id="ingredient" name="ingredient" required maxlength="160" autocomplete="off" list="generic-names" value="{{ old('ingredient') }}" placeholder="e.g. Paracetamol" @error('ingredient') aria-invalid="true" aria-describedby="ingredient-error" @enderror class="field-control mt-2 w-full text-base lg:text-sm">
                    <datalist id="generic-names">@foreach ($genericNames as $genericName)<option value="{{ $genericName }}">@endforeach</datalist>
                    <x-input-error id="ingredient-error" :messages="$errors->get('ingredient')" class="mt-2" />
                </div>
                <button type="submit" class="seller-primary-button">Add</button>
                <p class="w-full text-sm text-muted">Spell the generic name the way your products do. For words that belong to one product, like symptoms, use <strong>Search keywords</strong> on that product instead.</p>
            </form>

            <section class="sc-card" aria-labelledby="aliases-heading">
                <div class="sc-card-head"><h2 id="aliases-heading" class="sc-card-title">Names in use</h2><span class="sc-meta">{{ $aliasesByIngredient->flatten()->count() }} {{ Str::plural('name', $aliasesByIngredient->flatten()->count()) }}</span></div>
                @if ($aliasesByIngredient->isEmpty())
                    <div class="sc-empty"><h3>No other names yet</h3><p>Add one above, like “acetaminophen” for Paracetamol.</p></div>
                @else
                    <ul class="sc-list">
                        @foreach ($aliasesByIngredient as $ingredient => $aliases)
                            <li class="sc-list-item">
                                <p class="text-sm font-semibold text-ink">{{ $ingredient }}</p>
                                <ul class="mt-2 flex flex-wrap gap-2" aria-label="Other names for {{ $ingredient }}">
                                    @foreach ($aliases as $alias)
                                        <li class="inline-flex min-h-9 items-center gap-1 rounded-md border border-line bg-canvas ps-3 text-sm text-ink">
                                            {{ $alias->alias }}
                                            @if ($alias->isBuiltIn())
                                                <span class="pe-3 text-xs text-muted">Built in</span>
                                            @else
                                            <form method="POST" action="{{ route('seller.ingredient-aliases.destroy', $alias) }}" data-confirm="Stop matching “{{ $alias->alias }}” to {{ $ingredient }}?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="grid size-9 place-items-center rounded-md text-muted transition hover:bg-paper hover:text-danger focus-visible:ring-2 focus-visible:ring-brand" aria-label="Remove {{ $alias->alias }}">
                                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>
                                                </button>
                                            </form>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
