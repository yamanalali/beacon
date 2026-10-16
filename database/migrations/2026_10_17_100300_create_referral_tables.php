<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('enterprise_id')->constrained('enterprises')->cascadeOnDelete();
            $table->foreignId('mfi_institution_id')->constrained('mfi_institutions');
            $table->foreignId('readiness_assessment_id')->nullable()->constrained('readiness_assessments')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->text('purpose_ar');
            $table->json('disclosed_items');
            $table->text('info_request_note')->nullable();
            $table->text('decision_note')->nullable();
            $table->text('outcome_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('shared_at')->nullable();
            $table->timestamps();
        });

        Schema::create('referral_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained('referrals')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('note')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('referral_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained('referrals')->cascadeOnDelete();
            $table->foreignId('enterprise_id')->constrained('enterprises')->cascadeOnDelete();
            $table->foreignId('mfi_institution_id')->constrained('mfi_institutions');
            $table->text('purpose_ar');
            $table->string('consent_version');
            $table->text('consent_text_ar');
            $table->json('scope');
            $table->string('status')->default('granted');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at');
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_consents');
        Schema::dropIfExists('referral_status_changes');
        Schema::dropIfExists('referrals');
    }
};
