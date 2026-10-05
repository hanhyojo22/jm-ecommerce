<?php

namespace App\Queries;

use App\Models\AuditLog;
use App\Models\MessageReport;
use App\Models\ProductReview;
use App\Models\SellerApplication;
use App\Models\Store;
use App\Models\StoreSuspensionAppeal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AdminModerationQuery
{
    private const ITEM_LIMIT = 12;

    private const RECENT_DECISION_ACTIONS = [
        'seller_application.approved',
        'seller_application.rejected',
        'store.suspension_appeal.approved',
        'store.suspension_appeal.rejected',
        'product_review.moderated',
        'message_report.reviewed',
        'message_report.dismissed',
    ];

    /** @return array{applications:int,appeals:int,productReports:int,chatReports:int,suspendedStores:int,recentDecisions:int,attention:int} */
    public static function counts(): array
    {
        $counts = [
            'applications' => SellerApplication::query()->where('status', 'pending')->count(),
            'appeals' => StoreSuspensionAppeal::query()->where('status', 'pending')->count(),
            'productReports' => ProductReview::query()->published()->whereHas('reports')->count(),
            'chatReports' => MessageReport::query()->where('status', 'pending')->count(),
            'suspendedStores' => Store::query()->where('status', 'suspended')->count(),
            'recentDecisions' => AuditLog::query()
                ->whereIn('action', self::RECENT_DECISION_ACTIONS)
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
        ];
        $counts['attention'] = $counts['applications'] + $counts['appeals'] + $counts['productReports'] + $counts['chatReports'];

        return $counts;
    }

    /**
     * @return Collection<int, array{type:string,id:string,store_id:?string,title:string,subtitle:string,reason:string,received_at:Carbon}>
     */
    public static function items(string $type = 'all'): Collection
    {
        $items = collect()
            ->concat(self::applications())
            ->concat(self::appeals())
            ->concat(self::productReports())
            ->concat(self::chatReports());

        return $items
            ->when($type !== 'all', fn (Collection $items): Collection => $items->where('type', $type))
            ->sortByDesc(fn (array $item): int => $item['received_at']->getTimestamp())
            ->take(self::ITEM_LIMIT)
            ->values();
    }

    /** @return Collection<int, array{type:string,id:string,store_id:?string,title:string,subtitle:string,reason:string,received_at:Carbon}> */
    private static function applications(): Collection
    {
        return SellerApplication::query()
            ->where('status', 'pending')
            ->orderByRaw('COALESCE(submitted_at, created_at) DESC')
            ->limit(self::ITEM_LIMIT)
            ->get(['id', 'store_name', 'seller_name', 'submitted_at', 'created_at'])
            ->map(fn (SellerApplication $application): array => self::item(
                'application',
                $application->id,
                null,
                $application->store_name,
                $application->seller_name,
                'New seller application',
                $application->submitted_at ?? $application->created_at,
            ));
    }

    /** @return Collection<int, array{type:string,id:string,store_id:?string,title:string,subtitle:string,reason:string,received_at:Carbon}> */
    private static function appeals(): Collection
    {
        return StoreSuspensionAppeal::query()
            ->where('status', 'pending')
            ->with('store:id,name')
            ->latest()
            ->limit(self::ITEM_LIMIT)
            ->get(['id', 'store_id', 'created_at'])
            ->map(fn (StoreSuspensionAppeal $appeal): array => self::item(
                'appeal',
                $appeal->id,
                $appeal->store_id,
                $appeal->store?->name ?? 'Store appeal',
                'Suspended store',
                'Suspension appeal',
                $appeal->created_at,
            ));
    }

    /** @return Collection<int, array{type:string,id:string,store_id:?string,title:string,subtitle:string,reason:string,received_at:Carbon}> */
    private static function productReports(): Collection
    {
        return ProductReview::query()
            ->select(['id', 'product_id', 'user_id', 'created_at', 'status'])
            ->published()
            ->whereHas('reports')
            ->with(['product:id,name', 'user:id,name'])
            ->withCount('reports')
            ->withMax('reports', 'created_at')
            ->orderByDesc('reports_max_created_at')
            ->limit(self::ITEM_LIMIT)
            ->get()
            ->map(fn (ProductReview $review): array => self::item(
                'product_report',
                $review->id,
                null,
                $review->product?->name ?? 'Product review',
                $review->user?->name ?? 'Customer review',
                $review->reports_count.' '.Str::plural('customer report', $review->reports_count),
                Carbon::parse($review->reports_max_created_at),
            ));
    }

    /** @return Collection<int, array{type:string,id:string,store_id:?string,title:string,subtitle:string,reason:string,received_at:Carbon}> */
    private static function chatReports(): Collection
    {
        return MessageReport::query()
            ->where('status', 'pending')
            ->with([
                'reporter:id,name',
                'message:id,conversation_id,body',
                'message.conversation:id,store_id',
                'message.conversation.store:id,name',
            ])
            ->latest()
            ->limit(self::ITEM_LIMIT)
            ->get(['id', 'conversation_message_id', 'reporter_id', 'reason', 'created_at'])
            ->map(fn (MessageReport $report): array => self::item(
                'chat_report',
                $report->id,
                null,
                $report->message?->conversation?->store?->name ?? 'Order conversation',
                $report->reporter?->name ?? 'Marketplace customer',
                Str::headline($report->reason),
                $report->created_at,
            ));
    }

    /** @return array{type:string,id:string,store_id:?string,title:string,subtitle:string,reason:string,received_at:Carbon} */
    private static function item(string $type, string $id, ?string $storeId, string $title, string $subtitle, string $reason, Carbon $receivedAt): array
    {
        return [
            'type' => $type,
            'id' => $id,
            'store_id' => $storeId,
            'title' => $title,
            'subtitle' => $subtitle,
            'reason' => $reason,
            'received_at' => $receivedAt,
        ];
    }
}
