<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readiness_rule_sets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version_number')->unique();
            $table->string('name_ar');
            $table->string('status')->default('draft');
            $table->text('change_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('readiness_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('readiness_rule_set_id')->constrained('readiness_rule_sets')->cascadeOnDelete();
            $table->string('code');
            $table->string('label_ar');
            $table->text('description_ar')->nullable();
            $table->decimal('weight', 8, 2)->default(0);
            $table->boolean('is_mandatory')->default(false);
            $table->string('missing_data_treatment');
            $table->foreignId('compliance_requirement_id')->nullable()->constrained('compliance_requirements')->nullOnDelete();
            $table->timestamps();
            $table->unique(['readiness_rule_set_id', 'code']);
        });

        Schema::create('readiness_assessments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('enterprise_id')->constrained('enterprises')->cascadeOnDelete();
            $table->foreignId('readiness_rule_set_id')->constrained('readiness_rule_sets');
            $table->string('status')->default('draft');
            $table->decimal('score', 5, 2)->nullable();
            $table->boolean('mandatory_conditions_met')->nullable();
            $table->json('summary')->nullable();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });

        Schema::create('readiness_assessment_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('readiness_assessment_id')->constrained('readiness_assessments')->cascadeOnDelete();
            $table->foreignId('readiness_criterion_id')->constrained('readiness_criteria');
            $table->foreignId('compliance_requirement_version_id')->nullable()->constrained('compliance_requirement_versions');
            $table->string('outcome')->default('missing');
            $table->text('note')->nullable();
            $table->text('action_needed')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('target_date')->nullable();
            $table->timestamps();
            $table->unique(['readiness_assessment_id', 'readiness_criterion_id'], 'rar_assessment_criterion_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readiness_assessment_results');
        Schema::dropIfExists('readiness_assessments');
        Schema::dropIfExists('readiness_criteria');
        Schema::dropIfExists('readiness_rule_sets');
    }
};
