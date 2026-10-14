<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class SellerApplicationStatusHistory extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = ['actor_user_id', 'from_status', 'to_status', 'reason', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
