<?php

namespace Tests\Feature;

use App\Actions\SuspendStore;
use App\Models\Store;
use App\Models\StoreSuspensionAppeal;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreSuspensionAppealTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_owner_can_submit_one_appeal_for_a_suspended_store(): void
    {
        $administrator = User::factory()->administrator()->create();
        $store = $this->suspendStore('Northwind Goods', $administrator);
        $seller = $this->attachOwner($store);

        $this->actingAs($seller)->get(route('seller-application.create'))
            ->assertOk()
            ->assertSee('Submit an appeal')
            ->assertSee('Repeated policy violations reported by buyers.');

        $this->actingAs($seller)->post(route('seller.suspension-appeals.store'), [
            'statement' => 'We have corrected the listings and attached the requested compliance documents.',
        ])
            ->assertRedirect(route('seller-application.create'))
            ->assertSessionHas('status', 'Your suspension appeal has been submitted for review.');

        $appeal = StoreSuspensionAppeal::query()->sole();
        $this->assertSame($store->id, $appeal->store_id);
        $this->assertSame($seller->id, $appeal->seller_id);
        $this->assertSame('pending', $appeal->status);

        $this->actingAs($seller)->post(route('seller.suspension-appeals.store'), [
            'statement' => 'We would like to send another appeal while the first one is pending.',
        ])
            ->assertRedirect(route('seller-application.create'))
            ->assertSessionHasErrors('appeal');

        $this->assertDatabaseCount('store_suspension_appeals', 1);
    }

    public function test_seller_cannot_submit_an_appeal_without_owning_the_suspended_store(): void
    {
        $administrator = User::factory()->administrator()->create();
        $store = $this->suspendStore('Northwind Goods', $administrator);
        $seller = User::factory()->seller()->create();
        $store->tenant->members()->create(['user_id' => $seller->id, 'role' => 'staff']);

        $this->actingAs($seller)->post(route('seller.suspension-appeals.store'), [
            'statement' => 'I do not own the suspended store but want to appeal this action.',
        ])->assertForbidden();

        $this->assertDatabaseCount('store_suspension_appeals', 0);
    }

    public function test_unauthenticated_user_is_redirected_before_submitting_an_appeal(): void
    {
        $this->post(route('seller.suspension-appeals.store'), [
            'statement' => 'Please review the suspension with this explanation.',
        ])->assertRedirect(route('login'));
    }

    public function test_owner_cannot_appeal_a_store_that_is_not_suspended(): void
    {
        $store = $this->createStore('Northwind Goods');
        $seller = $this->attachOwner($store);

        $this->actingAs($seller)->post(route('seller.suspension-appeals.store'), [
            'statement' => 'Please review the status of our store account.',
        ])->assertForbidden();

        $this->assertDatabaseCount('store_suspension_appeals', 0);
        $this->assertDatabaseHas('stores', ['id' => $store->id, 'status' => 'active']);
    }

    public function test_administrator_can_approve_an_appeal_and_reactivate_the_store(): void
    {
        $administrator = User::factory()->administrator()->create();
        $store = $this->suspendStore('Northwind Goods', $administrator);
        $seller = $this->attachOwner($store);
        $appeal = $this->submitAppeal($seller);

        $this->actingAs($administrator)->get(route('admin.stores.show', $store))
            ->assertOk()
            ->assertSee('Suspension appeals')
            ->assertSee($appeal->statement)
            ->assertSee('Save appeal decision');

        $this->actingAs($administrator)->patch(route('admin.stores.appeals.update', [$store, $appeal]), [
            'decision' => 'approved',
            'review_note' => 'The seller corrected the listings and provided valid documentation.',
        ])
            ->assertRedirect(route('admin.stores.show', $store))
            ->assertSessionHas('status', 'Appeal approved. The store and seller workspace have been reactivated.');

        $this->assertDatabaseHas('store_suspension_appeals', [
            'id' => $appeal->id,
            'status' => 'approved',
            'review_note' => 'The seller corrected the listings and provided valid documentation.',
            'reviewed_by' => $administrator->id,
        ]);
        $this->assertDatabaseHas('stores', ['id' => $store->id, 'status' => 'active']);
        $this->assertDatabaseHas('tenants', ['id' => $store->tenant_id, 'status' => 'active']);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $administrator->id,
            'action' => 'store.suspension_appeal.approved',
            'entity_type' => StoreSuspensionAppeal::class,
            'entity_id' => $appeal->id,
        ]);
    }

    public function test_administrator_can_reject_an_appeal_without_reactivating_the_store(): void
    {
        $administrator = User::factory()->administrator()->create();
        $store = $this->suspendStore('Northwind Goods', $administrator);
        $seller = $this->attachOwner($store);
        $appeal = $this->submitAppeal($seller);

        $this->actingAs($administrator)->patch(route('admin.stores.appeals.update', [$store, $appeal]), [
            'decision' => 'rejected',
            'review_note' => 'The submitted documents do not address the outstanding policy violations.',
        ])
            ->assertRedirect(route('admin.stores.show', $store))
            ->assertSessionHas('status', 'Appeal rejected. The store remains suspended.');

        $this->assertDatabaseHas('store_suspension_appeals', ['id' => $appeal->id, 'status' => 'rejected']);
        $this->assertDatabaseHas('stores', ['id' => $store->id, 'status' => 'suspended']);
        $this->assertDatabaseHas('tenants', ['id' => $store->tenant_id, 'status' => 'suspended']);

        $this->actingAs($seller)->get(route('seller-application.create'))
            ->assertOk()
            ->assertSee('Your previous appeal was not approved.')
            ->assertSee('Submit an appeal');

        $this->actingAs($seller)->post(route('seller.suspension-appeals.store'), [
            'statement' => 'We have now completed the corrective steps described in your decision.',
        ])->assertRedirect(route('seller-application.create'));

        $this->assertDatabaseCount('store_suspension_appeals', 2);
        $this->assertSame('pending', StoreSuspensionAppeal::query()
            ->where('statement', 'We have now completed the corrective steps described in your decision.')
            ->sole()->status);
    }

    public function test_direct_admin_reactivation_resolves_any_pending_appeal(): void
    {
        $administrator = User::factory()->administrator()->create();
        $store = $this->suspendStore('Northwind Goods', $administrator);
        $seller = $this->attachOwner($store);
        $appeal = $this->submitAppeal($seller);

        $this->actingAs($administrator)->patch(route('admin.stores.reactivate', $store))
            ->assertRedirect(route('admin.stores.show', $store));

        $this->assertDatabaseHas('store_suspension_appeals', [
            'id' => $appeal->id,
            'status' => 'approved',
            'reviewed_by' => $administrator->id,
            'review_note' => 'This store was reactivated directly by an administrator.',
        ]);
    }

    public function test_only_administrators_can_review_an_appeal_and_nested_appeals_are_store_scoped(): void
    {
        $administrator = User::factory()->administrator()->create();
        $store = $this->suspendStore('Northwind Goods', $administrator);
        $seller = $this->attachOwner($store);
        $appeal = $this->submitAppeal($seller);
        $otherStore = $this->suspendStore('Harbor Market', $administrator);
        $otherOwner = $this->attachOwner($otherStore);

        $this->actingAs($seller)->patch(route('admin.stores.appeals.update', [$store, $appeal]), [
            'decision' => 'approved',
            'review_note' => 'The seller corrected the listings and provided valid documentation.',
        ])->assertForbidden();

        $this->actingAs($administrator)->patch(route('admin.stores.appeals.update', [$otherStore, $appeal]), [
            'decision' => 'approved',
            'review_note' => 'The seller corrected the listings and provided valid documentation.',
        ])->assertNotFound();

        $this->assertDatabaseHas('store_suspension_appeals', ['id' => $appeal->id, 'seller_id' => $seller->id]);
        $this->assertDatabaseMissing('store_suspension_appeals', ['seller_id' => $otherOwner->id]);
    }

    public function test_appeal_and_review_fields_are_required_and_bounded(): void
    {
        $administrator = User::factory()->administrator()->create();
        $store = $this->suspendStore('Northwind Goods', $administrator);
        $seller = $this->attachOwner($store);

        $this->actingAs($seller)->post(route('seller.suspension-appeals.store'), ['statement' => 'too short'])
            ->assertSessionHasErrors('statement');

        $appeal = $this->submitAppeal($seller);

        $this->actingAs($administrator)->patch(route('admin.stores.appeals.update', [$store, $appeal]), [
            'decision' => 'approved',
            'review_note' => 'short',
        ])->assertSessionHasErrors('review_note');

        $this->actingAs($administrator)->patch(route('admin.stores.appeals.update', [$store, $appeal]), [
            'decision' => 'unknown',
            'review_note' => 'This note is long enough to pass the validation check.',
        ])->assertSessionHasErrors('decision');

        $this->assertDatabaseHas('store_suspension_appeals', ['id' => $appeal->id, 'status' => 'pending']);
        $this->assertDatabaseHas('stores', ['id' => $store->id, 'status' => 'suspended']);
    }

    private function suspendStore(string $name, User $administrator): Store
    {
        $store = $this->createStore($name);

        return app(SuspendStore::class)->handle($administrator, $store, 'Repeated policy violations reported by buyers.');
    }

    private function createStore(string $name): Store
    {
        $slug = str($name)->slug().'-'.str()->lower(str()->random(6));
        $tenant = Tenant::create(['name' => $name, 'slug' => $slug.'-tenant', 'status' => 'active']);

        return Store::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'slug' => $slug,
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => 'active',
        ]);
    }

    private function attachOwner(Store $store): User
    {
        $seller = User::factory()->seller()->create();
        $store->tenant->members()->create(['user_id' => $seller->id, 'role' => 'owner']);

        return $seller;
    }

    private function submitAppeal(User $seller): StoreSuspensionAppeal
    {
        $this->actingAs($seller)->post(route('seller.suspension-appeals.store'), [
            'statement' => 'We corrected the listings and attached the requested compliance documents.',
        ])->assertRedirect(route('seller-application.create'));

        return StoreSuspensionAppeal::query()->sole();
    }
}
