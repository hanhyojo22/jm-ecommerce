<x-app-layout>
    <div class="mx-auto max-w-3xl px-[18px] py-10 text-ink sm:px-8 sm:py-14">
        <p class="text-sm font-semibold uppercase tracking-wider text-brand">JMMart · Marketplace</p>
        <h1 class="marketplace-page-title mt-2">Terms of Use</h1>
        <p class="mt-4 leading-7 text-muted">Last updated October 27, 2026. These terms describe how buyers and sellers use JMMart.</p>
        <div class="mt-9 space-y-8 leading-7">
            <section><h2 class="text-xl font-semibold">Accounts</h2><p class="mt-2">Provide accurate account and delivery details, keep your sign-in secure, and use the marketplace lawfully. Sellers are responsible for their shop information and listings. JMMart may moderate listings and accounts to keep the marketplace safe.</p></section>
            <section><h2 class="text-xl font-semibold">Orders and payment</h2><p class="mt-2">Product prices and availability appear on each listing. At checkout, we show the order total and delivery details. You can pay cash on delivery, or pay online by GCash, Maya, or card where every store in your order offers it (see <a href="#online-payments" class="font-semibold text-brand underline">Paying online</a>). Each seller handles their part of a multi-store order. We save the item price shown when you place an order.</p></section>
            <section id="online-payments" class="scroll-mt-24">
                <h2 class="text-xl font-semibold">Paying online: GCash, Maya, or card</h2>
                <p class="mt-2">Online payments are processed by <strong>PayMongo</strong>, a payment provider. You pay once, on PayMongo’s secure page, for every store in your order. PayMongo then splits the payment: each store receives its share in its own PayMongo account, and JMMart receives its platform fee. JMMart never sees your card, e-wallet login, MPIN, or OTP.</p>

                <h3 class="mt-5 font-semibold">Fees</h3>
                <p class="mt-2">PayMongo charges a processing fee, shown as a separate <strong>payment fee</strong> at checkout before you place your order. It is added to what you pay and is not refunded.</p>

                <h3 class="mt-5 font-semibold">Unpaid orders</h3>
                <p class="mt-2">Your items are held for {{ (int) config('marketplace.online_payment.payment_hours') }} hours after you place the order. If you have not paid by then, the order is cancelled and the items go back on sale. You can return to PayMongo’s page from your order until then.</p>

                <h3 class="mt-5 font-semibold">Cancellations and refunds</h3>
                <ul class="mt-2 list-disc space-y-1 pl-6">
                    <li>If a store cancels its part of an order you already paid, that part is refunded to the account you paid with, through PayMongo.</li>
                    <li>An approved return with a refund is paid back the same way.</li>
                    <li>Refunds can take a few business days to appear, depending on your e-wallet or bank. If a refund does not arrive, request help from your order page.</li>
                </ul>
                <p class="mt-3">If you prefer, choose <strong>cash on delivery</strong>: you pay only when the parcel is in your hands.</p>
            </section>
            <section><h2 class="text-xl font-semibold">Delivery</h2><p class="mt-2">Sellers arrange delivery for their items. Delivery timing and availability depend on the destination and seller confirmation; no fixed delivery date is guaranteed at checkout. Use the order page or contact support if you need an update.</p></section>
            <section><h2 class="text-xl font-semibold">Cancellations and returns</h2><p class="mt-2">Buyers may request cancellation before a store starts preparing its part of an order. For delivered items, buyers can request return or refund review. See our <a href="{{ route('returns') }}" class="font-semibold text-brand underline">Returns and Refunds</a> page for the process. A request is reviewed before any change or refund is approved.</p></section>
            <section><h2 class="text-xl font-semibold">Contact</h2><p class="mt-2">For order or account help, email <a href="mailto:itservices@jmsolutionitservices.com" class="font-semibold text-brand underline">itservices@jmsolutionitservices.com</a>. See our <a href="{{ route('privacy-policy') }}" class="font-semibold text-brand underline">Privacy Policy</a> for how we handle personal information.</p></section>
        </div>
    </div>
</x-app-layout>
