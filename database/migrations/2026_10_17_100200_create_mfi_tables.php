<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // TODO: BUSINESS DECISION REQUIRED - confirm the administrative fields needed for an MFI/institution record (section 27).
        Schema::create('mfi_institutions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name_ar');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('mfi_eligibility_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mfi_institution_id')->constrained('mfi_institutions')->cascadeOnDelete();
            $table->string('label_ar');
            $table->string('type');
            $table->json('parameters')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('mfi_institution_id')->nullable()->after('role')->constrained('mfi_institutions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mfi_institution_id');
        });
        Schema::dropIfExists('mfi_eligibility_criteria');
        Schema::dropIfExists('mfi_institutions');
    }
};
