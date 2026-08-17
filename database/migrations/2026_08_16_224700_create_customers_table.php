<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        if (Schema::hasTable('orders')) {
            $now = now();
            $existingPhones = [];

            DB::table('orders')
                ->select('phone', 'name')
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->orderByDesc('id')
                ->chunk(500, function ($orders) use ($now, &$existingPhones) {
                    $rows = [];

                    foreach ($orders as $order) {
                        $phone = trim((string) $order->phone);
                        if ($phone === '' || isset($existingPhones[$phone])) {
                            continue;
                        }

                        $existingPhones[$phone] = true;
                        $rows[] = [
                            'name' => $order->name ?: 'Unknown',
                            'phone' => $phone,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if ($rows) {
                        DB::table('customers')->insertOrIgnore($rows);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
