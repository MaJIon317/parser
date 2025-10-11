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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('donor_id'); // Указываем идентификатор проекта, с которого парсим
            $table->string('code')->index(); // Уникальный код внутри донора, по которому находим товар
            $table->string('url')->nullable();
            $table->string('name')->nullable();
            $table->json('data')->nullable();
            $table->json('images')->nullable();
            $table->string('parsing_status')->default('new');
            $table->string('status')->default('active');

            $table->timestamp('last_parsing')->nullable();

            $table->timestamps();

            $table->foreign('donor_id')->references('id')->on('donors')->onDelete('cascade');
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
