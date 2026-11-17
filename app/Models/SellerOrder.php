<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerOrder extends Model
{
    use HasUlids;

    protected static function booted(): void
    {
        static::creating(function (SellerOrder $order): void {
            $order->return_window_hours ??= (int) config('marketplace.orders.return_window_days') * 24;
        });
    }

    public function returnWindowHours(): int
    {
        return $this->return_window_hours ?? 168;
    }

    public function returnWindowEndsAt(): ?CarbonInterface
    {
        return $this->delivered_at?->copy()->addHours($this->returnWindowHours());
    }

    protected $fillable = [
        'tenant_id',
        'store_id',
        'subtotal',
        'shipping_amount',
        'discount_amount',
        'total',
        'status',
        'shipped_at',
        'pickup_ready_at',
        'rider_name',
        'rider_phone',
        'delivered_at',
        'cod_collected_at',
        'gateway_refund_id',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'shipped_at' => 'datetime',
            'pickup_ready_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cod_collected_at' => 'datetime',
            'return_window_hours' => 'integer',
        ];
    }

    /**
     * What a customer or branch calls a status. The stored values keep their marketplace names
     * (shipped, processing); only the words shown change, and pickup orders read differently.
     */
    public static function statusLabel(string $status, ?string $fulfillment = 'delivery'): string
    {
        $isPickup = $fulfillment === 'pickup';

        return match ($status) {
            'pending' => 'Order placed',
            'confirmed' => 'Accepted',
            'processing' => 'Preparing',
            'shipped' => $isPickup ? 'Ready for pickup' : 'Out for delivery',
            'delivered' => $isPickup ? 'Picked up' : 'Delivered',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(OrderIssue::class);
    }

    public function returnCases(): HasMany
    {
        return $this->hasMany(ReturnCase::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
