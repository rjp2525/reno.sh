<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catalog of sellable products, each mapped to a Prodigi SKU.
        Schema::create('print_products', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('type');
            $table->string('sku');
            $table->decimal('width_in', 6, 2);
            $table->decimal('height_in', 6, 2);
            $table->unsignedInteger('price');
            $table->string('currency', 3)->default('USD');
            $table->text('description')->nullable();
            // Extra Prodigi item attributes, e.g. {"color": "black"} for frames.
            $table->json('attributes')->nullable();
            // Required print resolution; a photo must have width_in*min_dpi pixels to be offered this size.
            $table->unsignedSmallInteger('min_dpi')->default(150);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('print_orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('number')->unique();
            $table->string('status')->default('pending_payment');
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('currency', 3);
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('shipping')->default(0);
            $table->unsignedInteger('tax')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->json('shipping_address')->nullable();
            $table->json('tracking')->nullable();
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('prodigi_order_id')->nullable()->unique();
            $table->text('error')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('print_order_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('print_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('photo_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('print_product_id')->nullable()->constrained()->nullOnDelete();
            // Snapshots so history survives catalog/photo edits.
            $table->string('photo_title');
            $table->string('product_name');
            $table->string('sku');
            $table->json('attributes')->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedInteger('unit_price');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_order_items');
        Schema::dropIfExists('print_orders');
        Schema::dropIfExists('print_products');
    }
};
