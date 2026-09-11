<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fraud_checker_settings', function (Blueprint $table) {
            $table->id();
            $table->text('api_key')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('cache_minutes')->default(60);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_checker_settings');
    }
};
