<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class SellerDocument extends Model
{
    use HasUlids;

    protected $fillable = ['type', 'path', 'mime_type'];

    protected $hidden = ['path'];
}
