<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_requirements', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code')->unique();
            $table->string('category');
            $table->boolean('is_active')->default(true);
            $table->foreignId('replaced_by_id')->nullable()->constrained('compliance_requirements')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('compliance_requirement_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compliance_requirement_id')->constrained('compliance_requirements', indexName: 'crv_requirement_foreign')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('title_ar');
            $table->text('description_ar')->nullable();
            // NULL guidance means "pending Beacon input" (section 23).
            $table->text('guidance_ar')->nullable();
            $table->boolean('requires_document')->default(false);
            $table->text('deadline_note_ar')->nullable();
            $table->date('effective_from')->nullable();
            $table->string('status')->default('draft');
            $table->text('change_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['compliance_requirement_id', 'version_number'], 'crv_req_version_unique');
        });

        Schema::create('enterprise_compliance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enterprise_id')->constrained('enterprises')->cascadeOnDelete();
            $table->foreignId('compliance_requirement_id')->constrained('compliance_requirements')->cascadeOnDelete();
            $table->foreignId('compliance_requirement_version_id')->constrained('compliance_requirement_versions', indexName: 'ecr_version_foreign');
            $table->string('status')->default('not_started');
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['enterprise_id', 'compliance_requirement_id'], 'ecr_enterprise_req_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enterprise_compliance_records');
        Schema::dropIfExists('compliance_requirement_versions');
        Schema::dropIfExists('compliance_requirements');
    }
};
