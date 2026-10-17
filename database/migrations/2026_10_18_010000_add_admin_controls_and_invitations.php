<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->char('code', 3)->primary();
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
        foreach (config('bookkeeping.currencies', []) as $code => $name) {
            DB::table('currencies')->insert(['code' => $code, 'name' => $name, 'enabled' => true, 'is_default' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::table('users', fn (Blueprint $table) => $table->json('access_permissions')->nullable());
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->uuid('client_draft_id')->nullable();
            $table->char('client_draft_hash', 64)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unique(['created_by', 'client_draft_id'], 'ft_creator_draft_unique');
        });
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name');
            $table->string('role', 32);
            $table->char('token_hash', 64)->unique();
            $table->foreignId('enterprise_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('mfi_institution_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invitations');
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropUnique('ft_creator_draft_unique');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['client_draft_id', 'client_draft_hash', 'approved_at']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('access_permissions'));
        Schema::dropIfExists('currencies');
    }
};
