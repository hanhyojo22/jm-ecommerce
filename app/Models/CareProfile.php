<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A family member whose medicine the buyer manages on their own phone (a child, a parent).
 */
class CareProfile extends Model
{
    use HasUlids;

    protected $fillable = ['name', 'relation'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
