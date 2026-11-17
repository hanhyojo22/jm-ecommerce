<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * A prescription is usually several pages, so the single file on the prescription becomes an
     * ordered set of images. Existing prescriptions keep their file as the first image.
     */
    public function up(): void
    {
        Schema::create('prescription_images', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('prescription_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->index(['prescription_id', 'position']);
        });

        DB::table('prescriptions')
            ->select(['id', 'path', 'original_name', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunk(200, function ($prescriptions): void {
                $images = [];

                foreach ($prescriptions as $prescription) {
                    $images[] = [
                        'id' => (string) Str::ulid(),
                        'prescription_id' => $prescription->id,
                        'path' => $prescription->path,
                        'original_name' => $prescription->original_name,
                        'position' => 0,
                        'created_at' => $prescription->created_at,
                        'updated_at' => $prescription->updated_at,
                    ];
                }

                DB::table('prescription_images')->insert($images);
            });

        Schema::table('prescriptions', function (Blueprint $table): void {
            $table->dropColumn(['path', 'original_name']);
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table): void {
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
        });

        DB::table('prescription_images')
            ->where('position', 0)
            ->orderBy('prescription_id')
            ->each(function ($image): void {
                DB::table('prescriptions')->where('id', $image->prescription_id)->update([
                    'path' => $image->path,
                    'original_name' => $image->original_name,
                ]);
            });

        Schema::dropIfExists('prescription_images');
    }
};
