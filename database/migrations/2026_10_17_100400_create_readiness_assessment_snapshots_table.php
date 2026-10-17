<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frozen readiness results (QA): each computed result is stored with the full scorer output and a copy of the
 * rule set/criteria config used. Old rows are never rewritten; a recalculation adds a new row marked current.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readiness_assessment_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('readiness_assessment_id')->constrained('readiness_assessments')->cascadeOnDelete();
            $table->foreignId('enterprise_id')->constrained('enterprises')->cascadeOnDelete();
            $table->foreignId('readiness_rule_set_id')->constrained('readiness_rule_sets');
            $table->unsignedInteger('rule_set_version')->nullable();
            $table->string('config_hash', 64);
            $table->decimal('score', 5, 2)->nullable();
            $table->boolean('mandatory_conditions_met')->nullable();
            $table->string('band')->nullable();
            $table->json('evaluation');
            $table->json('config');
            $table->json('outcomes');
            $table->string('reason');
            $table->text('reason_note')->nullable();
            $table->boolean('is_current')->default(true);
            $table->foreignId('computed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('computed_at');
            $table->foreignId('previous_snapshot_id')->nullable()->constrained('readiness_assessment_snapshots')->nullOnDelete();
            $table->timestamps();
            $table->index(['readiness_assessment_id', 'is_current'], 'ras_assessment_current_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readiness_assessment_snapshots');
    }
};
