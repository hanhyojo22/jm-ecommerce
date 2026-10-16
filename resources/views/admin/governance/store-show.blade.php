<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('admin.stores.index') }}" class="text-sm font-semibold text-brand underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-brand">Seller Stores</a>
                <h1 class="mt-2 text-[28px] font-bold tracking-[-0.02em] text-ink leading-[1.55]">{{ $store->name }}</h1>
                <p class="mt-2 text-sm text-muted">Store profile, verification record, listings, orders, and moderation history.</p>
            </div>
            <span class="inline-flex h-fit rounded-full px-3 py-1.5 text-sm font-semibold ring-1 ring-inset {{ ['active' => 'bg-success/10 text-success ring-success/20', 'inactive' => 'bg-canvas text-muted ring-line', 'suspended' => 'bg-danger/10 text-danger ring-danger/20'][$store->status] }}">{{ ucfirst($store->status) }} store</span>
        </div>
    </x-slot>
    @php($orderStyles = ['pending' => 'bg-warning/10 text-warning-text ring-warning/20', 'confirmed' => 'bg-brand/10 text-brand ring-brand/20', 'processing' => 'bg-brand/10 text-brand ring-brand/20', 'shipped' => 'bg-brand/10 text-brand ring-brand/20', 'delivered' => 'bg-success/10 text-success ring-success/20', 'cancelled' => 'bg-danger/10 text-danger ring-danger/20'])
    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-[1320px] px-[18px] sm:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-[14px] border border-success/20 bg-success/10 px-4 py-3 text-sm font-medium text-success" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-6 rounded-[14px] border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">We could not complete that moderation action. {{ $errors->first() }}</div>
            @endif

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="space-y-6">
                    <section class="rounded-[14px] border border-line bg-surface p-5 sm:p-6">
                        <div class="flex flex-wrap items-start gap-4">
                            @if ($store->logo_path)
                                <img src="{{ Storage::disk('public')->url($store->logo_path) }}" alt="{{ $store->name }} logo" class="h-16 w-16 rounded-[14px] object-cover">
                            @else
                                <div class="flex h-16 w-16 items-center justify-center rounded-[14px] bg-brand/10 text-xl font-bold text-brand" aria-hidden="true">{{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}</div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <h2 class="text-xl font-semibold text-ink">Store profile</h2>
                                <p class="mt-1 text-sm text-muted">{{ $store->city }} · Joined {{ $store->created_at->format('M j, Y') }} · Last updated {{ $store->updated_at->format('M j, Y · g:i A') }}</p>
                            </div>
                        </div>
                        <div class="mt-5 border-t border-line pt-5">
                            <h3 class="text-sm font-semibold text-ink">Description</h3>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-ink">{{ $store->description ?: 'No store description provided.' }}</p>
                        </div>
                        <dl class="mt-5 grid gap-5 border-t border-line pt-5 sm:grid-cols-3">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Products</dt>
                                <dd class="mt-1 text-lg font-semibold tabular-nums text-ink">{{ number_format($store->products_count) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Seller orders</dt>
                                <dd class="mt-1 text-lg font-semibold tabular-nums text-ink">{{ number_format($store->seller_orders_count) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Tenant status</dt>
                                <dd class="mt-1 font-medium text-ink">{{ ucfirst($store->tenant->status) }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="overflow-hidden rounded-[14px] border border-line bg-surface ">
                        <div class="border-b border-line px-5 py-4 sm:px-6">
                            <h2 class="font-semibold text-ink">Products</h2>
                            <p class="mt-1 text-sm text-muted">Live listings owned by this store. Prices are current listing prices, not order snapshots.</p>
                        </div>
                        <div class="hidden overflow-x-auto md:block">
                            <table class="min-w-full divide-y divide-line">
                                <thead class="bg-canvas text-left text-xs font-semibold uppercase tracking-[0.1em] text-muted">
                                    <tr><th scope="col" class="px-5 py-3.5">Product</th><th scope="col" class="px-5 py-3.5">Category</th><th scope="col" class="px-5 py-3.5 text-right">Price</th><th scope="col" class="px-5 py-3.5">Status</th><th scope="col" class="px-5 py-3.5"><span class="sr-only">Open</span></th></tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    @forelse ($products as $product)
                                        <tr class="transition hover:bg-canvas/80">
                                            <td class="px-5 py-4"><p class="font-medium text-ink">{{ $product->name }}</p><p class="mt-0.5 text-xs text-muted">SKU {{ $product->sku }} · {{ number_format($product->stock_quantity) }} in stock</p></td>
                                            <td class="px-5 py-4 text-sm text-ink">{{ $product->category?->name ?? 'Uncategorized' }}</td>
                                            <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-semibold tabular-nums text-ink">₱{{ number_format($product->price, 2) }}</td>
                                            <td class="px-5 py-4"><x-marketplace.product-status :status="$product->status" /></td>
                                            <td class="px-5 py-4 text-right"><a href="{{ route('admin.products.show', $product) }}" class="text-sm font-semibold text-brand underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-brand">Open</a></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-muted">This store has no products yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="divide-y divide-line md:hidden">
                            @forelse ($products as $product)
                                <article class="flex items-center justify-between gap-3 p-4">
                                    <div class="min-w-0"><p class="truncate font-medium text-ink">{{ $product->name }}</p><p class="mt-1 text-xs text-muted">₱{{ number_format($product->price, 2) }} · {{ \App\Models\Product::STATUSES[$product->status] }}</p></div>
                                    <a href="{{ route('admin.products.show', $product) }}" class="inline-flex min-h-11 items-center rounded-lg border border-line px-3 text-sm font-semibold text-ink focus:outline-none focus:ring-2 focus:ring-brand">Open</a>
                                </article>
                            @empty
                                <p class="px-6 py-12 text-center text-sm text-muted">This store has no products yet.</p>
                            @endforelse
                        </div>
                        @if ($products->hasPages())
                            <div class="border-t border-line bg-canvas px-4 py-4">{{ $products->links() }}</div>
                        @endif
                    </section>

                    <section class="overflow-hidden rounded-[14px] border border-line bg-surface ">
                        <div class="border-b border-line px-5 py-4 sm:px-6">
                            <h2 class="font-semibold text-ink">Orders</h2>
                            <p class="mt-1 text-sm text-muted">Totals are checkout snapshots; later product edits do not change them.</p>
                        </div>
                        <div class="hidden overflow-x-auto md:block">
                            <table class="min-w-full divide-y divide-line">
                                <thead class="bg-canvas text-left text-xs font-semibold uppercase tracking-[0.1em] text-muted">
                                    <tr><th scope="col" class="px-5 py-3.5">Order</th><th scope="col" class="px-5 py-3.5">Placed</th><th scope="col" class="px-5 py-3.5">Status</th><th scope="col" class="px-5 py-3.5 text-right">Total</th></tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    @forelse ($sellerOrders as $sellerOrder)
                                        <tr>
                                            <td class="px-5 py-4 font-medium text-ink">{{ $sellerOrder->order->number }}</td>
                                            <td class="whitespace-nowrap px-5 py-4 text-sm text-muted">{{ $sellerOrder->created_at->format('M j, Y') }}</td>
                                            <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $orderStyles[$sellerOrder->status] }}">{{ ucfirst($sellerOrder->status) }}</span></td>
                                            <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-semibold tabular-nums text-ink">₱{{ number_format((float) $sellerOrder->total, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-6 py-12 text-center text-sm text-muted">No orders for this store yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="divide-y divide-line md:hidden">
                            @forelse ($sellerOrders as $sellerOrder)
                                <article class="flex items-center justify-between gap-3 p-4">
                                    <div><p class="font-medium text-ink">{{ $sellerOrder->order->number }}</p><p class="mt-1 text-xs text-muted">{{ $sellerOrder->created_at->format('M j, Y') }} · {{ ucfirst($sellerOrder->status) }}</p></div>
                                    <p class="text-sm font-semibold tabular-nums text-ink">₱{{ number_format((float) $sellerOrder->total, 2) }}</p>
                                </article>
                            @empty
                                <p class="px-6 py-12 text-center text-sm text-muted">No orders for this store yet.</p>
                            @endforelse
                        </div>
                        @if ($sellerOrders->hasPages())
                            <div class="border-t border-line bg-canvas px-4 py-4">{{ $sellerOrders->links() }}</div>
                        @endif
                    </section>

                    <section class="rounded-[14px] border border-line bg-surface p-5 sm:p-6">
                        <h2 class="font-semibold text-ink">Activity history</h2>
                        <p class="mt-1 text-sm text-muted">Recorded moderation and onboarding events for this store and its tenant. Reasons are stored in the audit log.</p>
                        <div class="mt-4 space-y-0 divide-y divide-line">
                            @forelse ($auditLogs as $log)
                                <div class="py-3">
                                    <p class="text-sm font-semibold text-ink">{{ Str::headline(str_replace(['.', '_'], ' ', $log->action)) }}</p>
                                    <p class="mt-1 text-xs text-muted">{{ $log->actor?->name ?? $log->actor?->email ?? 'System' }} · {{ $log->created_at->format('M j, Y · g:i A') }}</p>
                                    @if (is_array($log->new_values) && filled($log->new_values['reason'] ?? null))
                                        <p class="mt-2 rounded-lg bg-canvas px-3 py-2 text-sm text-ink">Reason: {{ $log->new_values['reason'] }}</p>
                                    @endif
                                </div>
                            @empty
                                <p class="py-4 text-sm text-muted">No recorded activity for this store yet.</p>
                            @endforelse
                        </div>
                    </section>
                </div>

                <aside class="space-y-6">
                    <section class="rounded-[14px] border border-line bg-surface p-5 ">
                        <h2 class="font-semibold text-ink">Seller contact</h2>
                        @if ($owner)
                            <dl class="mt-4 space-y-4 text-sm">
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Owner</dt><dd class="mt-1 font-medium text-ink">{{ $owner->name }}</dd></div>
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Email</dt><dd class="mt-1 break-all font-medium text-ink"><a href="mailto:{{ $owner->email }}" class="text-brand underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-brand">{{ $owner->email }}</a></dd></div>
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Store phone</dt><dd class="mt-1 font-medium text-ink">{{ $store->phone }}</dd></div>
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Address</dt><dd class="mt-1 font-medium text-ink">{{ $store->address_line }}, {{ $store->city }}</dd></div>
                            </dl>
                        @else
                            <p class="mt-4 text-sm text-muted">No owner membership is on file for this tenant.</p>
                        @endif
                    </section>

                    <section class="rounded-[14px] border border-line bg-surface p-5 ">
                        <h2 class="font-semibold text-ink">Verification record</h2>
                        @if ($application)
                            <dl class="mt-4 space-y-4 text-sm">
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Application status</dt><dd class="mt-1 font-medium text-ink">{{ ucfirst($application->status) }}</dd></div>
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Submitted category</dt><dd class="mt-1 font-medium text-ink">{{ $application->store_category ?: 'Not provided' }}</dd></div>
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Submitted</dt><dd class="mt-1 font-medium text-ink">{{ $application->submitted_at?->format('M j, Y · g:i A') ?? 'Not recorded' }}</dd></div>
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Reviewed</dt><dd class="mt-1 font-medium text-ink">{{ $application->reviewed_at?->format('M j, Y · g:i A') ?? 'Not yet reviewed' }}</dd></div>
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-muted">Review note</dt><dd class="mt-1 whitespace-pre-line font-medium text-ink">{{ $application->review_note ?: 'No review note recorded.' }}</dd></div>
                            </dl>
                        @else
                            <p class="mt-4 text-sm text-muted">No verification submission is linked to this store. Only the data the seller submitted is shown here.</p>
                        @endif
                    </section>

                    <section id="suspension-appeals" class="scroll-mt-24 rounded-[14px] border border-line bg-surface p-5 " aria-label="Store suspension appeals">
                        <h2 class="font-semibold text-ink">Suspension appeals</h2>
                        <p class="mt-1 text-sm text-muted">Appeals are submitted by the store owner and reviewed here.</p>
                        <div class="mt-4 space-y-4">
                            @forelse ($appeals as $appeal)
                                <article id="appeal-{{ $appeal->id }}" class="rounded-[14px] border border-line p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="text-sm font-semibold text-ink">{{ $appeal->seller?->name ?? 'Former seller' }}</p>
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ ['pending' => 'bg-warning/10 text-warning-text', 'approved' => 'bg-success/10 text-success', 'rejected' => 'bg-danger/10 text-danger'][$appeal->status] }}">{{ ucfirst($appeal->status) }}</span>
                                    </div>
                                    <p class="mt-1 text-xs text-muted">Submitted {{ $appeal->created_at->format('M j, Y · g:i A') }}</p>
                                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-ink">{{ $appeal->statement }}</p>
                                    @if ($appeal->review_note)
                                        <p class="mt-3 border-t border-line pt-3 text-sm text-ink"><span class="font-semibold">Review note:</span> {{ $appeal->review_note }}</p>
                                    @endif
                                    @if ($appeal->status === 'pending')
                                        <form method="POST" action="{{ route('admin.stores.appeals.update', [$store, $appeal]) }}" class="mt-4 space-y-3">
                                            @csrf
                                            @method('PATCH')
                                            <label class="block">
                                                <span class="field-label">Decision</span>
                                                <select name="decision" required class="field-control">
                                                    <option value="">Choose a decision</option>
                                                    <option value="approved" @selected(old('decision') === 'approved')>Approve and reactivate store</option>
                                                    <option value="rejected" @selected(old('decision') === 'rejected')>Reject and keep suspended</option>
                                                </select>
                                            </label>
                                            <label class="block">
                                                <span class="field-label">Review note</span>
                                                <textarea name="review_note" rows="3" minlength="10" maxlength="1000" required class="field-control" placeholder="Explain the decision to the seller.">{{ old('review_note') }}</textarea>
                                            </label>
                                            <button class="inline-flex min-h-11 items-center rounded-lg bg-brand px-4 text-sm font-semibold text-white">Save appeal decision</button>
                                        </form>
                                    @else
                                        <p class="mt-2 text-xs text-muted">Reviewed {{ $appeal->reviewed_at?->format('M j, Y · g:i A') }}{{ $appeal->reviewer ? ' by '.$appeal->reviewer->name : '' }}</p>
                                        @if (! $hasPendingAppeal && (($appeal->status === 'approved' && $store->status === 'active') || ($appeal->status === 'rejected' && $store->status === 'suspended')))
                                            @if ($appeal->status === 'approved')
                                                <p class="mt-3 rounded-[10px] border border-warning/20 bg-warning/10 p-3 text-sm text-warning-text">Reopening this approved appeal will suspend the active store until a new review decision.</p>
                                            @endif
                                            <form method="POST" action="{{ route('admin.stores.appeals.reopen', [$store, $appeal]) }}" class="mt-4 space-y-3" @if ($appeal->status === 'approved') onsubmit="return confirm('Reopening this appeal will suspend the active store until a new review decision. Continue?')" @endif>
                                                @csrf
                                                @method('PATCH')
                                                <label class="block">
                                                    <span class="field-label">Why reopen this appeal?</span>
                                                    <textarea name="reason[{{ $appeal->id }}]" rows="2" minlength="10" maxlength="1000" required class="field-control" placeholder="Record why the previous decision needs another review.">{{ old('reason.'.$appeal->id) }}</textarea>
                                                </label>
                                                @error('reason.'.$appeal->id)<p class="text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                                                @error('appeal.'.$appeal->id)<p class="text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                                                <button class="inline-flex min-h-11 items-center rounded-lg border border-line px-4 text-sm font-semibold text-ink hover:bg-canvas">Reopen appeal</button>
                                            </form>
                                        @endif
                                    @endif
                                </article>
                            @empty
                                <p class="rounded-lg bg-canvas p-4 text-sm text-muted">No suspension appeals have been submitted.</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="rounded-[14px] border border-line bg-surface p-5 ">
                        <h2 class="font-semibold text-ink">Quick links</h2>
                        <div class="mt-4 grid gap-2">
                            <a href="{{ route('catalog.store', $store->slug) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-line px-4 text-sm font-semibold text-ink transition hover:border-brand/30 hover:text-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">View storefront</a>
                            <a href="{{ route('admin.products.index', ['store_id' => $store->id]) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-line px-4 text-sm font-semibold text-ink transition hover:border-brand/30 hover:text-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">View products</a>
                            <a href="{{ route('admin.orders.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-line px-4 text-sm font-semibold text-ink transition hover:border-brand/30 hover:text-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Review marketplace orders</a>
                        </div>
                        <p class="mt-3 text-xs leading-5 text-muted">The public storefront only renders when both the store and its tenant are active.</p>
                    </section>

                    <section class="rounded-[14px] border border-danger/20 bg-surface p-5 " aria-label="Store moderation">
                        <h2 class="font-semibold text-ink">Moderation</h2>
                        @if ($store->status === 'suspended')
                            <p class="mt-2 text-sm leading-6 text-muted">This store is suspended and hidden from the storefront. Reactivating restores both the store and its tenant to active.</p>
                            <div x-data class="mt-4">
                                <button type="button" @click="$refs.reactivate.showModal()" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-brand px-4 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Reactivate store</button>
                                <dialog x-ref="reactivate" class="w-[calc(100%-2rem)] max-w-md rounded-[14px] border-0 p-0 shadow-2xl backdrop:bg-ink/40">
                                    <form method="POST" action="{{ route('admin.stores.reactivate', $store) }}" class="bg-surface p-6">
                                        @csrf
                                        @method('PATCH')
                                        <h3 class="text-lg font-semibold text-ink">Reactivate {{ $store->name }}?</h3>
                                        <p class="mt-2 text-sm leading-6 text-muted">The store and its tenant return to active. Listings become eligible for the public storefront again. This is recorded in the audit log with your name and timestamp.</p>
                                        <div class="mt-5 flex gap-2">
                                            <button type="button" @click="$refs.reactivate.close()" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg border border-line px-4 text-sm font-semibold text-ink">Keep suspended</button>
                                            <button type="submit" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-hover">Confirm reactivation</button>
                                        </div>
                                    </form>
                                </dialog>
                            </div>
                        @else
                            <p class="mt-2 text-sm leading-6 text-muted">Suspending hides the store and its tenant from the storefront immediately. A reason is required and stored in the audit log.</p>
                            <div x-data class="mt-4">
                                <button type="button" @click="$refs.suspend.showModal()" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-danger/20 bg-surface px-4 text-sm font-semibold text-danger transition hover:bg-danger/10 focus:outline-none focus:ring-2 focus:ring-danger focus:ring-offset-2">Suspend store</button>
                                <dialog x-ref="suspend" class="w-[calc(100%-2rem)] max-w-md rounded-[14px] border-0 p-0 shadow-2xl backdrop:bg-ink/40">
                                    <form method="POST" action="{{ route('admin.stores.suspend', $store) }}" class="bg-surface p-6">
                                        @csrf
                                        @method('PATCH')
                                        <h3 class="text-lg font-semibold text-ink">Suspend {{ $store->name }}?</h3>
                                        <p class="mt-2 text-sm leading-6 text-muted">The store and its tenant move to suspended and disappear from the public storefront. This is recorded with your name, reason, and timestamp.</p>
                                        <label for="suspend-reason" class="mt-4 block text-sm font-medium text-ink">Reason (required, min. 10 characters)</label>
                                        <textarea id="suspend-reason" name="reason" rows="4" required minlength="10" maxlength="1000" placeholder="e.g. Repeated policy violations reported by buyers…" class="mt-1.5 block w-full rounded-lg border-line text-sm focus:border-danger focus:ring-danger">{{ old('reason') }}</textarea>
                                        <div class="mt-5 flex gap-2">
                                            <button type="button" @click="$refs.suspend.close()" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg border border-line px-4 text-sm font-semibold text-ink">Cancel</button>
                                            <button type="submit" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg bg-danger px-4 text-sm font-semibold text-white hover:bg-danger/90">Confirm suspension</button>
                                        </div>
                                    </form>
                                </dialog>
                            </div>
                        @endif
                    </section>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
