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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // example.com, shop.ua и т.д.
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('donor_id'); // Указываем идентификатор проекта, с которого парсим
            $table->unsignedBigInteger('category_id'); // Категория
            $table->string('code')->index(); // Уникальный код внутри донора, по которому находим товар
            $table->string('url')->nullable();
            $table->decimal('price', 20, 8)->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->json('detail')->nullable();
            $table->string('parsing_status')->default('new');
            $table->string('status')->default('active');

            $table->timestamp('last_parsing')->nullable();
            $table->json('errors')->nullable();

            $table->timestamps();

            $table->foreign('donor_id')->references('id')->on('donors')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('set null');

            $table->unique(['donor_id', 'code']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('url')->index(); // Ссылки на изображение
            $table->json('correct_url')->nullable(); // Ссылки на изображение
            $table->json('hashed')->nullable(); // Хэши запросов
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });

        Schema::create('product_logs', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->index();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->morphs('model');
            $table->enum('type', ['success', 'info', 'error'])->default('info');
            $table->string('code')->index();
            $table->string('message');
            $table->json('data')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
