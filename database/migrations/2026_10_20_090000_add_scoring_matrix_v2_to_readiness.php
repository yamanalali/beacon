<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scoring matrix v2 (additive only). Existing criteria stay pillar-less (implicit pillar)
 * and become type "boolean". No business weights/thresholds are seeded here:
 * TODO: BUSINESS DECISION REQUIRED (pillars, weights, min scores and bands are entered by Beacon).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readiness_pillars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('readiness_rule_set_id')->constrained('readiness_rule_sets')->cascadeOnDelete();
            $table->string('code');
            $table->string('label_ar');
            $table->decimal('weight', 8, 2)->default(0);
            $table->decimal('min_score', 5, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['readiness_rule_set_id', 'code']);
        });

        Schema::table('readiness_criteria', function (Blueprint $table) {
            $table->foreignId('readiness_pillar_id')->nullable()->constrained('readiness_pillars')->nullOnDelete();
            $table->string('type')->default('boolean');
            $table->json('config')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
        });

        Schema::create('readiness_score_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('readiness_rule_set_id')->constrained('readiness_rule_sets')->cascadeOnDelete();
            $table->string('label_ar');
            $table->decimal('min', 5, 2);
            $table->decimal('max', 5, 2);
            $table->string('color_token');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('readiness_assessment_results', function (Blueprint $table) {
            $table->json('value')->nullable();
            $table->decimal('points', 5, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('readiness_assessment_results', function (Blueprint $table) {
            $table->dropColumn(['value', 'points']);
        });

        Schema::dropIfExists('readiness_score_bands');

        Schema::table('readiness_criteria', function (Blueprint $table) {
            $table->dropConstrainedForeignId('readiness_pillar_id');
            $table->dropColumn(['type', 'config', 'sort_order']);
        });

        Schema::dropIfExists('readiness_pillars');
    }
};
