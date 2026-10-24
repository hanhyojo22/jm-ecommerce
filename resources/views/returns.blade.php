<x-app-layout>
    <div class="page-shell">
        <div class="page-container returns-policy-shell">
            <section class="returns-policy-hero">
                <div class="returns-policy-hero-content grid gap-10 px-6 py-11 sm:px-10 sm:py-14 lg:grid-cols-[minmax(0,1.35fr)_minmax(280px,0.65fr)] lg:items-end">
                    <div>
                        <p class="returns-eyebrow flex items-center gap-2">
                            <x-returns.icon name="shield" class="size-4" />
                            JMMart · Buyer protection
                        </p>
                        <h1 class="returns-policy-title mt-4">Returns and Refunds</h1>
                        <p class="mt-5 max-w-2xl text-[15px] leading-7 text-muted sm:text-base">
                            How to ask for help with an order, what the seller owes you, and how JMMart keeps the record straight.
                            <span class="mt-1 block text-xs text-slate-500">Last updated October 19, 2026.</span>
                        </p>
                        <div class="mt-7 flex flex-wrap gap-3">
                            <a href="{{ route('orders.index') }}" class="button-primary">
                                Go to my orders
                                <x-returns.icon name="chevron-right" class="size-4" />
                            </a>
                            <a href="mailto:itservices@jmsolutionitservices.com" class="button-secondary">
                                <x-returns.icon name="mail" class="size-4" />
                                Email support
                            </a>
                        </div>
                    </div>

                    <div class="returns-policy-summary">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-200">One record, start to finish</p>
                        <h2 class="mt-2">The short version</h2>
                        <ul class="mt-5">
                            <li>
                                <span class="returns-policy-summary-mark" aria-hidden="true"><x-returns.icon name="check-plain" class="size-2.5" /></span>
                                Every decision is written to the case history.
                            </li>
                            <li>
                                <span class="returns-policy-summary-mark" aria-hidden="true"><x-returns.icon name="check-plain" class="size-2.5" /></span>
                                Your next step and its deadline sit on the case page.
                            </li>
                            <li>
                                <span class="returns-policy-summary-mark" aria-hidden="true"><x-returns.icon name="check-plain" class="size-2.5" /></span>
                                JMMart support can step in when a deadline passes.
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <div class="returns-facts mt-5" aria-label="Return policy at a glance">
                <div class="returns-fact">
                    <span class="returns-fact-icon"><x-returns.icon name="calendar" class="size-[18px]" /></span>
                    <div>
                        <p class="returns-fact-value">7 days</p>
                        <p class="mt-1 text-xs leading-5 text-muted">To open an online request after delivery.</p>
                    </div>
                </div>
                <div class="returns-fact">
                    <span class="returns-fact-icon"><x-returns.icon name="chat" class="size-[18px]" /></span>
                    <div>
                        <p class="returns-fact-value">48 hours</p>
                        <p class="mt-1 text-xs leading-5 text-muted">The seller's target for responding to a request.</p>
                    </div>
                </div>
                <div class="returns-fact">
                    <span class="returns-fact-icon"><x-returns.icon name="refund" class="size-[18px]" /></span>
                    <div>
                        <p class="returns-fact-value">5 business days</p>
                        <p class="mt-1 text-xs leading-5 text-muted">To complete a refund or ship a replacement.</p>
                    </div>
                </div>
                <div class="returns-fact">
                    <span class="returns-fact-icon"><x-returns.icon name="truck" class="size-[18px]" /></span>
                    <div>
                        <p class="returns-fact-value">No-cost return</p>
                        <p class="mt-1 text-xs leading-5 text-muted">When the seller asks for the goods back.</p>
                    </div>
                </div>
            </div>

            <div class="mt-10 grid items-start gap-8 lg:grid-cols-[220px_minmax(0,1fr)]">
                <aside class="returns-policy-toc" aria-label="On this page">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-[0.16em] text-muted">On this page</p>
                    <nav class="grid gap-0.5">
                        <a href="#cancel" class="returns-policy-link"><span>01</span> Cancel before preparation</a>
                        <a href="#after-delivery" class="returns-policy-link"><span>02</span> Problems after delivery</a>
                        <a href="#seller-response" class="returns-policy-link"><span>03</span> Seller response and appeal</a>
                        <a href="#return-completion" class="returns-policy-link"><span>04</span> Return and completion</a>
                        <a href="#change-of-mind" class="returns-policy-link"><span>05</span> Change of mind</a>
                        <a href="#cod-refunds" class="returns-policy-link"><span>06</span> Cash on delivery refunds</a>
                        <a href="#need-help" class="returns-policy-link"><span>07</span> Need help?</a>
                    </nav>
                </aside>

                <article class="returns-policy-article">
                    <section id="cancel" class="returns-policy-section" aria-labelledby="cancel-heading">
                        <div class="returns-policy-heading">
                            <span class="returns-policy-number" aria-hidden="true">01</span>
                            <div>
                                <p class="returns-policy-kicker">Before the parcel moves</p>
                                <h2 id="cancel-heading">Cancel before preparation</h2>
                            </div>
                        </div>
                        <div class="returns-policy-body">
                            <p>You can request cancellation while a store order is pending or confirmed. Open your order page, choose the store order, and send a cancellation request. JMMart reviews the request; the order is cancelled only when an admin approves it. Once preparation or delivery begins, use the order help option instead.</p>
                        </div>
                    </section>

                    <section id="after-delivery" class="returns-policy-section" aria-labelledby="after-delivery-heading">
                        <div class="returns-policy-heading">
                            <span class="returns-policy-number" aria-hidden="true">02</span>
                            <div>
                                <p class="returns-policy-kicker">When something is wrong</p>
                                <h2 id="after-delivery-heading">Problems after delivery</h2>
                            </div>
                        </div>
                        <div class="returns-policy-body">
                            <p>For damaged, defective, wrong, or materially misdescribed goods, open your order page within seven days after that store marks delivery. Select the affected items and quantities, explain the problem, choose a preferred refund or replacement, and optionally add photos. Keep the item and packaging while the case is reviewed.</p>
                            <p>If the seven-day online window has passed or the delivery date is uncertain, contact support with your order number. This interface window does not limit later warranty or statutory rights.</p>
                        </div>
                        <div class="returns-policy-callout">
                            <x-returns.icon name="camera" class="mt-0.5 size-4 shrink-0 text-brand" />
                            <p>Photos are optional, but clear pictures of the item, packaging, and any damage help the seller decide faster.</p>
                        </div>
                    </section>

                    <section id="seller-response" class="returns-policy-section" aria-labelledby="seller-response-heading">
                        <div class="returns-policy-heading">
                            <span class="returns-policy-number" aria-hidden="true">03</span>
                            <div>
                                <p class="returns-policy-kicker">The first decision</p>
                                <h2 id="seller-response-heading">Seller response and appeal</h2>
                            </div>
                        </div>
                        <div class="returns-policy-body">
                            <p>The seller aims to respond within 48 hours with reasons for any rejection or with a refund or replacement offer. You may accept an offer or appeal an unsuitable offer or rejection to JMMart within seven days. Support decides appeals and can take over cases that pass a deadline. If you neither accept nor appeal within seven days, JMMart can close the case without a remedy. Your case page shows its history, the next action, and the date it is due.</p>
                        </div>
                    </section>

                    <section id="return-completion" class="returns-policy-section" aria-labelledby="return-completion-heading">
                        <div class="returns-policy-heading">
                            <span class="returns-policy-number" aria-hidden="true">04</span>
                            <div>
                                <p class="returns-policy-kicker">Getting to done</p>
                                <h2 id="return-completion-heading">Return and completion</h2>
                            </div>
                        </div>
                        <div class="returns-policy-body">
                            <p>The seller may waive return of the original goods. Otherwise, for a qualifying problem the seller must give instructions and a no-cost return method. Send the goods back and upload handoff proof in your case within seven days of accepting, or JMMart can close the case without a remedy. The seller then records receipt.</p>
                            <p>The seller aims to complete an approved refund or ship a replacement within five business days after receiving the return, or after approval when return is waived. The seller records refund payment date and proof or replacement shipment details. Confirm receipt in the case, or dispute an unsatisfactory outcome for support review. Support can set aside a recorded outcome it finds unsupported and put the seller back on the hook. An unconfirmed outcome does not close automatically.</p>
                        </div>
                    </section>

                    <section id="change-of-mind" class="returns-policy-section" aria-labelledby="change-of-mind-heading">
                        <div class="returns-policy-heading">
                            <span class="returns-policy-number" aria-hidden="true">05</span>
                            <div>
                                <p class="returns-policy-kicker">Outside a fault</p>
                                <h2 id="change-of-mind-heading">Change of mind</h2>
                            </div>
                        </div>
                        <div class="returns-policy-body">
                            <p>A change of mind alone does not automatically qualify for a refund or exchange. You can still contact support to ask whether the seller can accommodate your request. This does not limit remedies available for defective goods.</p>
                        </div>
                    </section>

                    <section id="cod-refunds" class="returns-policy-section" aria-labelledby="cod-refunds-heading">
                        <div class="returns-policy-heading">
                            <span class="returns-policy-number" aria-hidden="true">06</span>
                            <div>
                                <p class="returns-policy-kicker">Paying and refunding</p>
                                <h2 id="cod-refunds-heading">Cash on delivery refunds</h2>
                            </div>
                        </div>
                        <div class="returns-policy-body">
                            <p>Each seller collects cash for its own portion of a multi-store order and pays approved refunds directly. Support coordinates a suitable refund destination privately, outside the app. The seller records payment date and proof in the case; the buyer confirms receipt. Do not send bank or refund account details in chat, reviews, or case notes.</p>
                            <p>A seller can only record a refund for cash that was actually collected for that store order. If the seller does not pay, support reviews the case; a recorded offer alone is not a completed refund.</p>
                        </div>
                        <div class="returns-policy-callout">
                            <x-returns.icon name="lock" class="mt-0.5 size-4 shrink-0 text-brand" />
                            <p>Keep bank and refund account details out of chat, reviews, and case notes. Support will coordinate a safe destination privately.</p>
                        </div>
                    </section>

                    <section id="need-help" class="returns-policy-section" aria-labelledby="need-help-heading">
                        <div class="returns-policy-heading">
                            <span class="returns-policy-number" aria-hidden="true">07</span>
                            <div>
                                <p class="returns-policy-kicker">Still stuck?</p>
                                <h2 id="need-help-heading">Need help?</h2>
                            </div>
                        </div>
                        <div class="returns-contact-card mt-6">
                            <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-blue-200">
                                <x-returns.icon name="chat" class="size-4" />
                                JMMart support
                            </p>
                            <h3 class="mt-2">A real person can review the case.</h3>
                            <p class="mt-2 max-w-2xl text-sm leading-6">Use the request form on your order page or email itservices@jmsolutionitservices.com with your order number.</p>
                            <div class="mt-5 flex flex-wrap gap-3">
                                <a href="{{ route('orders.index') }}" class="button-primary">
                                    Open my orders
                                    <x-returns.icon name="chevron-right" class="size-4" />
                                </a>
                                <a href="mailto:itservices@jmsolutionitservices.com" class="button-secondary">
                                    <x-returns.icon name="mail" class="size-4" />
                                    itservices@jmsolutionitservices.com
                                </a>
                            </div>
                        </div>
                    </section>
                </article>
            </div>
        </div>
    </div>
</x-app-layout>
