<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One page of a customer's prescription. The file sits on the private chat_attachments disk and is
 * served only to the pharmacist reviewing the order.
 */
class PrescriptionImage extends Model
{
    use HasUlids;

    protected $fillable = ['prescription_id', 'path', 'original_name', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}
