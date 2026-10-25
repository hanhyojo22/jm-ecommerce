<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasUlids;

    /** A photo with a color belongs to every variant of that color; one without is a shared product photo. */
    protected $fillable = ['product_id', 'color', 'path', 'position'];
}
