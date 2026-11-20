@php($cancelUrl = $cancelUrl ?? null)
<form method="POST" action="{{ $action }}" class="grid gap-x-4 gap-y-5 sm:grid-cols-2">
    @csrf
    @if ($method !== 'POST')@method($method)@endif
    <div>
        <label for="recipient_name" class="block text-meta font-semibold text-ink">Recipient name</label>
        <input id="recipient_name" name="recipient_name" value="{{ old('recipient_name', $address?->recipient_name) }}" required maxlength="150" autocomplete="name" class="field-control mt-1.5" @error('recipient_name') aria-invalid="true" aria-describedby="recipient_name-error" @enderror>
        @error('recipient_name')<p id="recipient_name-error" class="mt-1.5 text-xs text-danger" role="alert">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="phone" class="block text-meta font-semibold text-ink">Mobile number</label>
        <input id="phone" name="phone" value="{{ old('phone', $address?->phone) }}" required maxlength="30" type="tel" autocomplete="tel" placeholder="09171234567" class="field-control mt-1.5 tabular-nums" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
        @error('phone')<p id="phone-error" class="mt-1.5 text-xs text-danger" role="alert">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <label for="address_line" class="block text-meta font-semibold text-ink">Unit / house no. and street</label>
        <input id="address_line" name="address_line" value="{{ old('address_line', $address?->address_line) }}" required maxlength="255" autocomplete="address-line1" placeholder="Lot 5 Blk 2, Rizal Street" class="field-control mt-1.5" @error('address_line') aria-invalid="true" aria-describedby="address_line-error" @enderror>
        @error('address_line')<p id="address_line-error" class="mt-1.5 text-xs text-danger" role="alert">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="subdivision" class="block text-meta font-semibold text-ink">Subdivision / village / building (optional)</label>
        <input id="subdivision" name="subdivision" value="{{ old('subdivision', $address?->subdivision) }}" maxlength="150" autocomplete="address-line2" class="field-control mt-1.5" @error('subdivision') aria-invalid="true" aria-describedby="subdivision-error" @enderror>
        @error('subdivision')<p id="subdivision-error" class="mt-1.5 text-xs text-danger" role="alert">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="barangay" class="block text-meta font-semibold text-ink">Barangay</label>
        <input id="barangay" name="barangay" value="{{ old('barangay', $address?->barangay) }}" required maxlength="100" autocomplete="address-level3" class="field-control mt-1.5" @error('barangay') aria-invalid="true" aria-describedby="barangay-error" @enderror>
        @error('barangay')<p id="barangay-error" class="mt-1.5 text-xs text-danger" role="alert">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="city" class="block text-meta font-semibold text-ink">City / municipality</label>
        <input id="city" name="city" value="{{ old('city', $address?->city) }}" required maxlength="100" autocomplete="address-level2" class="field-control mt-1.5" @error('city') aria-invalid="true" aria-describedby="city-error" @enderror>
        @error('city')<p id="city-error" class="mt-1.5 text-xs text-danger" role="alert">{{ $message }}</p>@enderror
    </div>
    <div class="grid grid-cols-[minmax(0,1fr)_7rem] gap-4">
        <div>
            <label for="province" class="block text-meta font-semibold text-ink">Province</label>
            <input id="province" name="province" value="{{ old('province', $address?->province) }}" required maxlength="100" autocomplete="address-level1" placeholder="Metro Manila if in NCR" class="field-control mt-1.5" @error('province') aria-invalid="true" aria-describedby="province-error" @enderror>
            @error('province')<p id="province-error" class="mt-1.5 text-xs text-danger" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="postal_code" class="block text-meta font-semibold text-ink">Postal code</label>
            <input id="postal_code" name="postal_code" value="{{ old('postal_code', $address?->postal_code) }}" required inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="postal-code" class="field-control mt-1.5 tabular-nums" @error('postal_code') aria-invalid="true" aria-describedby="postal_code-error" @enderror>
            @error('postal_code')<p id="postal_code-error" class="mt-1.5 text-xs text-danger" role="alert">{{ $message }}</p>@enderror
        </div>
    </div>
    <div class="flex flex-col-reverse gap-2 border-t border-line-soft pt-5 sm:col-span-2 sm:flex-row sm:justify-end">
        @if ($cancelUrl)
            <a href="{{ $cancelUrl }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-line bg-surface px-4 text-meta font-semibold text-ink transition-colors hover:border-brand hover:text-brand-hover lg:min-h-10">Cancel</a>
        @endif
        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-5 text-meta font-semibold text-white transition-colors hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand lg:min-h-10">{{ $submitLabel }}</button>
    </div>
</form>
