<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('enterprise_id')->constrained('enterprises')->cascadeOnDelete();
            $table->string('type', 20);
            $table->date('transaction_date');
            $table->decimal('amount', 18, 2);
            $table->char('currency', 3);
            $table->string('category', 60);
            $table->string('payment_status', 20);
            $table->string('counterparty', 150)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('posted');
            $table->foreignId('corrects_transaction_id')->nullable()->constrained('financial_transactions')->nullOnDelete();
            $table->text('correction_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['enterprise_id', 'status', 'transaction_date']);
            $table->index(['enterprise_id', 'type']);
        });

        // Cash movements against a transaction (payment received / paid).
        // A settlement never creates revenue or expense by itself, which
        // prevents double counting of credit sales and credit purchases.
        Schema::create('transaction_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_transaction_id')->constrained('financial_transactions')->cascadeOnDelete();
            $table->date('settlement_date');
            $table->decimal('amount', 18, 2);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['financial_transaction_id', 'settlement_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_settlements');
        Schema::dropIfExists('financial_transactions');
    }
};
