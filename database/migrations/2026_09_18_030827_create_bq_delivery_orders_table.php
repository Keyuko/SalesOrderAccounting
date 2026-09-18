<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bq_delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->string('company_code')->nullable();
            $table->string('business_area')->nullable();
            $table->string('delivery_order_number');
            $table->string('sales_order_number')->nullable();
            $table->date('estimated_goods_issue_date')->nullable();
            $table->date('actual_goods_issue_date')->nullable();
            $table->string('sold_to_party')->nullable();
            $table->string('ship_to_party')->nullable();
            $table->string('material_number')->nullable();
            $table->string('batch_number')->nullable();
            $table->string('storage_location')->nullable();
            $table->decimal('quantity', 15, 4)->nullable();
            $table->string('salesperson_number')->nullable();
            $table->decimal('cost_value_in_local_currency', 18, 4)->nullable();
            $table->string('profit_center')->nullable();
            $table->date('fbl3n_posting_date')->nullable();
            $table->decimal('goods_value_in_local_currency', 18, 4)->nullable();
            $table->timestamps();

            $table->index('delivery_order_number');
            $table->index('sales_order_number');
        });

        // Also remove foreign key constraint on sales_orders.quotation_id so we can insert SO without quotation
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('quotation_id')->nullable()->change();
        });

        // Remove foreign key constraint on delivery_orders.sales_order_id so we can insert DO without SO in some cases
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('sales_order_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bq_delivery_orders');
    }
};
