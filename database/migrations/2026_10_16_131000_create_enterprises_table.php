<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enterprises', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('business_name');
            $table->string('sector')->nullable();
            // TODO: BUSINESS DECISION REQUIRED — location validation currently allows only the documented programme areas (damascus, rural_damascus, daraa). If Beacon later needs locations outside these three, widen the allowed list.
            $table->string('location')->nullable();
            $table->date('founding_date')->nullable();
            $table->unsignedInteger('employee_count')->nullable();
            // TODO: BUSINESS DECISION REQUIRED — the contract requires gender-disaggregated information where required, but the exact breakdown is not defined. These two optional counts are reversible storage only and must not be presented as an official gender framework.
            $table->unsignedInteger('female_employee_count')->nullable();
            $table->unsignedInteger('male_employee_count')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('registration_status')->nullable();
            $table->text('initial_digital_readiness_note')->nullable();
            $table->text('initial_financial_readiness_note')->nullable();
            $table->string('programme_stage')->nullable();
            $table->text('progress_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('enterprise_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enterprise_id')->constrained('enterprises')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unique('user_id');
            $table->unique(['enterprise_id', 'user_id']);
            $table->timestamps();
        });

        Schema::create('advisor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enterprise_id')->constrained('enterprises')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['enterprise_id', 'user_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advisor_assignments');
        Schema::dropIfExists('enterprise_members');
        Schema::dropIfExists('enterprises');
    }
};
