<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_extractions', function (Blueprint $table) {
            $table->string('suggested_transaction_type', 20)->nullable()->after('suggested_counterparty');
        });
    }

    public function down(): void
    {
        Schema::table('receipt_extractions', function (Blueprint $table) {
            $table->dropColumn('suggested_transaction_type');
        });
    }
};
