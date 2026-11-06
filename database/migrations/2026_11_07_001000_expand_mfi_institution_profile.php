<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expand the MFI directory record beyond name/notes.
 *
 * TODO: BUSINESS DECISION REQUIRED — Beacon must confirm the final administrative
 * fields for an MFI/institution record (product §27). Fields below are directory /
 * contact / coverage metadata only; they do not invent eligibility rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mfi_institutions', function (Blueprint $table) {
            $table->string('code', 64)->nullable()->after('name_ar');
            $table->string('name_en')->nullable()->after('code');
            $table->string('contact_name')->nullable()->after('name_en');
            $table->string('contact_phone', 40)->nullable()->after('contact_name');
            $table->string('contact_email')->nullable()->after('contact_phone');
            $table->string('region_ar', 120)->nullable()->after('contact_email');
            $table->string('address_ar')->nullable()->after('region_ar');
            $table->string('website')->nullable()->after('address_ar');
            $table->text('focus_ar')->nullable()->after('website');
            $table->decimal('loan_size_min', 18, 2)->nullable()->after('focus_ar');
            $table->decimal('loan_size_max', 18, 2)->nullable()->after('loan_size_min');
            $table->string('loan_currency', 3)->nullable()->after('loan_size_max');
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('mfi_institutions', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn([
                'code',
                'name_en',
                'contact_name',
                'contact_phone',
                'contact_email',
                'region_ar',
                'address_ar',
                'website',
                'focus_ar',
                'loan_size_min',
                'loan_size_max',
                'loan_currency',
            ]);
        });
    }
};
