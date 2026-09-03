<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Widen from enum to a plain string first — this lets both the old
        // and new spelling coexist during the rename, without needing the
        // doctrine/dbal package that Schema::table()->change() would require.
        DB::statement("ALTER TABLE well_materials MODIFY hptp VARCHAR(20) NOT NULL DEFAULT 'Non-HPHT'");

        DB::table('well_materials')->where('hptp', 'HPTP')->update(['hptp' => 'HPHT']);
        DB::table('well_materials')->where('hptp', 'Non-HPTP')->update(['hptp' => 'Non-HPHT']);
    }

    public function down(): void
    {
        DB::table('well_materials')->where('hptp', 'HPHT')->update(['hptp' => 'HPTP']);
        DB::table('well_materials')->where('hptp', 'Non-HPHT')->update(['hptp' => 'Non-HPTP']);

        DB::statement("ALTER TABLE well_materials MODIFY hptp ENUM('HPTP','Non-HPTP') NOT NULL DEFAULT 'Non-HPTP'");
    }
};
