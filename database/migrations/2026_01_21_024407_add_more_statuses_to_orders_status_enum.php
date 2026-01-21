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
        Schema::table('orders', function (Blueprint $table) {
                 $table->enum('status', [
                'pending',
                'placed',
                'delivered',
                'cancel',
                'completed',
                'cancelled',
                'processing',
                'returned',
                'first_call',
                'second_call',
                'third_call',
                'stock_sold',
                'shipped_to_you',
                'received_in_bd',
                'order_sent_to_china',
                'file_completed',
                'order_confirmed'
            ])->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'placed',
                'delivered',
                'cancel'
            ])->default('pending')->change();
        });
    }
};
