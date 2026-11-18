<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Another name for an active ingredient, e.g. "vitamin c" for Ascorbic Acid. Catalog search
 * expands a term to its ingredient and every alias of it.
 */
class IngredientAlias extends Model
{
    protected $fillable = ['alias', 'ingredient'];
}
