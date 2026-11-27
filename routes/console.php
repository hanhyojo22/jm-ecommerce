<?php

use App\Actions\MakeSuperAdministrator;
use App\Actions\SetUpPharmacyCompany;
use App\Actions\UpdateSellerOrderStatus;
use App\Models\CaregiverLink;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ReturnCase;
use App\Models\SellerOrder;
use App\Models\User;
use App\Notifications\MarketplaceNotice;
use App\Services\GmailOrderMailer;
use App\Services\PayMongo;
use App\Services\PushNotifier;
use App\Support\MedicationSupply;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pharmacy:setup {company : The pharmacy company name} {owner : The owner\'s sign-in email}', function (SetUpPharmacyCompany $setUp): int {
    try {
        $tenant = $setUp->handle($this->argument('company'), $this->argument('owner'));
    } catch (LogicException $exception) {
        $this->error($exception->getMessage());

        return 1;
    }

    $this->info("Created {$tenant->name}. Sign in with {$this->argument('owner')}, then add branches in Seller Center → Branches.");

    return 0;
})->purpose('Create the pharmacy company and its head-office owner (run once on a new server)');

Artisan::command('admin:super {email : The super admin\'s sign-in email}', function (MakeSuperAdministrator $makeSuperAdministrator): int {
    try {
        $makeSuperAdministrator->handle($this->argument('email'));
    } catch (LogicException $exception) {
        $this->error($exception->getMessage());

        return 1;
    }

    $this->info("{$this->argument('email')} is now a super admin. Sign in with that email to open the admin workspace.");

    return 0;
})->purpose('Create or promote a super admin who signs in with this email');

