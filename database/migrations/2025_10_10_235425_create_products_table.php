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
            $table->json('images')->nullable();
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


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
