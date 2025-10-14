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
            $table->unsignedBigInteger('currency_id')->nullable();

            $table->string('name')->unique(); // example.com, shop.ua и т.д.
            $table->string('code');
            $table->integer('rate_limit')->default(30); // лимит запросов в минуту
            $table->integer('delay_min')->default(1);   // минимальная задержка между запросами
            $table->integer('delay_max')->default(5);   // максимальная задержка
            $table->integer('refresh_interval')->default(3600); // мин. интервал между полными повторными парсингами (в минутах)
            $table->integer('refresh_interval_sale')->default(720); // мин. интервал между полными повторными парсингами цен, остатков и тд (в минутах)
            $table->boolean('is_active')->default(true);
            $table->json('setting')->nullable(); // Настройки парсинга
            $table->timestamps();

            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('cascade');
        });

        Schema::create('donor_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('donor_id');
            $table->string('type')->default('info'); // ℹ️ Тип записи: запуск, ошибка, успех, завершение и т.д.
            $table->text('message')->nullable(); // 📝 Основное сообщение
            $table->json('context')->nullable(); // 🧩 Дополнительные данные (например, URL, stacktrace, json-данные и т.п.)
            $table->timestamps();

            $table->foreign('donor_id')->references('id')->on('donors')->onDelete('cascade');

            // Индексы для быстрого поиска
            $table->index(['donor_id', 'type']);
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