Artisan::command('returns:remind', function (GmailOrderMailer $mailer): void {
    $cases = ReturnCase::query()->overdue()
        ->whereDoesntHave('events', fn ($query) => $query->where('type', 'reminder')->where('created_at', '>=', now()->subDays(3)))
        ->with('buyer', 'sellerOrder.order', 'sellerOrder.store.tenant.members.user')
        ->get();

    foreach ($cases as $case) {
        $sellerTurn = in_array($case->status, ['awaiting_seller', 'awaiting_receipt', 'awaiting_fulfillment'], true);
        $recipients = $sellerTurn
            ? $case->sellerOrder->store->tenant->members->pluck('user')->filter()
            : collect([$case->buyer])->filter();

        foreach ($recipients as $recipient) {
            try {
                $mailer->send(
                    $recipient,
                    'Everyday Pharmacy return case is overdue',
                    "The return case for order {$case->sellerOrder->order->number} passed its {$case->deadline()->format('M j, Y g:i A')} deadline and is waiting on you. Everyday Pharmacy can take the case over while it stays unanswered."
                    ."\n\nView the case: ".route($sellerTurn ? 'seller.returns.show' : 'returns.show', $case),
                );
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $case->events()->create(['type' => 'reminder', 'data' => ['status' => $case->status], 'created_at' => now()]);
    }

    $overdue = ReturnCase::query()->overdue()->count();

    if ($overdue > 0) {
        foreach (User::query()->where('account_type', 'admin')->whereIn('platform_role', ['super_admin', 'admin'])->get() as $administrator) {
            $mailer->send(
                $administrator,
                'Everyday Pharmacy return cases need support review',
                "{$overdue} return case(s) are past their deadline and need support to take them over or close them."
                ."\n\nOpen the queue: ".route('admin.returns.index', ['needs_action' => 1]),
            );
        }
    }

    $this->info("Reminded {$cases->count()} case(s); {$overdue} overdue in total.");
})->purpose('Remind the waiting party about overdue return cases and alert support')->daily();

Artisan::command('orders:auto-deliver', function (UpdateSellerOrderStatus $updateStatus): void {
    $sellerOrders = SellerOrder::query()
        ->where('status', 'shipped')
        ->where('shipped_at', '<', now()->subDays((int) config('marketplace.orders.auto_deliver_days')))
        ->whereDoesntHave('issues', fn ($query) => $query->where('status', 'open'))
        ->whereHas('order', fn ($query) => $query->where('fulfillment_method', 'delivery'))
        ->get();
    $delivered = 0;

    foreach ($sellerOrders as $sellerOrder) {
        try {
            $updateStatus->handle($sellerOrder, 'delivered', null);
            $delivered++;
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    $this->info("Marked {$delivered} shipped store order(s) delivered.");
})->purpose('Mark store orders delivered when the buyer never confirms them')->daily();

Artisan::command('orders:cancel-unpaid', function (UpdateSellerOrderStatus $updateStatus, PayMongo $payMongo): void {
    // An online order the buyer never paid holds stock, so it is released after the payment window.
    $sellerOrders = SellerOrder::query()
        ->with('order.payment')
        ->where('status', 'pending')
        ->whereNull('cod_collected_at')
        ->where('created_at', '<', now()->subHours((int) config('marketplace.online_payment.payment_hours')))
        // Only PayMongo orders, which always carry a payment fee. An order from before the switch may
        // already have been paid to the store directly, so support settles those by hand.
        ->whereHas('order.payment', fn ($query) => $query->whereIn('method', Payment::ONLINE_METHODS)->where('status', 'pending')->where('fee_amount', '>', 0))
        ->get();
    $cancelled = 0;

    foreach ($sellerOrders as $sellerOrder) {
        try {
            $updateStatus->handle($sellerOrder, 'cancelled', null);
            $cancelled++;
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    // A late payment on a released order would only be refunded, so the checkout is closed too.
    foreach ($sellerOrders->pluck('order.payment')->unique('id')->filter(fn ($payment): bool => $payment->checkout_session_id !== null) as $payment) {
        try {
            $payMongo->expireCheckoutSession($payment->checkout_session_id);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    $this->info("Cancelled {$cancelled} unpaid online store order(s).");
})->purpose('Release stock held by online orders the buyer never paid')->hourly();

Artisan::command('carts:remind', function (PushNotifier $notifier): void {
    $cutoff = now()->subHours((int) config('marketplace.cart.reminder_hours'));

    $carts = Cart::query()
        ->whereHas('user', fn ($query) => $query->where('is_active', true)->where('cart_reminders', true))
        // Only worth a nudge while something in it can still be bought.
        ->whereHas('items.product', fn ($query) => $query->where('status', 'active')->where('stock_quantity', '>', 0)
            ->whereHas('store', fn ($store) => $store->where('status', 'active')))
        // updated_at is the cart's last activity: item changes touch it (see CartItem::$touches).
        ->where('updated_at', '<', $cutoff)
        // One reminder per idle stretch: a cart changed after its last reminder is due again.
        ->where(fn ($query) => $query->whereNull('reminded_at')->orWhereColumn('reminded_at', '<', 'updated_at'))
        ->with('user')
        ->withCount('items')
        ->get();

    foreach ($carts as $cart) {
        $title = 'Still thinking it over?';
        $lead = $cart->items()->with(['product.store', 'product.tenant', 'product.variants', 'product.thumbnail', 'variant'])->get()
            ->first(fn ($item): bool => $item->isAvailable());
        if ($lead === null) {
            continue;
        }
        $body = $lead->product->name.' is saved in your cart.';

        $notifier->sendToUser($cart->user, $title, $body, array_filter([
            'type' => 'cart',
            'image_url' => ($path = $lead->imagePath()) !== null ? Storage::disk('product_images')->url($path) : null,
        ]));

        try {
            $cart->user->notify(new MarketplaceNotice('cart', $title, $body, route('cart.index')));
        } catch (Throwable $exception) {
            report($exception);
        }

        // Not an activity: keep updated_at so the cart stays marked as idle.
        $cart->timestamps = false;
        $cart->update(['reminded_at' => now()]);
    }

    $this->info("Reminded {$carts->count()} idle cart(s).");
})->purpose('Remind buyers about carts left unchanged for a day')->hourly();

Artisan::command('discount-ids:purge', function (): void {
    // The photo only helps staff check the discount while the order runs; the encrypted name and
    // number stay on the order as the sales record. Saved IDs keep their own photo for reuse.
    $orders = Order::query()
        ->whereNotNull('discount_document_path')
        ->whereHas('sellerOrders')
        ->whereDoesntHave('sellerOrders', fn ($query) => $query->where(fn ($open) => $open->whereNotIn('status', ['delivered', 'cancelled'])
            ->orWhere('updated_at', '>=', now()->subDays(Order::DISCOUNT_PHOTO_DAYS))))
        ->get();

    foreach ($orders as $order) {
        Storage::disk('chat_attachments')->delete($order->discount_document_path);
        // The file name stays so Seller Center can say the photo was deleted, not that none was sent.
        $order->forceFill(['discount_document_path' => null])->save();
    }

    $this->info("Deleted {$orders->count()} finished order(s)' discount ID photo(s).");
})->purpose('Delete SC/PWD ID photos from orders finished more than 30 days ago')->daily();

Artisan::command('medications:check-missed', function (PushNotifier $notifier): void {
    $sent = 0;
    $links = CaregiverLink::query()
        ->where('status', 'accepted')
        ->whereNotNull('caregiver_user_id')
        ->whereHas('patient', fn ($patient) => $patient->where('is_active', true)->whereNotNull('medication_reminders_consent_at'))
        ->with(['patient', 'caregiver'])
        ->get();

    foreach ($links as $link) {
        // Only the patient's own medicine: family profiles are on the patient's phone, not theirs.
        $plans = $link->patient->medicationPlans()
            ->whereNull('care_profile_id')
            ->where('status', 'active')
            ->where('medicine_kind', '!=', 'as_needed')
            ->with(['doseLogs' => fn ($logs) => $logs->whereNotNull('scheduled_for')->where('scheduled_for', '>=', now()->subHours(6))])
            ->get();

        foreach ($plans as $plan) {
            $answered = $plan->doseLogs->map(fn ($log): int => $log->scheduled_for->getTimestamp())->flip();
            // Six hours back at most: an old miss is no longer worth a call.
            foreach (MedicationSupply::slots($plan, now()->subHours(6), now()->subMinutes($link->notify_after_minutes)) as $slot) {
                if ($answered->has($slot->getTimestamp())) {
                    continue;
                }

                $isNew = DB::table('caregiver_alerts')->insertOrIgnore([
                    'caregiver_link_id' => $link->id, 'medication_plan_id' => $plan->id, 'slot_at' => $slot->setTimezone(config('app.timezone')), 'created_at' => now(),
                ]) === 1;
                if (! $isNew) {
                    continue;
                }

                $time = $slot->setTimezone(MedicationSupply::TIMEZONE)->format('g:i A');
                // "Hasn't marked", not "missed": the patient's phone may simply be offline.
                $body = $link->share_medicine_names
                    ? "{$link->patient->name} hasn't marked {$plan->product_name} ({$time}) as taken."
                    : "{$link->patient->name} hasn't marked their {$time} medicine as taken.";
                $notifier->sendToUser($link->caregiver, 'Medicine reminder', $body, ['type' => 'medication']);
                $sent++;
            }
        }
    }

    $this->info("Sent {$sent} caregiver alert(s).");
})->purpose('Tell caregivers when a patient has not marked a dose')->everyFifteenMinutes();
