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
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->onDelete('cascade');
            $table->string('do_number')->unique();
            $table->date('delivery_date');
            $table->string('location');
            $table->text('notes')->nullable();
            $table->string('vehicle_info')->nullable(); // For Security to monitor
            $table->string('ppic_status')->default('pending'); // pending, approved, rejected
            $table->text('ppic_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_orders');
    }
};
