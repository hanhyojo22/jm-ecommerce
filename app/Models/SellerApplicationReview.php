<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class SellerApplicationReview extends Model
{
    use HasUlids;

    protected $fillable = ['reviewer_id', 'action', 'note', 'correction_steps'];

    protected function casts(): array
    {
        return ['correction_steps' => 'array'];
    }
}
