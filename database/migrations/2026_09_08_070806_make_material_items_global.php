<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Collapse to one row per item_name — items existed once per asset
        // before; now the same name across different assets becomes a
        // single shared row. Keep the earliest occurrence's id, renumber
        // sort_order cleanly, and drop the rest.
        $rows = DB::table('material_items')->orderBy('item_name')->orderBy('id')->get();
        $seen = [];
        $toDelete = [];
        $order = 0;

        foreach ($rows as $r) {
            if (!isset($seen[$r->item_name])) {
                $seen[$r->item_name] = $r->id;
                $order++;
                DB::table('material_items')->where('id', $r->id)->update(['sort_order' => $order]);
            } else {
                $toDelete[] = $r->id;
            }
        }

        if (!empty($toDelete)) {
            DB::table('material_items')->whereIn('id', $toDelete)->delete();
        }

        Schema::table('material_items', function (Blueprint $table) {
            $table->dropUnique(['asset_name', 'item_name']);
            $table->dropColumn('asset_name');
        });

        Schema::table('material_items', function (Blueprint $table) {
            $table->unique('item_name');
        });
    }

    public function down(): void
    {
        Schema::table('material_items', function (Blueprint $table) {
            $table->dropUnique(['item_name']);
            $table->string('asset_name')->nullable()->after('id');
        });
        // Note: original per-asset associations aren't recoverable — this
        // rollback restores the column structure only, not the old data split.
    }
};
