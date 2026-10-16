<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarantees idempotent scheduled runs: no duplicate reminders.
        Schema::create('reminder_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('reminder_type', 50);
            $table->foreignId('enterprise_compliance_record_id')->constrained('enterprise_compliance_records')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('due_date');
            $table->timestamp('created_at')->nullable();
            $table->unique(['reminder_type', 'enterprise_compliance_record_id', 'user_id', 'due_date'], 'reminder_dispatch_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_dispatches');
    }
};
