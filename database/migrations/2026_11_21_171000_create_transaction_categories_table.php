<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-managed transaction categories. Transactions keep the category key as text,
        // so deactivating a category never changes historical records.
        Schema::create('transaction_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('label_ar', 120);
            $table->string('label_en', 120)->nullable();
            // Transaction type this category belongs to (sale, income, purchase, expense); null = every type.
            $table->string('type_scope', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'type_scope', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_categories');
    }
};
