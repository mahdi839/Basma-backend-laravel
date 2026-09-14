<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

require_once __DIR__.'/helpers/ensure_product_variants_matrix.php';

/**
 * Rebuilds product_variants as the colour x size stock matrix.
 *
 * The previous table was attribute/value shaped (EAV) and cannot express a
 * combination. It is RENAMED rather than dropped, so no row is ever lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        eyara_ensure_product_variants_matrix();
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');

        if (Schema::hasTable('product_variants_legacy')) {
            Schema::rename('product_variants_legacy', 'product_variants');
        }
    }
};
