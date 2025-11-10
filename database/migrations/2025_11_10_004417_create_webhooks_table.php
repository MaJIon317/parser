<?php

use App\Models\Product;
use App\Models\Webhook;
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
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->string('secret');
            $table->string('locale');
            $table->unsignedBigInteger('currency_id');
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('cascade');
        });

        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('webhook_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('message');
            $table->json('data')->nullable();
            $table->timestamps();

            $table->foreign('webhook_id')->references('id')->on('webhooks')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
