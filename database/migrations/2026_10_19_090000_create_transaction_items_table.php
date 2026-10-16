<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('financial_transactions')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('description', 255);
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('total', 18, 2);
            $table->timestamps();

            $table->index(['transaction_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
    }
};
