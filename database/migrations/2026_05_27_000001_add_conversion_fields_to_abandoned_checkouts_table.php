<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('abandoned_checkouts', function (Blueprint $table) {
            $table->foreignId('converted_order_id')
                ->nullable()
                ->after('status')
                ->constrained('orders')
                ->nullOnDelete();
            $table->timestamp('converted_at')->nullable()->after('converted_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('abandoned_checkouts', function (Blueprint $table) {
            $table->dropForeign(['converted_order_id']);
            $table->dropColumn(['converted_order_id', 'converted_at']);
        });
    }
};
