<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasUlids;

    protected $fillable = ['product_id', 'product_variant_id', 'path', 'position'];
}
