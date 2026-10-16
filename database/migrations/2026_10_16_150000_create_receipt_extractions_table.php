<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Suggested (non-authoritative) values extracted from a receipt.
        Schema::create('receipt_extractions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('enterprise_id')->constrained('enterprises')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('status', 20)->default('pending_review');
            $table->string('extractor', 50);
            $table->string('categorizer', 50)->nullable();
            $table->date('suggested_date')->nullable();
            $table->decimal('suggested_amount', 18, 2)->nullable();
            $table->char('suggested_currency', 3)->nullable();
            $table->string('suggested_counterparty', 150)->nullable();
            $table->string('suggested_category', 60)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->boolean('needs_review')->default(true);
            $table->foreignId('confirmed_transaction_id')->nullable()->constrained('financial_transactions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['enterprise_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_extractions');
    }
};
