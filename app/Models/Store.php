<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = ['tenant_id', 'name', 'slug', 'description', 'logo_path', 'banner_path', 'phone', 'address_line', 'city', 'status', 'gcash_number', 'gcash_qr_path', 'maya_number', 'maya_qr_path', 'bank_name', 'bank_account_name', 'bank_number', 'bank_qr_path'];

    /**
     * A store takes a GCash or Maya payment once buyers have its number and QR. A bank transfer
     * needs the bank, account name, and number instead; its QR Ph code is optional.
     */
    public function acceptsWallet(string $method): bool
    {
        return $method === 'bank'
            ? filled($this->bank_name) && filled($this->bank_account_name) && filled($this->bank_number)
            : filled($this->{$method.'_number'}) && filled($this->{$method.'_qr_path'});
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function appeals(): HasMany
    {
        return $this->hasMany(StoreSuspensionAppeal::class);
    }
}
