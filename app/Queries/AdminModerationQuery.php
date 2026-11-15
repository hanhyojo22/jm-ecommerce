<?php

namespace App\Queries;

use App\Models\AuditLog;
use App\Models\ProductReview;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AdminModerationQuery
{
    private const ITEM_LIMIT = 12;

    private const RECENT_DECISION_ACTIONS = [
        'product_review.moderated',
    ];

    /** @return array{productReports:int,suspendedStores:int,recentDecisions:int,attention:int} */
    public static function counts(): array
    {
        $counts = [
            'productReports' => ProductReview::query()->published()->whereHas('reports')->count(),
            'suspendedStores' => Store::query()->where('status', 'suspended')->count(),
            'recentDecisions' => AuditLog::query()
                ->whereIn('action', self::RECENT_DECISION_ACTIONS)
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
        ];
        $counts['attention'] = $counts['productReports'];

        return $counts;
    }

    /**
     * @return Collection<int, array{type:string,id:string,store_id:?string,title:string,subtitle:string,reason:string,received_at:Carbon}>
     */
    public static function items(string $type = 'all', string $sort = 'oldest'): Collection
    {
        $direction = $sort === 'newest' ? 'desc' : 'asc';
        $items = collect()
            ->concat(self::productReports($direction))
            ->when($type !== 'all', fn (Collection $items): Collection => $items->where('type', $type));

        $items = $sort === 'newest'
            ? $items->sortByDesc(fn (array $item): int => $item['received_at']->getTimestamp())
            : $items->sortBy(fn (array $item): int => $item['received_at']->getTimestamp());

        return $items->take(self::ITEM_LIMIT)->values();
    }

    /** @return Collection<int, array{type:string,id:string,store_id:?string,title:string,subtitle:string,reason:string,received_at:Carbon}> */
    private static function productReports(string $direction): Collection
    {
        return ProductReview::query()
            ->select(['id', 'product_id', 'user_id', 'created_at', 'status'])
            ->published()
            ->whereHas('reports')
            ->with(['product:id,name', 'user:id,name'])
            ->withCount('reports')
            ->withMax('reports', 'created_at')
            ->orderBy('reports_max_created_at', $direction)
            ->limit(self::ITEM_LIMIT)
            ->get()
            ->map(fn (ProductReview $review): array => self::item(
                'product_report',
                $review->id,
                null,
                $review->product?->name ?? 'Product review',
                'Review author: '.($review->user?->name ?? 'Customer'),
                $review->reports_count.' '.Str::plural('customer report', $review->reports_count),
                Carbon::parse($review->reports_max_created_at),
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
