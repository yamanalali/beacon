<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('operation_key');
            $table->string('fingerprint', 64);
            $table->text('result_url')->nullable();
            $table->json('versions');
            $table->timestamp('created_at');
            $table->unique(['user_id', 'operation_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_operations');
    }
};
