<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PayoutProfile extends Model
{
    use HasUlids;

    protected $fillable = ['method', 'provider', 'holder_name', 'account_number', 'name_requires_review'];

    protected $hidden = ['account_number'];

    protected function casts(): array
    {
        return ['account_number' => 'encrypted', 'name_requires_review' => 'boolean'];
    }

    public function maskedAccountNumber(): string
    {
        return '•••• '.substr($this->account_number, -4);
    }
}
