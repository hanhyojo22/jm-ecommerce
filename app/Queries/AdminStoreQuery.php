<?php

namespace App\Queries;

use App\Models\SellerApplication;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdminStoreQuery
{
    /**
     * Base store list query shared by the dashboard and the full Admin Stores page.
     */
    public static function base(): Builder
    {
        return Store::query()
            ->with(['tenant.members.user'])
            ->withCount(['products', 'sellerOrders']);
    }

    public static function applySearch(Builder $query, string $search): void
    {
        $search = '%'.mb_strtolower($search).'%';

        $query->where(function (Builder $storeQuery) use ($search): void {
            $storeQuery->whereRaw('LOWER(name) LIKE ?', [$search])
                ->orWhereHas('tenant', function (Builder $tenantQuery) use ($search): void {
                    $tenantQuery->whereRaw('LOWER(name) LIKE ?', [$search])
                        ->orWhereHas('members.user', function (Builder $ownerQuery) use ($search): void {
                            $ownerQuery->whereRaw('LOWER(name) LIKE ?', [$search])
                                ->orWhereRaw('LOWER(email) LIKE ?', [$search]);
                        });
                });
        });
    }

    /**
     * @return Collection<string, int>
     */
    public static function statusCounts(): Collection
    {
        /** @var Collection<string, int> $counts */
        $counts = Store::query()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return $counts;
    }

    public static function total(): int
    {
        return Store::query()->count();
    }

    public static function pendingReviewCount(): int
    {
        return SellerApplication::query()->where('status', 'pending')->count();
    }
}
