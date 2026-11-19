<?php

namespace Tests\Feature;

use App\Models\IngredientAlias;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use App\Queries\CatalogQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngredientAliasTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_office_adds_another_name_that_search_then_understands(): void
    {
        [$tenant, $branch] = $this->pharmacy();
        $owner = $this->member($tenant, 'owner');
        $mefenamic = Product::create(['tenant_id' => $tenant->id, 'store_id' => $branch->id, 'name' => 'Dolfenal 500 mg Capsule', 'generic_name' => 'Mefenamic Acid', 'slug' => 'dolfenal', 'sku' => 'DOL-500', 'price' => '12.00', 'stock_quantity' => 5, 'status' => 'active']);

        $this->assertSame([], CatalogQuery::search(['q' => 'ponstan generic'])['products']->getCollection()->all());

        $this->actingAs($owner)->get(route('seller.ingredient-aliases.index'))
            ->assertOk()
            ->assertSee('<option value="Mefenamic Acid">', false);
        $this->actingAs($owner)->post(route('seller.ingredient-aliases.store'), ['alias' => '  Ponstan   Generic ', 'ingredient' => 'Mefenamic Acid'])
            ->assertRedirect(route('seller.ingredient-aliases.index'));

        $this->assertDatabaseHas('ingredient_aliases', ['tenant_id' => $tenant->id, 'alias' => 'ponstan generic', 'ingredient' => 'Mefenamic Acid']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ingredient_alias.added', 'actor_user_id' => $owner->id]);
        $this->assertSame([$mefenamic->id], CatalogQuery::search(['q' => 'Ponstan generic'])['products']->getCollection()->pluck('id')->all());

        $this->actingAs($owner)->post(route('seller.ingredient-aliases.store'), ['alias' => 'PONSTAN GENERIC', 'ingredient' => 'Mefenamic Acid'])
            ->assertSessionHasErrors('alias');
        $this->actingAs($owner)->post(route('seller.ingredient-aliases.store'), ['alias' => 'mefenamic acid', 'ingredient' => 'Mefenamic Acid'])
            ->assertSessionHasErrors('alias');
        $this->assertSame(1, IngredientAlias::query()->where('ingredient', 'Mefenamic Acid')->count());

        $this->actingAs($owner)->delete(route('seller.ingredient-aliases.destroy', IngredientAlias::query()->where('alias', 'ponstan generic')->sole()))
            ->assertRedirect(route('seller.ingredient-aliases.index'));
        $this->assertDatabaseMissing('ingredient_aliases', ['alias' => 'ponstan generic']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ingredient_alias.removed', 'actor_user_id' => $owner->id]);
    }

    public function test_head_office_cannot_remove_built_in_or_another_companys_names(): void
    {
        [$tenant] = $this->pharmacy();
        $owner = $this->member($tenant, 'owner');
        $rival = Tenant::create(['name' => 'Rival Drug', 'slug' => 'rival-drug', 'status' => 'active']);
        $rivalName = IngredientAlias::query()->create(['tenant_id' => $rival->id, 'alias' => 'rival secret', 'ingredient' => 'Paracetamol']);
        $builtIn = IngredientAlias::query()->where('alias', 'acetaminophen')->sole();

        $this->actingAs($owner)->get(route('seller.ingredient-aliases.index'))
            ->assertOk()
            ->assertSee('acetaminophen')
            ->assertSee('Built in')
            ->assertDontSee('rival secret');
        $this->actingAs($owner)->delete(route('seller.ingredient-aliases.destroy', $rivalName))->assertNotFound();
        $this->actingAs($owner)->delete(route('seller.ingredient-aliases.destroy', $builtIn))->assertNotFound();

        $this->assertDatabaseHas('ingredient_aliases', ['id' => $rivalName->id]);
        $this->assertDatabaseHas('ingredient_aliases', ['id' => $builtIn->id]);
    }

    public function test_branch_people_cannot_change_other_names(): void
    {
        [$tenant, $branch] = $this->pharmacy();
        $alias = IngredientAlias::query()->where('alias', 'acetaminophen')->sole();

        foreach ([$this->member($tenant, 'manager', $branch), $this->member($tenant, 'staff', $branch)] as $branchMember) {
            $this->actingAs($branchMember)->get(route('seller.ingredient-aliases.index'))->assertForbidden();
            $this->actingAs($branchMember)->post(route('seller.ingredient-aliases.store'), ['alias' => 'tempra generic', 'ingredient' => 'Paracetamol'])->assertForbidden();
            $this->actingAs($branchMember)->delete(route('seller.ingredient-aliases.destroy', $alias))->assertForbidden();
        }

        $this->assertDatabaseMissing('ingredient_aliases', ['alias' => 'tempra generic']);
        $this->assertDatabaseHas('ingredient_aliases', ['alias' => 'acetaminophen']);
    }

    /**
     * @return array{0: Tenant, 1: Store}
     */
    private function pharmacy(): array
    {
        $tenant = Tenant::create(['name' => 'Botika Plus', 'slug' => 'botika-plus', 'status' => 'active']);
        $branch = Store::create(['tenant_id' => $tenant->id, 'name' => 'Botika Plus Makati', 'slug' => 'botika-plus-makati', 'phone' => '+639171234567', 'address_line' => '10 Rizal Street', 'city' => 'Makati', 'status' => 'active']);

        return [$tenant, $branch];
    }

    private function member(Tenant $tenant, string $role, ?Store $branch = null): User
    {
        $user = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $tenant->id, 'store_id' => $branch?->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }
}
