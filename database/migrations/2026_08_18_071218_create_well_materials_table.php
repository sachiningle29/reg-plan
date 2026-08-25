<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('well_materials', function (Blueprint $table) {
            $table->id();
            $table->string('asset_name');
            $table->string('well_name');
            $table->enum('hptp', ['HPTP', 'Non-HPTP'])->default('Non-HPTP');
            $table->string('item_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('well_materials');
    }
};
