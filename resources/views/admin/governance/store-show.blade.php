<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('admin.stores.index') }}" class="text-sm font-semibold text-emerald-800 underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-emerald-700">Seller Stores</a>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ $store->name }}</h1>
                <p class="mt-2 text-sm text-slate-600">Store profile, verification record, listings, orders, and moderation history.</p>
            </div>
            <span class="inline-flex h-fit rounded-full px-3 py-1.5 text-sm font-semibold ring-1 ring-inset {{ ['active' => 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'inactive' => 'bg-slate-100 text-slate-700 ring-slate-200', 'suspended' => 'bg-rose-50 text-rose-800 ring-rose-200'][$store->status] }}">{{ ucfirst($store->status) }} store</span>
        </div>
    </x-slot>
    @php($orderStyles = ['pending' => 'bg-amber-50 text-amber-800 ring-amber-200', 'confirmed' => 'bg-sky-50 text-sky-800 ring-sky-200', 'processing' => 'bg-violet-50 text-violet-800 ring-violet-200', 'shipped' => 'bg-indigo-50 text-indigo-800 ring-indigo-200', 'delivered' => 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'cancelled' => 'bg-rose-50 text-rose-800 ring-rose-200'])
    @php($productStyles = ['active' => 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'draft' => 'bg-amber-50 text-amber-800 ring-amber-200', 'inactive' => 'bg-slate-100 text-slate-700 ring-slate-200'])
    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">We could not complete that moderation action. {{ $errors->first() }}</div>
            @endif

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="space-y-6">
                    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-wrap items-start gap-4">
                            @if ($store->logo_path)
                                <img src="{{ Storage::disk('public')->url($store->logo_path) }}" alt="{{ $store->name }} logo" class="h-16 w-16 rounded-xl object-cover">
                            @else
                                <div class="flex h-16 w-16 items-center justify-center rounded-xl bg-emerald-100 text-xl font-bold text-emerald-800" aria-hidden="true">{{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}</div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <h2 class="text-xl font-semibold text-slate-950">Store profile</h2>
                                <p class="mt-1 text-sm text-slate-500">{{ $store->city }} · Joined {{ $store->created_at->format('M j, Y') }} · Last updated {{ $store->updated_at->format('M j, Y · g:i A') }}</p>
                            </div>
                        </div>
                        <div class="mt-5 border-t border-slate-100 pt-5">
                            <h3 class="text-sm font-semibold text-slate-950">Description</h3>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $store->description ?: 'No store description provided.' }}</p>
                        </div>
                        <dl class="mt-5 grid gap-5 border-t border-slate-100 pt-5 sm:grid-cols-3">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Products</dt>
                                <dd class="mt-1 text-lg font-semibold tabular-nums text-slate-950">{{ number_format($store->products_count) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Seller orders</dt>
                                <dd class="mt-1 text-lg font-semibold tabular-nums text-slate-950">{{ number_format($store->seller_orders_count) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Tenant status</dt>
                                <dd class="mt-1 font-medium text-slate-900">{{ ucfirst($store->tenant->status) }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                            <h2 class="font-semibold text-slate-950">Products</h2>
                            <p class="mt-1 text-sm text-slate-600">Live listings owned by this store. Prices are current listing prices, not order snapshots.</p>
                        </div>
                        <div class="hidden overflow-x-auto md:block">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">
                                    <tr><th scope="col" class="px-5 py-3.5">Product</th><th scope="col" class="px-5 py-3.5">Category</th><th scope="col" class="px-5 py-3.5 text-right">Price</th><th scope="col" class="px-5 py-3.5">Status</th><th scope="col" class="px-5 py-3.5"><span class="sr-only">Open</span></th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($products as $product)
                                        <tr class="transition hover:bg-slate-50/80">
                                            <td class="px-5 py-4"><p class="font-medium text-slate-900">{{ $product->name }}</p><p class="mt-0.5 text-xs text-slate-500">SKU {{ $product->sku }} · {{ number_format($product->stock_quantity) }} in stock</p></td>
                                            <td class="px-5 py-4 text-sm text-slate-700">{{ $product->category?->name ?? 'Uncategorized' }}</td>
                                            <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-semibold tabular-nums text-slate-900">₱{{ number_format($product->price, 2) }}</td>
                                            <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $productStyles[$product->status] }}">{{ ucfirst($product->status) }}</span></td>
                                            <td class="px-5 py-4 text-right"><a href="{{ route('admin.products.show', $product) }}" class="text-sm font-semibold text-emerald-800 underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-emerald-700">Open</a></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-slate-600">This store has no products yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="divide-y divide-slate-100 md:hidden">
                            @forelse ($products as $product)
                                <article class="flex items-center justify-between gap-3 p-4">
                                    <div class="min-w-0"><p class="truncate font-medium text-slate-900">{{ $product->name }}</p><p class="mt-1 text-xs text-slate-500">₱{{ number_format($product->price, 2) }} · {{ ucfirst($product->status) }}</p></div>
                                    <a href="{{ route('admin.products.show', $product) }}" class="inline-flex min-h-10 items-center rounded-lg border border-slate-200 px-3 text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-700">Open</a>
                                </article>
                            @empty
                                <p class="px-6 py-12 text-center text-sm text-slate-600">This store has no products yet.</p>
                            @endforelse
                        </div>
                        @if ($products->hasPages())
                            <div class="border-t border-slate-200 bg-slate-50 px-4 py-4">{{ $products->links() }}</div>
                        @endif
                    </section>

                    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                            <h2 class="font-semibold text-slate-950">Orders</h2>
                            <p class="mt-1 text-sm text-slate-600">Totals are checkout snapshots; later product edits do not change them.</p>
                        </div>
                        <div class="hidden overflow-x-auto md:block">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">
                                    <tr><th scope="col" class="px-5 py-3.5">Order</th><th scope="col" class="px-5 py-3.5">Placed</th><th scope="col" class="px-5 py-3.5">Status</th><th scope="col" class="px-5 py-3.5 text-right">Total</th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($sellerOrders as $sellerOrder)
                                        <tr>
                                            <td class="px-5 py-4 font-medium text-slate-900">{{ $sellerOrder->order->number }}</td>
                                            <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500">{{ $sellerOrder->created_at->format('M j, Y') }}</td>
                                            <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $orderStyles[$sellerOrder->status] }}">{{ ucfirst($sellerOrder->status) }}</span></td>
                                            <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-semibold tabular-nums text-slate-900">₱{{ number_format((float) $sellerOrder->total, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-6 py-12 text-center text-sm text-slate-600">No orders for this store yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="divide-y divide-slate-100 md:hidden">
                            @forelse ($sellerOrders as $sellerOrder)
                                <article class="flex items-center justify-between gap-3 p-4">
                                    <div><p class="font-medium text-slate-900">{{ $sellerOrder->order->number }}</p><p class="mt-1 text-xs text-slate-500">{{ $sellerOrder->created_at->format('M j, Y') }} · {{ ucfirst($sellerOrder->status) }}</p></div>
                                    <p class="text-sm font-semibold tabular-nums text-slate-900">₱{{ number_format((float) $sellerOrder->total, 2) }}</p>
                                </article>
                            @empty
                                <p class="px-6 py-12 text-center text-sm text-slate-600">No orders for this store yet.</p>
                            @endforelse
                        </div>
                        @if ($sellerOrders->hasPages())
                            <div class="border-t border-slate-200 bg-slate-50 px-4 py-4">{{ $sellerOrders->links() }}</div>
                        @endif
                    </section>

                    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <h2 class="font-semibold text-slate-950">Activity history</h2>
                        <p class="mt-1 text-sm text-slate-600">Recorded moderation and onboarding events for this store and its tenant. Reasons are stored in the audit log.</p>
                        <div class="mt-4 space-y-0 divide-y divide-slate-100">
                            @forelse ($auditLogs as $log)
                                <div class="py-3">
                                    <p class="text-sm font-semibold text-slate-900">{{ Str::headline(str_replace(['.', '_'], ' ', $log->action)) }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $log->actor?->name ?? $log->actor?->email ?? 'System' }} · {{ $log->created_at->format('M j, Y · g:i A') }}</p>
                                    @if (is_array($log->new_values) && filled($log->new_values['reason'] ?? null))
                                        <p class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">Reason: {{ $log->new_values['reason'] }}</p>
                                    @endif
                                </div>
                            @empty
                                <p class="py-4 text-sm text-slate-600">No recorded activity for this store yet.</p>
                            @endforelse
                        </div>
                    </section>
                </div>

                <aside class="space-y-6">
                    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="font-semibold text-slate-950">Seller contact</h2>
                        @if ($owner)
                            <dl class="mt-4 space-y-4 text-sm">
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Owner</dt><dd class="mt-1 font-medium text-slate-900">{{ $owner->name }}</dd></div>
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Email</dt><dd class="mt-1 break-all font-medium text-slate-900"><a href="mailto:{{ $owner->email }}" class="text-emerald-800 underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-emerald-700">{{ $owner->email }}</a></dd></div>
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Store phone</dt><dd class="mt-1 font-medium text-slate-900">{{ $store->phone }}</dd></div>
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Address</dt><dd class="mt-1 font-medium text-slate-900">{{ $store->address_line }}, {{ $store->city }}</dd></div>
                            </dl>
                        @else
                            <p class="mt-4 text-sm text-slate-600">No owner membership is on file for this tenant.</p>
                        @endif
                    </section>

                    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="font-semibold text-slate-950">Verification record</h2>
                        @if ($application)
                            <dl class="mt-4 space-y-4 text-sm">
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Application status</dt><dd class="mt-1 font-medium text-slate-900">{{ ucfirst($application->status) }}</dd></div>
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Submitted category</dt><dd class="mt-1 font-medium text-slate-900">{{ $application->store_category ?: 'Not provided' }}</dd></div>
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Submitted</dt><dd class="mt-1 font-medium text-slate-900">{{ $application->submitted_at?->format('M j, Y · g:i A') ?? 'Not recorded' }}</dd></div>
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Reviewed</dt><dd class="mt-1 font-medium text-slate-900">{{ $application->reviewed_at?->format('M j, Y · g:i A') ?? 'Not yet reviewed' }}</dd></div>
                                <div><dt class="text-xs font-medium uppercase tracking-[0.1em] text-slate-500">Review note</dt><dd class="mt-1 whitespace-pre-line font-medium text-slate-900">{{ $application->review_note ?: 'No review note recorded.' }}</dd></div>
                            </dl>
                        @else
                            <p class="mt-4 text-sm text-slate-600">No verification submission is linked to this store. Only the data the seller submitted is shown here.</p>
                        @endif
                    </section>

                    <section id="suspension-appeals" class="scroll-mt-24 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-label="Store suspension appeals">
                        <h2 class="font-semibold text-slate-950">Suspension appeals</h2>
                        <p class="mt-1 text-sm text-slate-600">Appeals are submitted by the store owner and reviewed here.</p>
                        <div class="mt-4 space-y-4">
                            @forelse ($appeals as $appeal)
                                <article id="appeal-{{ $appeal->id }}" class="rounded-lg border border-slate-200 p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="text-sm font-semibold text-slate-950">{{ $appeal->seller?->name ?? 'Former seller' }}</p>
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ ['pending' => 'bg-amber-50 text-amber-800', 'approved' => 'bg-emerald-50 text-emerald-800', 'rejected' => 'bg-rose-50 text-rose-800'][$appeal->status] }}">{{ ucfirst($appeal->status) }}</span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">Submitted {{ $appeal->created_at->format('M j, Y · g:i A') }}</p>
                                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $appeal->statement }}</p>
                                    @if ($appeal->review_note)
                                        <p class="mt-3 border-t border-slate-100 pt-3 text-sm text-slate-700"><span class="font-semibold">Review note:</span> {{ $appeal->review_note }}</p>
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
                                            <button class="inline-flex min-h-10 items-center rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white">Save appeal decision</button>
                                        </form>
                                    @else
                                        <p class="mt-2 text-xs text-slate-500">Reviewed {{ $appeal->reviewed_at?->format('M j, Y · g:i A') }}{{ $appeal->reviewer ? ' by '.$appeal->reviewer->name : '' }}</p>
                                        @if (! $hasPendingAppeal && (($appeal->status === 'approved' && $store->status === 'active') || ($appeal->status === 'rejected' && $store->status === 'suspended')))
                                            @if ($appeal->status === 'approved')
                                                <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">Reopening this approved appeal will suspend the active store until a new review decision.</p>
                                            @endif
                                            <form method="POST" action="{{ route('admin.stores.appeals.reopen', [$store, $appeal]) }}" class="mt-4 space-y-3" @if ($appeal->status === 'approved') onsubmit="return confirm('Reopening this appeal will suspend the active store until a new review decision. Continue?')" @endif>
                                                @csrf
                                                @method('PATCH')
                                                <label class="block">
                                                    <span class="field-label">Why reopen this appeal?</span>
                                                    <textarea name="reason[{{ $appeal->id }}]" rows="2" minlength="10" maxlength="1000" required class="field-control" placeholder="Record why the previous decision needs another review.">{{ old('reason.'.$appeal->id) }}</textarea>
                                                </label>
                                                @error('reason.'.$appeal->id)<p class="text-sm text-rose-700" role="alert">{{ $message }}</p>@enderror
                                                @error('appeal.'.$appeal->id)<p class="text-sm text-rose-700" role="alert">{{ $message }}</p>@enderror
                                                <button class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-800 hover:bg-slate-50">Reopen appeal</button>
                                            </form>
                                        @endif
                                    @endif
                                </article>
                            @empty
                                <p class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600">No suspension appeals have been submitted.</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="font-semibold text-slate-950">Quick links</h2>
                        <div class="mt-4 grid gap-2">
                            <a href="{{ route('catalog.store', $store->slug) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 px-4 text-sm font-semibold text-slate-800 transition hover:border-emerald-300 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">View storefront</a>
                            <a href="{{ route('admin.products.index', ['store_id' => $store->id]) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 px-4 text-sm font-semibold text-slate-800 transition hover:border-emerald-300 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">View products</a>
                            <a href="{{ route('admin.orders.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 px-4 text-sm font-semibold text-slate-800 transition hover:border-emerald-300 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Review marketplace orders</a>
                        </div>
                        <p class="mt-3 text-xs leading-5 text-slate-500">The public storefront only renders when both the store and its tenant are active.</p>
                    </section>

                    <section class="rounded-xl border border-rose-200 bg-white p-5 shadow-sm" aria-label="Store moderation">
                        <h2 class="font-semibold text-slate-950">Moderation</h2>
                        @if ($store->status === 'suspended')
                            <p class="mt-2 text-sm leading-6 text-slate-600">This store is suspended and hidden from the storefront. Reactivating restores both the store and its tenant to active.</p>
                            <div x-data class="mt-4">
                                <button type="button" @click="$refs.reactivate.showModal()" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">Reactivate store</button>
                                <dialog x-ref="reactivate" class="w-[calc(100%-2rem)] max-w-md rounded-xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/40">
                                    <form method="POST" action="{{ route('admin.stores.reactivate', $store) }}" class="bg-white p-6">
                                        @csrf
                                        @method('PATCH')
                                        <h3 class="text-lg font-semibold text-slate-950">Reactivate {{ $store->name }}?</h3>
                                        <p class="mt-2 text-sm leading-6 text-slate-600">The store and its tenant return to active. Listings become eligible for the public storefront again. This is recorded in the audit log with your name and timestamp.</p>
                                        <div class="mt-5 flex gap-2">
                                            <button type="button" @click="$refs.reactivate.close()" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg border border-slate-200 px-4 text-sm font-semibold text-slate-700">Keep suspended</button>
                                            <button type="submit" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800">Confirm reactivation</button>
                                        </div>
                                    </form>
                                </dialog>
                            </div>
                        @else
                            <p class="mt-2 text-sm leading-6 text-slate-600">Suspending hides the store and its tenant from the storefront immediately. A reason is required and stored in the audit log.</p>
                            <div x-data class="mt-4">
                                <button type="button" @click="$refs.suspend.showModal()" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-rose-200 bg-white px-4 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-600 focus:ring-offset-2">Suspend store</button>
                                <dialog x-ref="suspend" class="w-[calc(100%-2rem)] max-w-md rounded-xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/40">
                                    <form method="POST" action="{{ route('admin.stores.suspend', $store) }}" class="bg-white p-6">
                                        @csrf
                                        @method('PATCH')
                                        <h3 class="text-lg font-semibold text-slate-950">Suspend {{ $store->name }}?</h3>
                                        <p class="mt-2 text-sm leading-6 text-slate-600">The store and its tenant move to suspended and disappear from the public storefront. This is recorded with your name, reason, and timestamp.</p>
                                        <label for="suspend-reason" class="mt-4 block text-sm font-medium text-slate-700">Reason (required, min. 10 characters)</label>
                                        <textarea id="suspend-reason" name="reason" rows="4" required minlength="10" maxlength="1000" placeholder="e.g. Repeated policy violations reported by buyers…" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-rose-600 focus:ring-rose-600">{{ old('reason') }}</textarea>
                                        <div class="mt-5 flex gap-2">
                                            <button type="button" @click="$refs.suspend.close()" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg border border-slate-200 px-4 text-sm font-semibold text-slate-700">Cancel</button>
                                            <button type="submit" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg bg-rose-700 px-4 text-sm font-semibold text-white hover:bg-rose-800">Confirm suspension</button>
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
