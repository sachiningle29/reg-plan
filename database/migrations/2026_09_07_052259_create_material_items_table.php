<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_items', function (Blueprint $table) {
            $table->id();
            $table->string('asset_name');
            $table->string('item_name');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['asset_name', 'item_name']);
        });

        // Backfill from whatever item names already exist in well_materials,
        // so your 964 imported NH rows immediately have a matching master
        // list — nothing needs re-importing.
        $rows = DB::table('well_materials')
            ->select('asset_name', 'item_name')->distinct()
            ->orderBy('asset_name')->orderBy('item_name')
            ->get();

        $order = [];
        $now = now();
        $insert = [];
        foreach ($rows as $r) {
            $order[$r->asset_name] = ($order[$r->asset_name] ?? 0) + 1;
            $insert[] = [
                'asset_name' => $r->asset_name,
                'item_name'  => $r->item_name,
                'sort_order' => $order[$r->asset_name],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($insert) {
            DB::table('material_items')->insert($insert);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('material_items');
    }
};
