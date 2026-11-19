<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Another name for an active ingredient, e.g. "vitamin c" for Ascorbic Acid. Catalog search
 * expands a term to its ingredient and every alias of it. A null tenant marks a built-in name.
 *
 * shortcut: search uses every company's names, so one company's name also widens search for
 * another's products; scope expansion per company if a second pharmacy company is ever set up.
 */
class IngredientAlias extends Model
{
    protected $fillable = ['tenant_id', 'alias', 'ingredient'];

    public function isBuiltIn(): bool
    {
        return $this->tenant_id === null;
    }
}
