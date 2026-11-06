<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enterprise_compliance_record_id')
                ->constrained('enterprise_compliance_records', indexName: 'cs_record_foreign')
                ->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('submitted_at');
            $table->string('review_status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['enterprise_compliance_record_id', 'submitted_at'], 'cs_record_submitted_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_submissions');
    }
};
