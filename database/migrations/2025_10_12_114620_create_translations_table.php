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
        Schema::create('translations', function (Blueprint $table) { // Переводы найденных атрибутов на язык по-умолчанию
            $table->id();
            $table->string('hash', 32)->unique()->index(); // md5(source)
            $table->string('lang', 8); // Надо понимать на каком языке исходный текст
            $table->text('source'); // оригинальный текст
            $table->text('target')->nullable(); // перевод на язык по-умолчанию
            $table->string('target_hash')->nullable()->index(); // md5(source)
            $table->string('target_text')->nullable()->index(); // Для поиска
            $table->unsignedBigInteger('canonical_id')->nullable()->index(); // 🔗 Каноническая ссылка на основную запись
            $table->timestamps();

            $table->foreign('canonical_id')->references('id')->on('translations')->onDelete('set null'); // если удалён canonical, ссылка обнуляется
        });

        // Переводы найденных атрибутов на язык по-умолчанию
        Schema::create('translation_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('translation_id');
            $table->string('lang', 8);
            $table->text('text'); // Перевод
            $table->timestamps();

            $table->foreign('translation_id')->on('translations')->references('id')->onDelete('cascade');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translation_variants');
        Schema::dropIfExists('translations');
    }
};
