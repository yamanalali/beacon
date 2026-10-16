<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OCR draft only (suggestions shown on the review screen). The confirmed items are stored
 * by the bookkeeping side in transaction_items, never here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_extractions', function (Blueprint $table) {
            $table->json('suggested_items')->nullable()->after('suggested_counterparty');
            $table->string('suggested_invoice_number', 50)->nullable()->after('suggested_items');
        });
    }

    public function down(): void
    {
        Schema::table('receipt_extractions', function (Blueprint $table) {
            $table->dropColumn(['suggested_items', 'suggested_invoice_number']);
        });
    }
};
