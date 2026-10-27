<x-app-layout>
    <div class="mx-auto max-w-3xl px-[18px] py-10 text-ink sm:px-8 sm:py-14">
        <p class="text-sm font-semibold uppercase tracking-wider text-brand">JMMart · Marketplace</p>
        <h1 class="marketplace-page-title mt-2">Terms of Use</h1>
        <p class="mt-4 leading-7 text-muted">Last updated October 27, 2026. These terms describe how buyers and sellers use JMMart.</p>
        <div class="mt-9 space-y-8 leading-7">
            <section><h2 class="text-xl font-semibold">Accounts</h2><p class="mt-2">Provide accurate account and delivery details, keep your sign-in secure, and use the marketplace lawfully. Sellers are responsible for their shop information and listings. JMMart may moderate listings and accounts to keep the marketplace safe.</p></section>
            <section><h2 class="text-xl font-semibold">Orders and payment</h2><p class="mt-2">Product prices and availability appear on each listing. At checkout, we show the order total and delivery details. You can pay cash on delivery, or pay a store directly by GCash, Maya, or bank transfer where the store offers it (see <a href="#wallet-payments" class="font-semibold text-brand underline">Paying a store directly</a>). Each seller handles their part of a multi-store order. We save the item price shown when you place an order.</p></section>
            <section id="wallet-payments" class="scroll-mt-24">
                <h2 class="text-xl font-semibold">Paying a store directly: GCash, Maya, or bank transfer</h2>
                <p class="mt-2">When you choose GCash, Maya, or bank transfer, you send your payment <strong>directly to the store's own e-wallet or bank account</strong>. JMMart does not receive, hold, or process that money, and is not a bank, e-money issuer, or payment provider. Please read what this means before you pay.</p>

                <h3 class="mt-5 font-semibold">What JMMart does to protect you</h3>
                <ul class="mt-2 list-disc space-y-1 pl-6">
                    <li>We review each seller's identity and business details before their store can sell.</li>
                    <li>We show you only the e-wallet or bank account the store registered with us, and we keep a record whenever a store changes it.</li>
                    <li>A store cannot confirm or ship your order until it records that your payment arrived, and we keep your reference number and the store's confirmation as a record.</li>
                    <li>If you do not send a reference number within {{ config('marketplace.wallet.payment_hours.gcash') }} hours ({{ config('marketplace.wallet.payment_hours.bank') }} hours for bank transfer, since PESONet can take until the next banking day), the order is cancelled so you are not left waiting.</li>
                    <li>A store that cancels after you paid must refund you and give us the refund reference number, which you can see on your order.</li>
                    <li>You can request order help, cancellation, or a return on any order. We review it, and we can warn, restrict, or suspend a store that does not deliver or refund.</li>
                    <li>If you report fraud, we give you and the authorities the records we hold about the order and the store, as the law allows.</li>
                </ul>

                <h3 class="mt-5 font-semibold">What JMMart cannot do</h3>
                <ul class="mt-2 list-disc space-y-1 pl-6">
                    <li>We cannot see e-wallet or bank accounts, so we cannot verify a transfer ourselves. We rely on the store's confirmation and your reference number.</li>
                    <li>Because we never hold your payment, we cannot reverse a transfer or return money from it. Refunds come from the store, or through your bank's or e-wallet's own dispute process.</li>
                    <li>We cannot help with money sent to an account other than the one shown on your JMMart order page.</li>
                </ul>
                <p class="mt-3">If any of this is a concern, choose <strong>cash on delivery</strong>: you pay only when the parcel is in your hands.</p>

                <h3 class="mt-5 font-semibold">Pay safely</h3>
                <ul class="mt-2 list-disc space-y-1 pl-6">
                    <li>Pay only the QR code or number shown on your JMMart order page. Never pay a number sent by chat, text, or social media, even if it claims to be the store or JMMart.</li>
                    <li>Send the exact amount shown for each store, and keep a screenshot of your receipt.</li>
                    <li>Never share your MPIN or one-time PIN (OTP) with anyone, including the store or JMMart.</li>
                    <li>If a store has not recorded your payment within 2 days, request order help on your order.</li>
                </ul>

                <h3 class="mt-5 font-semibold">If something goes wrong</h3>
                <ol class="mt-2 list-decimal space-y-1 pl-6">
                    <li><strong>JMMart:</strong> request help on your order page, or email us. Include your reference number and receipt.</li>
                    <li><strong>Your bank, GCash, or Maya:</strong> report the transaction to the bank or e-wallet you paid from right away. Under the Anti-Financial Account Scamming Act (Republic Act No. 12010), banks and e-wallets can temporarily hold funds involved in a reported scam, so speed matters.</li>
                    <li><strong>Bangko Sentral ng Pilipinas:</strong> if your bank or e-wallet does not resolve your complaint, you can escalate it through the BSP's consumer assistance channels under the Financial Products and Services Consumer Protection Act (Republic Act No. 11765).</li>
                    <li><strong>DTI:</strong> for a seller that will not deliver or refund, you can file a consumer complaint with the Department of Trade and Industry under the Consumer Act (Republic Act No. 7394) and the Internet Transactions Act of 2023 (Republic Act No. 11967).</li>
                    <li><strong>Fraud:</strong> report scams to the Cybercrime Investigation and Coordinating Center (hotline 1326), the PNP Anti-Cybercrime Group, or the NBI Cybercrime Division.</li>
                </ol>

                <h3 class="mt-5 font-semibold">Your rights and your data</h3>
                <p class="mt-2">Nothing in these terms limits or waives the rights you have under Philippine law, including the laws named above. To let a store verify your payment and send any refund, we share your name, mobile number, order amount, and reference number with that store, as described in our <a href="{{ route('privacy-policy') }}" class="font-semibold text-brand underline">Privacy Policy</a> and the Data Privacy Act of 2012 (Republic Act No. 10173).</p>
            </section>
            <section><h2 class="text-xl font-semibold">Delivery</h2><p class="mt-2">Sellers arrange delivery for their items. Delivery timing and availability depend on the destination and seller confirmation; no fixed delivery date is guaranteed at checkout. Use the order page or contact support if you need an update.</p></section>
            <section><h2 class="text-xl font-semibold">Cancellations and returns</h2><p class="mt-2">Buyers may request cancellation before a store starts preparing its part of an order. For delivered items, buyers can request return or refund review. See our <a href="{{ route('returns') }}" class="font-semibold text-brand underline">Returns and Refunds</a> page for the process. A request is reviewed before any change or refund is approved.</p></section>
            <section><h2 class="text-xl font-semibold">Contact</h2><p class="mt-2">For order or account help, email <a href="mailto:itservices@jmsolutionitservices.com" class="font-semibold text-brand underline">itservices@jmsolutionitservices.com</a>. See our <a href="{{ route('privacy-policy') }}" class="font-semibold text-brand underline">Privacy Policy</a> for how we handle personal information.</p></section>
        </div>
    </div>
</x-app-layout>
