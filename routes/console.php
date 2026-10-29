<?php

use App\Actions\IssuePlatformFeeStatements;
use App\Actions\UpdateSellerOrderStatus;
use App\Models\Payment;
use App\Models\PlatformFeeStatement;
use App\Models\ReturnCase;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\GmailOrderMailer;
use App\Services\PayMongo;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
                    'JMMart return case is overdue',
                    "The return case for order {$case->sellerOrder->order->number} passed its {$case->deadline()->format('M j, Y g:i A')} deadline and is waiting on you. JMMart can take the case over while it stays unanswered."
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
                'JMMart return cases need support review',
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
        ->whereHas('order.payment', fn ($query) => $query->whereIn('method', [...Payment::ONLINE_METHODS, 'bank'])->where('status', 'pending'))
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

Artisan::command('platform-fees:bill {month? : The month to bill as YYYY-MM; defaults to last month}', function (IssuePlatformFeeStatements $issue, GmailOrderMailer $mailer): int {
    $requested = $this->argument('month');
    if ($requested !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requested) !== 1) {
        $this->error('The month must look like 2026-09.');

        return 1;
    }
    $month = $requested !== null ? CarbonImmutable::createFromFormat('!Y-m', $requested) : CarbonImmutable::now()->subMonthNoOverflow();

    $statements = $issue->handle($month);

    foreach ($statements->filter(fn (PlatformFeeStatement $statement): bool => $statement->status === 'unpaid')->load('tenant.members.user') as $statement) {
        foreach ($statement->tenant->members->pluck('user')->filter() as $recipient) {
            // A rerun issues nothing new, so one failed email must not cost the other stores theirs.
            try {
                $mailer->send(
                    $recipient,
                    "JMMart platform fee statement for {$month->format('F Y')}",
                    'Your platform fee for '.$month->format('F Y').' is ₱'.number_format((float) $statement->amount_due, 2).", due {$statement->due_at->format('M j, Y')}."
                    ."\n\nPay by bank or GCash transfer, then upload the reference number and receipt: ".route('seller.platform-fees.index'),
                );
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    $this->info("Issued {$statements->count()} statement(s) for {$month->format('F Y')}.");

    return 0;
})->purpose('Issue each store its monthly platform fee statement')->monthlyOn(1, '01:00')->withoutOverlapping();

Artisan::command('platform-fees:remind', function (GmailOrderMailer $mailer): void {
    $statements = PlatformFeeStatement::query()->overdue()
        ->where(fn ($query) => $query->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<', now()->subDays(3)))
        ->with('tenant.members.user', 'tenant.store')
        ->get();

    foreach ($statements as $statement) {
        foreach ($statement->tenant->members->pluck('user')->filter() as $recipient) {
            // The list runs in the same order daily, so one bad address must not block every store after it.
            try {
                $mailer->send(
                    $recipient,
                    'JMMart platform fee is overdue',
                    'Your platform fee of ₱'.number_format((float) $statement->amount_due, 2)." for {$statement->period_start->format('F Y')} was due {$statement->due_at->format('M j, Y')}. JMMart may suspend stores with unpaid fees."
                    ."\n\nPay and upload your receipt: ".route('seller.platform-fees.index'),
                );
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $statement->update(['last_reminded_at' => now()]);
    }

    if ($statements->isNotEmpty()) {
        foreach (User::query()->where('account_type', 'admin')->whereIn('platform_role', ['super_admin', 'admin'])->get() as $administrator) {
            $mailer->send(
                $administrator,
                'JMMart platform fees are overdue',
                "{$statements->count()} store(s) have overdue platform fees: ".$statements->map(fn (PlatformFeeStatement $statement): string => $statement->tenant->store?->name ?? $statement->tenant->name)->implode(', ').'.'
                ."\n\nReview them: ".route('admin.platform-fees.index', ['status' => 'overdue']),
            );
        }
    }

    $this->info("Reminded {$statements->count()} overdue statement(s).");
})->purpose('Remind stores with overdue platform fees and alert support')->daily();
