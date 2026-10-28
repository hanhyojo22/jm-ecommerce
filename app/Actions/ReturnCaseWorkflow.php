<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\OrderIssue;
use App\Models\Product;
use App\Models\ReturnCase;
use App\Models\ReturnCaseEvidence;
use App\Models\ReturnCaseItem;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\GmailOrderMailer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnCaseWorkflow
{
    /**
     * What each notified case action means to the buyer and the seller. A type that is absent
     * here changes nothing either side has to act on, so it is recorded without an email.
     *
     * @var array<string, string>
     */
    private const NOTICES = [
        'submitted' => 'A buyer opened a return request.',
        'admin_opened' => 'JMMart opened a return case for this order.',
        'offer' => 'The seller offered a remedy. The buyer can accept it or appeal to JMMart.',
        'reject' => 'The seller rejected the request. The buyer can appeal to JMMart.',
        'accept' => 'The buyer accepted the offer.',
        'appeal' => 'The case was appealed to JMMart.',
        'handoff' => 'The buyer handed the goods over for return.',
        'receive' => 'The seller recorded the returned goods as received.',
        'refund' => 'The seller recorded a refund payment. The buyer should confirm receipt.',
        'ship' => 'The seller recorded a replacement shipment. The buyer should confirm receipt.',
        'decide' => 'JMMart decided the appeal.',
        'takeover' => 'JMMart took the overdue case over for review.',
        'reopen' => 'JMMart set the recorded outcome aside. The seller must fulfil the remedy again.',
        'confirm' => 'The buyer confirmed the outcome and the case is complete.',
        'verify' => 'JMMart verified the outcome and closed the case.',
        'close' => 'JMMart closed the case because its deadline passed without a response.',
    ];

    /** @param array<string, mixed> $data */
    public function open(SellerOrder $order, User $actor, array $data, bool $admin = false): ReturnCase
    {
        return DB::transaction(function () use ($order, $actor, $data, $admin): ReturnCase {
            $order = SellerOrder::query()->with('order')->lockForUpdate()->findOrFail($order->id);
            abort_unless($admin || $order->order->user_id === $actor->id, 404);
            if ($admin
                ? $order->status === 'cancelled'
                : ($order->status !== 'delivered' || $order->delivered_at === null || now()->greaterThan($order->delivered_at->copy()->addDays((int) config('marketplace.orders.return_window_days'))))) {
                throw ValidationException::withMessages(['items' => $admin
                    ? 'A cancelled store order cannot have a return case.'
                    : 'The seven-day request window has ended or delivery cannot be verified. Contact support for a later claim.']);
            }
            $issue = null;
            if ($admin && isset($data['order_issue_id'])) {
                $issue = OrderIssue::query()->lockForUpdate()->findOrFail($data['order_issue_id']);
                if ($issue->seller_order_id !== $order->id || $issue->buyer_id !== $order->order->user_id || $issue->status !== 'open' || ReturnCase::query()->where('order_issue_id', $issue->id)->exists()) {
                    throw ValidationException::withMessages(['order_issue_id' => 'This support claim cannot be converted.']);
                }
            }
            $items = $order->items()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $requested = collect($data['items'])->filter(fn (array $line): bool => (int) $line['quantity'] > 0)->values();
            if ($requested->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Select at least one affected unit.']);
            }
            if ($requested->pluck('order_item_id')->unique()->count() !== $requested->count()) {
                throw ValidationException::withMessages(['items' => 'Select each purchased item only once.']);
            }
            foreach ($requested as $line) {
                $item = $items->get($line['order_item_id']);
                if ($item === null) {
                    throw ValidationException::withMessages(['items' => 'An item does not belong to this store order.']);
                }
                $claimed = ReturnCaseItem::query()->where('order_item_id', $item->id)->sum('quantity');
                if (! $admin && $claimed + $line['quantity'] > $item->quantity) {
                    throw ValidationException::withMessages(['items' => 'Every unit of this item is already covered by a return case. Contact support to revisit a decided case.']);
                }
            }
            $case = ReturnCase::query()->create([
                'seller_order_id' => $order->id, 'buyer_id' => $order->order->user_id, 'opened_by' => $actor->id, 'order_issue_id' => $issue?->id,
                'status' => 'awaiting_seller', 'reason' => $data['reason'], 'requested_remedy' => $data['requested_remedy'],
                'details' => $data['details'], 'respond_by' => now()->addHours(48),
            ]);
            foreach ($requested as $line) {
                $item = $items->get($line['order_item_id']);
                $case->items()->create(['order_item_id' => $item->id, 'quantity' => $line['quantity'], 'unit_price' => $item->unit_price]);
            }
            foreach ($data['photos'] ?? [] as $photo) {
                $this->evidence($case, $actor, 'problem', $photo);
            }
            $issue?->update(['status' => 'resolved', 'resolution_note' => 'Converted to return case '.$case->id.'.', 'resolved_by' => $actor->id]);
            $this->event($case, $actor, $admin ? 'admin_opened' : 'submitted', $data['details'], ['items' => $requested->all()]);

            return $case;
        });
    }

    /** @param array<string, mixed> $data */
    public function act(ReturnCase $case, User $actor, string $action, array $data = []): ReturnCase
    {
        return DB::transaction(function () use ($case, $actor, $action, $data): ReturnCase {
            $case = ReturnCase::query()->with('items.orderItem', 'outcome', 'sellerOrder')->lockForUpdate()->findOrFail($case->id);
            $status = $case->status;
            $changes = [];
            $eventData = [];
            $note = $data['note'] ?? null;
            switch ($action) {
                case 'reject':
                case 'offer':
                    $this->requireStatus($status, ['awaiting_seller']);
                    $changes = ['status' => $action === 'reject' ? 'rejected' : 'offered', 'response_note' => $note, 'buyer_respond_by' => now()->addDays(7)];
                    if ($action === 'offer') {
                        $changes += $this->offerFields($data);
                    }
                    break;
                case 'accept':
                    $this->requireStatus($status, ['offered']);
                    $this->requireBuyerDeadline($case);
                    $changes = $this->startRemedy($case);
                    break;
                case 'appeal':
                    $this->requireStatus($status, ['awaiting_seller', 'rejected', 'offered', 'awaiting_confirmation']);
                    if ($status === 'awaiting_seller' && ! $case->isOverdue()) {
                        throw ValidationException::withMessages(['action' => 'The seller still has time to respond.']);
                    }
                    if (in_array($status, ['rejected', 'offered'], true)) {
                        $this->requireBuyerDeadline($case);
                    }
                    $changes = ['status' => 'appealed'];
                    break;
                case 'handoff':
                    $this->requireStatus($status, ['awaiting_return']);
                    $this->evidence($case, $actor, 'handoff', $data['proof']);
                    $changes = ['status' => 'awaiting_receipt', 'handed_off_at' => now(), 'return_by' => now()->addWeekdays(10)];
                    break;
                case 'receive':
                    $this->requireStatus($status, ['awaiting_receipt']);
                    $changes = ['status' => 'awaiting_fulfillment', 'received_at' => now(), 'fulfill_by' => now()->addWeekdays(5)];
                    break;
                case 'refund':
                    $this->requireStatus($status, ['awaiting_fulfillment']);
                    $this->requireRemedy($case, 'refund');
                    if ($case->sellerOrder->cod_collected_at === null) {
                        throw ValidationException::withMessages(['action' => 'No cash has been collected for this store order, so there is nothing to refund. Record the collection first, or ask support to change the remedy.']);
                    }
                    $amount = $case->items->reduce(fn (string $sum, ReturnCaseItem $item): string => bcadd($sum, bcmul((string) $item->unit_price, (string) $item->quantity, 2), 2), '0.00');
                    $proof = $this->evidence($case, $actor, 'refund', $data['proof']);
                    $case->outcome()->create(['recorded_by' => $actor->id, 'kind' => 'refund', 'amount' => $amount, 'paid_at' => $data['paid_at'], 'proof_evidence_id' => $proof->id, 'created_at' => now()]);
                    $changes = ['status' => 'awaiting_confirmation', 'buyer_respond_by' => now()->addDays(7)];
                    $eventData = ['refund_amount' => $amount, 'paid_at' => $data['paid_at'], 'proof_evidence_id' => $proof->id];
                    break;
                case 'ship':
                    $this->requireStatus($status, ['awaiting_fulfillment']);
                    $this->requireRemedy($case, 'replacement');
                    $this->replacementStock($case, $actor);
                    $case->outcome()->create(['recorded_by' => $actor->id, 'kind' => 'replacement', 'shipment_reference' => $data['reference'], 'shipped_at' => now(), 'created_at' => now()]);
                    $changes = ['status' => 'awaiting_confirmation', 'buyer_respond_by' => now()->addDays(7)];
                    $eventData = ['shipment_reference' => $data['reference']];
                    break;
                case 'confirm':
                    $this->requireStatus($status, ['awaiting_confirmation']);
                    $changes = ['status' => 'completed', 'completed_at' => now()];
                    break;
                case 'takeover':
                    $this->requireStatus($status, ['awaiting_seller', 'awaiting_receipt', 'awaiting_fulfillment', 'awaiting_confirmation']);
                    if (! $case->isOverdue()) {
                        throw ValidationException::withMessages(['action' => 'This case is not overdue.']);
                    }
                    $changes = ['status' => 'appealed'];
                    break;
                case 'close':
                    $this->requireStatus($status, ['offered', 'rejected', 'awaiting_return']);
                    if (! $case->isOverdue()) {
                        throw ValidationException::withMessages(['action' => 'Wait for the buyer to act or for their deadline to pass.']);
                    }
                    $changes = ['status' => 'closed', 'response_note' => $note, 'completed_at' => now()];
                    break;
                case 'reopen':
                    $this->requireStatus($status, ['appealed']);
                    if ($case->outcome === null) {
                        throw ValidationException::withMessages(['action' => 'There is no recorded outcome to set aside.']);
                    }
                    if ($case->outcome->kind === 'replacement') {
                        $this->replacementStock($case, $actor, restore: true);
                    }
                    $eventData = ['voided_outcome' => $case->outcome->only(['kind', 'amount', 'paid_at', 'shipment_reference'])];
                    $case->outcome->delete();
                    $case->unsetRelation('outcome');
                    $changes = ['status' => 'awaiting_fulfillment', 'fulfill_by' => now()->addWeekdays(5)];
                    break;
                case 'decide':
                    $this->requireStatus($status, ['appealed']);
                    if ($case->outcome !== null) {
                        throw ValidationException::withMessages(['action' => 'An outcome was already recorded. Review its proof before closing or arranging further support.']);
                    }
                    $changes = $data['decision'] === 'deny'
                        ? ['status' => 'denied', 'response_note' => $note]
                        : $this->offerFields($data) + ['response_note' => $note];
                    if ($data['decision'] !== 'deny') {
                        $case->fill($changes);
                        $changes += $this->startRemedy($case);
                    }
                    break;
                case 'verify':
                    $this->requireStatus($status, ['awaiting_confirmation', 'appealed']);
                    if ($status === 'awaiting_confirmation' && ($case->buyer_respond_by === null || now()->lessThanOrEqualTo($case->buyer_respond_by))) {
                        throw ValidationException::withMessages(['action' => 'Wait for buyer confirmation or the response deadline.']);
                    }
                    if ($case->outcome === null) {
                        throw ValidationException::withMessages(['action' => 'No refund or replacement has been recorded.']);
                    }
                    $changes = ['status' => 'completed', 'completed_at' => now()];
                    break;
                case 'restock':
                    if ($case->received_at === null) {
                        throw ValidationException::withMessages(['action' => 'Record receipt before restocking.']);
                    }
                    $line = $case->items->firstWhere('id', $data['case_item_id']);
                    if ($line === null || $line->restocked_quantity + $data['quantity'] > $line->quantity) {
                        throw ValidationException::withMessages(['quantity' => 'The resellable quantity exceeds the received units.']);
                    }
                    $this->restock($line, $data['quantity']);
                    $note = 'Inspected and restocked '.$data['quantity'].' unit(s).';
                    $eventData = ['order_item_id' => $line->order_item_id, 'quantity' => $data['quantity']];
                    break;
                default:
                    throw ValidationException::withMessages(['action' => 'Unknown case action.']);
            }
            if ($changes !== []) {
                $case->update($changes);
            }
            $this->event($case, $actor, $action, $note, $eventData + collect($changes)->except('response_note')->all());

            return $case;
        });
    }

    private function requireStatus(string $actual, array $allowed): void
    {
        if (! in_array($actual, $allowed, true)) {
            throw ValidationException::withMessages(['action' => 'This case action is no longer available.']);
        }
    }

    private function requireBuyerDeadline(ReturnCase $case): void
    {
        if ($case->buyer_respond_by === null || now()->greaterThan($case->buyer_respond_by)) {
            throw ValidationException::withMessages(['action' => 'The response window has ended. Contact support.']);
        }
    }

    private function requireRemedy(ReturnCase $case, string $remedy): void
    {
        if ($case->remedy !== $remedy) {
            throw ValidationException::withMessages(['action' => 'This action does not match the approved remedy.']);
        }
    }

    /** @param array<string, mixed> $data */
    private function offerFields(array $data): array
    {
        return ['remedy' => $data['decision'] ?? $data['remedy'], 'return_waived' => (bool) ($data['return_waived'] ?? false), 'return_instructions' => $data['return_instructions'] ?? null, 'return_method' => $data['return_method'] ?? null];
    }

    /**
     * Pick the remedy up where the goods already are: a waived or completed return goes
     * straight to fulfilment, and goods already in transit wait on the seller's receipt.
     */
    private function startRemedy(ReturnCase $case): array
    {
        if ($case->return_waived || $case->received_at !== null) {
            return ['status' => 'awaiting_fulfillment', 'fulfill_by' => now()->addWeekdays(5)];
        }

        return $case->handed_off_at !== null
            ? ['status' => 'awaiting_receipt', 'return_by' => now()->addWeekdays(10)]
            : ['status' => 'awaiting_return', 'return_by' => now()->addDays(7)];
    }

    private function evidence(ReturnCase $case, User $actor, string $kind, UploadedFile $file): ReturnCaseEvidence
    {
        $path = $file->store('returns/'.$case->id, 'chat_attachments');
        if ($path === false) {
            throw ValidationException::withMessages(['proof' => 'Evidence could not be saved.']);
        }
        $evidence = $case->evidence()->create(['uploaded_by' => $actor->id, 'kind' => $kind, 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'created_at' => now()]);
        $this->event($case, $actor, 'evidence_added', null, ['kind' => $kind, 'evidence_id' => $evidence->id]);

        return $evidence;
    }

    private function replacementStock(ReturnCase $case, User $actor, bool $restore = false): void
    {
        foreach ($case->items as $line) {
            $item = $line->orderItem;
            $product = Product::withTrashed()->lockForUpdate()->find($item->product_id);
            $variant = $item->product_variant_id ? $product?->variants()->whereKey($item->product_variant_id)->lockForUpdate()->first() : null;
            if ($restore) {
                $product?->increment('stock_quantity', $line->quantity);
                $variant?->increment('stock_quantity', $line->quantity);
                $this->event($case, $actor, 'replacement_stock_restored', null, ['order_item_id' => $item->id, 'quantity' => $line->quantity]);

                continue;
            }
            if ($product === null || $product->stock_quantity < $line->quantity || ($item->product_variant_id && ($variant === null || $variant->stock_quantity < $line->quantity))) {
                throw ValidationException::withMessages(['action' => 'Replacement stock is unavailable. Ask support to change the remedy.']);
            }
            $product->decrement('stock_quantity', $line->quantity);
            $variant?->decrement('stock_quantity', $line->quantity);
            $this->event($case, $actor, 'replacement_stock_reduced', null, ['order_item_id' => $item->id, 'quantity' => $line->quantity]);
        }
    }

    private function restock(ReturnCaseItem $line, int $quantity): void
    {
        $item = $line->orderItem;
        $product = Product::withTrashed()->lockForUpdate()->find($item->product_id);
        if ($product === null) {
            throw ValidationException::withMessages(['quantity' => 'The original product no longer exists.']);
        }
        $variant = $item->product_variant_id !== null
            ? $product->variants()->whereKey($item->product_variant_id)->lockForUpdate()->first()
            : null;
        if ($item->product_variant_id !== null && $variant === null) {
            throw ValidationException::withMessages(['quantity' => 'The original variant no longer exists.']);
        }
        $product->increment('stock_quantity', $quantity);
        $variant?->increment('stock_quantity', $quantity);
        $line->increment('restocked_quantity', $quantity);
    }

    private function event(ReturnCase $case, User $actor, string $type, ?string $note = null, array $data = []): void
    {
        $case->events()->create(['actor_id' => $actor->id, 'type' => $type, 'note' => $note, 'data' => $data, 'created_at' => now()]);
        AuditLog::query()->create(['actor_user_id' => $actor->id, 'action' => 'return_case.'.$type, 'entity_type' => ReturnCase::class, 'entity_id' => $case->id, 'new_values' => $data + ['status' => $case->status], 'ip_address' => request()->ip()]);
        if (isset(self::NOTICES[$type])) {
            DB::afterCommit(function () use ($case, $type): void {
                try {
                    $case->load('buyer', 'sellerOrder.order', 'sellerOrder.store.tenant.members.user');
                    $deadline = $case->deadline();
                    $body = self::NOTICES[$type]
                        ."\n\nOrder {$case->sellerOrder->order->number} · {$case->sellerOrder->store->name}."
                        .($deadline !== null ? "\nThe next step is due by {$deadline->format('M j, Y g:i A')}." : '');
                    $recipients = collect([$case->buyer])->merge($case->sellerOrder->store->tenant->members->pluck('user'))->filter()->unique('id');
                    foreach ($recipients as $recipient) {
                        $url = $recipient->id === $case->buyer_id ? route('returns.show', $case) : route('seller.returns.show', $case);
                        app(GmailOrderMailer::class)->send($recipient, 'JMMart return case update', $body."\n\nView the case: {$url}");
                    }
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });
        }
    }
}
