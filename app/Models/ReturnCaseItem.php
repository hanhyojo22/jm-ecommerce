<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnCaseItem extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = ['order_item_id', 'quantity', 'unit_price', 'restocked_quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'restocked_quantity' => 'integer', 'unit_price' => 'decimal:2'];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function returnCase(): BelongsTo
    {
        return $this->belongsTo(ReturnCase::class);
    }
}
