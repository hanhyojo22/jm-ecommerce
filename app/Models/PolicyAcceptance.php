<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PolicyAcceptance extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = ['user_id', 'policy_type', 'version', 'ip_address', 'user_agent', 'accepted_at'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }
}
