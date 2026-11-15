<?php

namespace App\Models;

use App\Queries\CatalogQuery;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasUlids;

    protected $fillable = ['user_id', 'store_id', 'reminded_at'];

    protected function casts(): array
    {
        return ['reminded_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The branch this cart buys from; stock and availability are read there.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * The branch this cart buys a company's products from: its chosen branch, or — shortcut: the
     * web has no branch picker until Phase 7 — that company's first open branch.
     */
    public function branchFor(string $tenantId): ?Store
    {
        if ($this->store_id !== null) {
            return $this->store;
        }

        return $this->fallbackBranches[$tenantId] ??= CatalogQuery::publicStores()->where('tenant_id', $tenantId)->oldest()->first();
    }

    /** @var array<string, Store|null> */
    private array $fallbackBranches = [];

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
