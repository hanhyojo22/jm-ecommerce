<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\PlatformFeeStatement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlatformFeePayment
{
    /**
     * The store says it has paid: record the transfer reference and receipt for review.
     */
    public function submit(User $seller, PlatformFeeStatement $statement, string $reference, UploadedFile $proof, ?string $ipAddress = null): PlatformFeeStatement
    {
        return DB::transaction(function () use ($seller, $statement, $reference, $proof, $ipAddress): PlatformFeeStatement {
            $statement = PlatformFeeStatement::query()->lockForUpdate()->findOrFail($statement->id);
            $this->requireStatus($statement, 'unpaid', 'This statement is not waiting for payment.');

            $path = $proof->store('platform-fees/'.$statement->id, 'chat_attachments');
            if ($path === false) {
                throw ValidationException::withMessages(['proof' => 'The receipt could not be saved.']);
            }

            $statement->update([
                'status' => 'submitted',
                'payment_reference' => $reference,
                'payment_proof_path' => $path,
                'payment_proof_name' => $proof->getClientOriginalName(),
                'payment_submitted_at' => now(),
                'review_note' => null,
            ]);
            $this->audit($seller, $statement, 'platform_fee.payment_submitted', ['status' => 'unpaid'], ['status' => 'submitted', 'payment_reference' => $reference], $ipAddress);

            return $statement;
        });
    }

    public function confirm(User $administrator, PlatformFeeStatement $statement, ?string $ipAddress = null): PlatformFeeStatement
    {
        return DB::transaction(function () use ($administrator, $statement, $ipAddress): PlatformFeeStatement {
            $statement = PlatformFeeStatement::query()->lockForUpdate()->findOrFail($statement->id);
            $this->requireStatus($statement, 'submitted', 'Only a submitted payment can be confirmed.');

            $statement->update(['status' => 'paid', 'paid_at' => now(), 'confirmed_by' => $administrator->id]);
            $this->audit($administrator, $statement, 'platform_fee.payment_confirmed', ['status' => 'submitted'], ['status' => 'paid', 'amount_due' => $statement->amount_due], $ipAddress);

            return $statement;
        });
    }

    /**
     * Send the statement back to the store, keeping the note so it knows what to fix.
     */
    public function reject(User $administrator, PlatformFeeStatement $statement, string $note, ?string $ipAddress = null): PlatformFeeStatement
    {
        return DB::transaction(function () use ($administrator, $statement, $note, $ipAddress): PlatformFeeStatement {
            $statement = PlatformFeeStatement::query()->lockForUpdate()->findOrFail($statement->id);
            $this->requireStatus($statement, 'submitted', 'Only a submitted payment can be rejected.');

            $statement->update(['status' => 'unpaid', 'review_note' => $note]);
            $this->audit($administrator, $statement, 'platform_fee.payment_rejected', ['status' => 'submitted'], ['status' => 'unpaid', 'review_note' => $note], $ipAddress);

            return $statement;
        });
    }

    private function requireStatus(PlatformFeeStatement $statement, string $status, string $message): void
    {
        if ($statement->status !== $status) {
            throw ValidationException::withMessages(['statement' => $message]);
        }
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function audit(User $actor, PlatformFeeStatement $statement, string $action, array $oldValues, array $newValues, ?string $ipAddress): void
    {
        AuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'action' => $action,
            'entity_type' => PlatformFeeStatement::class,
            'entity_id' => $statement->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ipAddress,
        ]);
    }
}
