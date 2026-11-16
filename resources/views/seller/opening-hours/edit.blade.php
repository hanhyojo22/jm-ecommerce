<x-app-layout :error-summary="false">
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <h1 class="sc-title">Opening hours · {{ $branch->name }}</h1>
                <p class="sc-subtitle">Same hours every day, Philippine time. While the branch is closed, the app takes pickup orders only.</p>
            </div>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container seller-workspace-page sc-stack">
            @if (session('status'))<p class="sc-notice" data-tone="success" role="status">{{ session('status') }}</p>@endif
            @if ($errors->any())<p class="sc-notice" data-tone="danger" role="alert">{{ $errors->first() }}</p>@endif

            @if ($branches)
                <form method="GET" action="{{ route('seller.opening-hours.edit') }}">
                    <label for="branch" class="block text-sm font-semibold text-ink">Branch</label>
                    <select id="branch" name="branch" class="field-control mt-2 text-base lg:text-sm" onchange="this.form.submit()">
                        @foreach ($branches as $option)
                            <option value="{{ $option->id }}" @selected($option->id === $branch->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            <form method="POST" action="{{ route('seller.opening-hours.update', ['branch' => $branch->id]) }}" class="seller-form-card min-w-0 p-5 sm:p-6"
                x-data="{ allDay: @js((bool) old('open_24_hours', $branch->opening_time === null)) }">
                @csrf
                @method('PATCH')
                <input type="hidden" name="open_24_hours" value="0">
                <label class="flex min-h-11 items-center gap-3 text-sm font-semibold text-ink">
                    <input type="checkbox" name="open_24_hours" value="1" x-model="allDay" class="h-5 w-5 rounded border-line text-brand">
                    Open 24 hours
                </label>
                <p class="mt-1 text-sm text-muted">Otherwise set when the branch opens and closes. A closing time before the opening time runs past midnight.</p>
                <div class="mt-5 grid min-w-0 max-w-2xl gap-4 sm:grid-cols-2">
                    <div class="min-w-0">
                        <label for="opening_time" class="block text-sm font-semibold text-ink">Opens at</label>
                        <input id="opening_time" name="opening_time" type="time" :disabled="allDay" value="{{ old('opening_time', $branch->opening_time) }}" @error('opening_time') aria-invalid="true" @enderror class="field-control mt-2 text-base disabled:opacity-50 lg:text-sm @error('opening_time') border-rose-500 @enderror">
                        <x-input-error :messages="$errors->get('opening_time')" class="mt-2" />
                    </div>
                    <div class="min-w-0">
                        <label for="closing_time" class="block text-sm font-semibold text-ink">Closes at</label>
                        <input id="closing_time" name="closing_time" type="time" :disabled="allDay" value="{{ old('closing_time', $branch->closing_time) }}" @error('closing_time') aria-invalid="true" @enderror class="field-control mt-2 text-base disabled:opacity-50 lg:text-sm @error('closing_time') border-rose-500 @enderror">
                        <x-input-error :messages="$errors->get('closing_time')" class="mt-2" />
                    </div>
                </div>
                <div class="mt-5 flex justify-end">
                    <button type="submit" class="seller-primary-button min-h-11">Save hours</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
