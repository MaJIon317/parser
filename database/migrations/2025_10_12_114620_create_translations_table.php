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
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('hash', 32)->index(); // md5(source)
            $table->text('source'); // оригинальный текст
            $table->text('target'); // перевод
            $table->string('from_lang', 8);
            $table->string('to_lang', 8);
            $table->timestamps();

            $table->unique(['hash', 'from_lang', 'to_lang'], 'translations_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
