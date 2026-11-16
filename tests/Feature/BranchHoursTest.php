<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchHoursTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_branch_manager_sets_only_their_own_branchs_hours(): void
    {
        [$tenant, $makati, $cebu] = $this->pharmacy();
        $manager = $this->member($tenant, $cebu, 'manager');

        $this->actingAs($manager)->get(route('seller.opening-hours.edit', ['branch' => $makati->id]))
            ->assertOk()
            ->assertSee('Opening hours · Botika Plus Cebu')
            ->assertSee('Seller Center sidebar');
        $this->patch(route('seller.opening-hours.update', ['branch' => $makati->id]), ['opening_time' => '08:00', 'closing_time' => '21:30'])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('stores', ['id' => $cebu->id, 'opening_time' => '08:00', 'closing_time' => '21:30']);
        $this->assertDatabaseHas('stores', ['id' => $makati->id, 'opening_time' => null]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.hours_updated', 'entity_id' => $cebu->id, 'actor_user_id' => $manager->id]);
    }

    public function test_head_office_picks_any_branch_but_not_another_companys(): void
    {
        [$tenant, $makati] = $this->pharmacy();
        [, , $otherBranch] = $this->pharmacy('Other Rx');
        $owner = $this->member($tenant, null, 'owner');

        $this->actingAs($owner)->patch(route('seller.opening-hours.update', ['branch' => $makati->id]), ['opening_time' => '07:00', 'closing_time' => '19:00'])
            ->assertSessionHas('status');
        $this->patch(route('seller.opening-hours.update', ['branch' => $otherBranch->id]), ['opening_time' => '07:00', 'closing_time' => '19:00'])
            ->assertNotFound();

        $this->assertDatabaseHas('stores', ['id' => $makati->id, 'opening_time' => '07:00']);
        $this->assertDatabaseHas('stores', ['id' => $otherBranch->id, 'opening_time' => null]);
    }

    public function test_branch_staff_cannot_change_hours(): void
    {
        [$tenant, , $cebu] = $this->pharmacy();

        $this->actingAs($this->member($tenant, $cebu, 'staff'))
            ->patch(route('seller.opening-hours.update'), ['opening_time' => '08:00', 'closing_time' => '20:00'])
            ->assertForbidden();

        $this->assertDatabaseHas('stores', ['id' => $cebu->id, 'opening_time' => null]);
    }

    public function test_set_hours_need_both_valid_times(): void
    {
        [$tenant, , $cebu] = $this->pharmacy();
        $cebu->update(['opening_time' => '08:00', 'closing_time' => '20:00']);
        $this->actingAs($this->member($tenant, $cebu, 'manager'));

        $this->patch(route('seller.opening-hours.update'), ['open_24_hours' => '0', 'opening_time' => '8am'])
            ->assertSessionHasErrors(['opening_time', 'closing_time']);

        $this->assertDatabaseHas('stores', ['id' => $cebu->id, 'opening_time' => '08:00']);
    }

    public function test_open_24_hours_clears_the_times_and_the_app_is_told(): void
    {
        [$tenant, , $cebu] = $this->pharmacy();
        $cebu->update(['opening_time' => '08:00', 'closing_time' => '20:00', 'latitude' => 10.3, 'longitude' => 123.9]);
        $this->actingAs($this->member($tenant, $cebu, 'manager'));

        $this->patch(route('seller.opening-hours.update'), ['open_24_hours' => '1', 'opening_time' => '08:00', 'closing_time' => '20:00'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stores', ['id' => $cebu->id, 'opening_time' => null, 'closing_time' => null]);
        $this->getJson(route('api.v1.store-locations.index'))
            ->assertJsonPath('data.0.open_24_hours', true)
            ->assertJsonPath('data.0.is_open', true);
    }

    /** @return array{0: Tenant, 1: Store, 2: Store} */
    private function pharmacy(string $name = 'Botika Plus'): array
    {
        $tenant = Tenant::create(['name' => $name, 'slug' => str($name)->slug(), 'status' => 'active']);
        $branch = fn (string $city): Store => Store::create([
            'tenant_id' => $tenant->id, 'name' => "{$name} {$city}", 'slug' => str("{$name} {$city}")->slug(),
            'phone' => '+639171234567', 'address_line' => '10 Rizal Street', 'city' => $city, 'status' => 'active',
        ]);

        return [$tenant, $branch('Makati'), $branch('Cebu')];
    }

    private function member(Tenant $tenant, ?Store $branch, string $role): User
    {
        $user = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $tenant->id, 'store_id' => $branch?->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }
}
