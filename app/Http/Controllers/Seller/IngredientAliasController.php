<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\IngredientAlias;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Other names for an active ingredient ("vitamin c" for Ascorbic Acid). Catalog search expands a
 * term to the ingredient and every other name of it, so one entry reaches every brand. Head office
 * keeps the list.
 */
class IngredientAliasController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $this->headOffice($request);

        return view('seller.ingredient-aliases.index', [
            // The built-in names and this company's own; another company's names stay private.
            'aliasesByIngredient' => IngredientAlias::query()
                ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
                ->orderBy('ingredient')->orderBy('alias')->get()->groupBy('ingredient'),
            // Offered while typing, so the ingredient is spelled the way the products already spell it.
            'genericNames' => $tenant->products()->whereNotNull('generic_name')->where('generic_name', '!=', '')->distinct()->orderBy('generic_name')->pluck('generic_name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->headOffice($request);
        $data = $request->validate([
            'alias' => ['required', 'string', 'max:100'],
            'ingredient' => ['required', 'string', 'max:160'],
        ]);
        $alias = preg_replace('/\s+/u', ' ', Str::lower(trim($data['alias'])));
        $ingredient = preg_replace('/\s+/u', ' ', trim($data['ingredient']));

        if ($alias === Str::lower($ingredient)) {
            throw ValidationException::withMessages(['alias' => 'The other name has to differ from the ingredient.']);
        }
        if (IngredientAlias::query()->where('alias', $alias)->exists()) {
            throw ValidationException::withMessages(['alias' => "“{$alias}” is already listed."]);
        }

        $entry = IngredientAlias::query()->create(['tenant_id' => $tenant->id, 'alias' => $alias, 'ingredient' => $ingredient]);
        AuditLog::query()->create([
            'actor_user_id' => $request->user()->id,
            'action' => 'ingredient_alias.added',
            'entity_type' => IngredientAlias::class,
            'entity_id' => (string) $entry->id,
            'new_values' => ['alias' => $alias, 'ingredient' => $ingredient],
            'ip_address' => $request->ip(),
        ]);

        return to_route('seller.ingredient-aliases.index')->with('status', "Searching “{$alias}” now also finds {$ingredient}.");
    }

    public function destroy(Request $request, IngredientAlias $ingredientAlias): RedirectResponse
    {
        $tenant = $this->headOffice($request);
        // Only this company's own names; a built-in or another company's name is a 404.
        abort_unless($ingredientAlias->tenant_id !== null && $ingredientAlias->tenant_id === $tenant->id, 404);
        $ingredientAlias->delete();
        AuditLog::query()->create([
            'actor_user_id' => $request->user()->id,
            'action' => 'ingredient_alias.removed',
            'entity_type' => IngredientAlias::class,
            'entity_id' => (string) $ingredientAlias->id,
            'old_values' => $ingredientAlias->only(['alias', 'ingredient']),
            'ip_address' => $request->ip(),
        ]);

        return to_route('seller.ingredient-aliases.index')->with('status', "Removed “{$ingredientAlias->alias}”.");
    }

    private function headOffice(Request $request): Tenant
    {
        return $request->user()->headOfficeTenant() ?? abort(403);
    }
}
