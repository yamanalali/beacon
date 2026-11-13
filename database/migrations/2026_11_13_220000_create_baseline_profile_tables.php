<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baseline_profile_fields', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('excel_column', 8)->nullable()->index();
            $table->string('label_ar', 255);
            $table->string('section', 40)->index();
            $table->string('input_kind', 32)->default('text');
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_child')->default(false);
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('enterprise_baseline_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enterprise_id')->unique()->constrained('enterprises')->cascadeOnDelete();
            $table->string('external_mse_id', 64)->nullable()->index();
            $table->string('source_uuid', 64)->nullable()->index();
            $table->timestamp('submission_time')->nullable();
            $table->json('answers');
            $table->timestamp('imported_at')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('enterprises', function (Blueprint $table) {
            $table->string('address_line')->nullable()->after('contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('enterprises', function (Blueprint $table) {
            $table->dropColumn('address_line');
        });
        Schema::dropIfExists('enterprise_baseline_profiles');
        Schema::dropIfExists('baseline_profile_fields');
    }
};
