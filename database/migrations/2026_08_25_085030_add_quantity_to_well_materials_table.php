<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('well_materials', function (Blueprint $table) {
            $table->decimal('quantity', 12, 2)->nullable()->after('item_name');
        });
    }

    public function down(): void
    {
        Schema::table('well_materials', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
