<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v0.4.0: every fee is entered by hand and the last values are kept per user and market, so the sales tax and
 * the reprocessing settings are stored too. Sales tax has three decimals (3.375% at Accounting V).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quicksell_market_fees', function (Blueprint $table) {
            $table->decimal('sales_tax_pct', 6, 3)->nullable()->after('location_id');
            $table->decimal('reprocess_tax_pct', 5, 2)->nullable();
            $table->decimal('reprocess_ore_pct', 5, 2)->nullable();
            $table->decimal('reprocess_scrap_pct', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quicksell_market_fees', function (Blueprint $table) {
            $table->dropColumn(['sales_tax_pct', 'reprocess_tax_pct', 'reprocess_ore_pct', 'reprocess_scrap_pct']);
        });
    }
};
