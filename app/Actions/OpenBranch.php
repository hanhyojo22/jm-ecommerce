<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OpenBranch
{
    /**
     * Adds a branch to the pharmacy. It starts inactive, so it stays off the storefront until
     * head office has set its map pin and stock and switches it on.
     *
     * @param  array{name: string, phone: string, address_line: string, city: string}  $details
     */
    public function handle(User $actor, Tenant $tenant, array $details, ?string $ipAddress = null): Store
    {
        return DB::transaction(function () use ($actor, $tenant, $details, $ipAddress): Store {
            // Store names are unique regardless of case across the whole platform.
            if (Store::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($details['name'])])->exists()) {
                throw ValidationException::withMessages(['name' => 'Another branch already uses this name.']);
            }

            $store = $tenant->stores()->create([...$details, 'slug' => $this->uniqueSlug($details['name']), 'status' => 'inactive']);

            AuditLog::query()->create([
                'actor_user_id' => $actor->id,
                'action' => 'store.opened',
                'entity_type' => Store::class,
                'entity_id' => $store->id,
                'new_values' => [...$details, 'status' => 'inactive'],
                'ip_address' => $ipAddress,
            ]);

            return $store;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'branch';
        $slug = $base;

        while (Store::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }
}
