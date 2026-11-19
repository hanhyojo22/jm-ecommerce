@props(['active' => 'details', 'branch' => null])

<nav class="sc-settings-nav" aria-label="Store settings">
    <a href="{{ route('seller.store-settings.edit', ['branch' => $branch]) }}" @if ($active === 'details') aria-current="page" @endif>Store details</a>
    <a href="{{ route('seller.opening-hours.edit', ['branch' => $branch]) }}" @if ($active === 'hours') aria-current="page" @endif>Opening hours</a>
    <a href="{{ route('seller.staff.index') }}">Team &amp; permissions</a>
    <a href="{{ route('profile.edit') }}">Your account</a>
</nav>
