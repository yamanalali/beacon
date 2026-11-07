<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('signature_path')->nullable()->after('locale');
            $table->timestamp('signature_skipped_at')->nullable()->after('signature_path');
        });

        Schema::create('registration_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('business_name');
            $table->string('city')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_applications');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['signature_path', 'signature_skipped_at']);
        });
    }
};
