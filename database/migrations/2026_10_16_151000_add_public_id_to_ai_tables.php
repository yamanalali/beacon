<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['knowledge_articles', 'ai_escalations'] as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->ulid('public_id')->nullable()->after('id'));
            DB::table($t)->whereNull('public_id')->orderBy('id')->each(
                fn ($row) => DB::table($t)->where('id', $row->id)->update(['public_id' => (string) Str::ulid()])
            );
            Schema::table($t, fn (Blueprint $table) => $table->unique('public_id'));
        }
    }

    public function down(): void
    {
        foreach (['knowledge_articles', 'ai_escalations'] as $t) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                $table->dropUnique($t.'_public_id_unique');
                $table->dropColumn('public_id');
            });
        }
    }
};
