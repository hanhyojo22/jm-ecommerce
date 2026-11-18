<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Other names shoppers type for an active ingredient (lay names, US names, salts), so a search
     * for any of them also reaches products whose generic name uses another. `alias` is stored
     * lower-case; `ingredient` is the display name of the ingredient it belongs to.
     */
    public function up(): void
    {
        Schema::create('ingredient_aliases', function (Blueprint $table): void {
            $table->id();
            $table->string('alias')->unique();
            $table->string('ingredient')->index();
            $table->timestamps();
        });

        $now = now();
        DB::table('ingredient_aliases')->insert(collect([
            'vitamin c' => 'Ascorbic Acid',
            'vit c' => 'Ascorbic Acid',
            'sodium ascorbate' => 'Ascorbic Acid',
            'calcium ascorbate' => 'Ascorbic Acid',
            'acetaminophen' => 'Paracetamol',
            'albuterol' => 'Salbutamol',
            'vitamin b1' => 'Thiamine',
            'vitamin d3' => 'Cholecalciferol',
        ])->map(fn (string $ingredient, string $alias): array => [
            'alias' => $alias,
            'ingredient' => $ingredient,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_aliases');
    }
};
