<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = ['name', 'slug', 'status'];

    public function members(): HasMany
    {
        return $this->hasMany(TenantMember::class);
    }

    public function store(): HasOne
    {
        return $this->hasOne(Store::class);
    }

    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    public function sellerEarnings(): HasMany
    {
        return $this->hasMany(SellerEarning::class);
    }

    public function sellerPayouts(): HasMany
    {
        return $this->hasMany(SellerPayout::class);
    }
}
