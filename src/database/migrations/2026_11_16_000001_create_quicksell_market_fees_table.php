<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fees a user corrected for a market (the only thing Quick Sell stores permanently).
 * ESI does not tell the broker's fee of a structure, and standings change it in NPC stations.
 * Per user, because standings and the structure owner's fee for a corporation can differ between users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quicksell_market_fees', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->bigInteger('location_id');
            $table->decimal('broker_pct', 5, 2)->nullable();
            $table->decimal('scc_pct', 5, 2)->nullable();
            $table->decimal('safety_pct', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quicksell_market_fees');
    }
};
