<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pillar weight may be null (no admin weight = equal share, same as 0). Existing values are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('readiness_pillars', function (Blueprint $table) {
            $table->decimal('weight', 8, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        DB::table('readiness_pillars')->whereNull('weight')->update(['weight' => 0]);
        Schema::table('readiness_pillars', function (Blueprint $table) {
            $table->decimal('weight', 8, 2)->default(0)->change();
        });
    }
};