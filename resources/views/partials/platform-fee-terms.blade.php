{{-- The fee terms a store agrees to, shown at onboarding and to stores approved before they existed. --}}
@php($ratePercent = rtrim(rtrim(bcmul((string) config('marketplace.platform_fee.rate'), '100', 2), '0'), '.'))
<ul class="{{ $class ?? '' }} list-disc space-y-1 rounded-lg bg-canvas py-3 pl-8 pr-4 text-xs leading-5 text-muted">
    <li>JMMart charges <strong class="text-ink">{{ $ratePercent }}% of each delivered order</strong>. Orders that are cancelled are never charged.</li>
    <li>When you refund a buyer through a return case, {{ $ratePercent }}% of the refund is credited back to you.</li>
    <li>You collect the cash from buyers, so JMMart sends you one statement a month. It is due {{ config('marketplace.platform_fee.due_days') }} days after the month ends.</li>
    <li>Pay by bank or GCash transfer, then upload the reference number and receipt on your Platform fees page.</li>
</ul>
