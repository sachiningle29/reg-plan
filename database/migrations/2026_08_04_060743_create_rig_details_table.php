<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rig_details', function (Blueprint $table) {
            $table->id();

            $table->string('plan_name')->index();
            $table->enum('record_type', ['planned', 'actual'])->index();

            $table->string('rig_name')->nullable();
            $table->string('asset')->nullable();
            $table->string('platform')->nullable();
            $table->string('well_name')->nullable();
            $table->string('type')->nullable();
            $table->decimal('meters', 10, 2)->nullable();
            $table->string('wi_well')->nullable();
            $table->integer('days')->nullable();
            $table->string('status')->nullable();
            $table->string('activity')->nullable();
            $table->date('start_date')->nullable();
            $table->date('finish_date')->nullable();
            $table->decimal('oil_gain', 10, 2)->nullable();
            $table->decimal('gas_gain', 10, 2)->nullable();

            $table->foreignId('imported_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rig_details');
    }
};
