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
        Schema::create('loggings', function (Blueprint $table) {
            $table->id();

            // Тип операции (например, "donor_pages", "product_detail")
            $table->string('type')->index();

            // Какой донор (сайт)
            $table->unsignedBigInteger('donor_id')->nullable();

            // Какой товар (если лог парсинга конкретного товара)
            $table->unsignedBigInteger('product_id')->nullable();

            // URL, который парсили
            $table->text('url')->nullable();

            // Класс парсера, например "WatchnianComParser"
            $table->string('parser_class')->nullable();

            // Статус выполнения: success / error / warning / running
            $table->string('status')->default('pending')->index();

            // Сообщение (краткое описание или ошибка)
            $table->string('message')->nullable();

            // Детали (JSON) — можно хранить результат, ошибки, статистику и т.п.
            $table->json('context')->nullable();

            // Время начала / завершения
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            // Сколько миллисекунд заняло выполнение
            $table->integer('duration_ms')->nullable();

            $table->uuid('job_uuid')->nullable()->index();
            $table->string('queue')->nullable();

            $table->timestamps();

            $table->foreign('donor_id')->references('id')->on('donors')->onDelete('set null');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loggings');
    }
};
