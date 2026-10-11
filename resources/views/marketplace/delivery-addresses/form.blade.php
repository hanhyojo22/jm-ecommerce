<form method="POST" action="{{ $action }}" class="grid gap-4 sm:grid-cols-2">
    @csrf
    @if ($method !== 'POST')@method($method)@endif
    <div class="sm:col-span-2">
        <label for="recipient_name" class="block text-sm font-semibold text-ink">Recipient name</label>
        <input id="recipient_name" name="recipient_name" value="{{ old('recipient_name', $address?->recipient_name) }}" required maxlength="150" autocomplete="name" class="mt-2 min-h-11 w-full rounded-[10px] border-line text-sm focus:border-brand focus:ring-brand">
        @error('recipient_name')<p class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="phone" class="block text-sm font-semibold text-ink">Mobile number</label>
        <input id="phone" name="phone" value="{{ old('phone', $address?->phone) }}" required maxlength="30" type="tel" autocomplete="tel" class="mt-2 min-h-11 w-full rounded-[10px] border-line text-sm focus:border-brand focus:ring-brand">
        @error('phone')<p class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="city" class="block text-sm font-semibold text-ink">City</label>
        <input id="city" name="city" value="{{ old('city', $address?->city) }}" required maxlength="100" autocomplete="address-level2" class="mt-2 min-h-11 w-full rounded-[10px] border-line text-sm focus:border-brand focus:ring-brand">
        @error('city')<p class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <label for="address_line" class="block text-sm font-semibold text-ink">Street address</label>
        <input id="address_line" name="address_line" value="{{ old('address_line', $address?->address_line) }}" required maxlength="255" autocomplete="street-address" class="mt-2 min-h-11 w-full rounded-[10px] border-line text-sm focus:border-brand focus:ring-brand">
        @error('address_line')<p class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
    </div>
    <button type="submit" class="min-h-11 rounded-lg bg-brand px-5 text-sm font-semibold text-white hover:bg-brand-hover focus-visible:ring-2 focus-visible:ring-brand">{{ $submitLabel }}</button>
</form>
