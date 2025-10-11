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
        Schema::create('donors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // example.com, shop.ua и т.д.
            $table->string('base_url');
            $table->integer('rate_limit')->default(30); // лимит запросов в минуту
            $table->integer('delay_min')->default(1);   // минимальная задержка между запросами
            $table->integer('delay_max')->default(5);   // максимальная задержка
            $table->integer('refresh_interval')->default(60); // мин. интервал между повторными парсингами (в минутах)
            $table->string('user_agent')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donors');
    }
};
