<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_escalations', function (Blueprint $table) {
            $table->text('staff_reply')->nullable()->after('status');
            $table->foreignId('replied_by')->nullable()->after('staff_reply')->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at')->nullable()->after('replied_by');
        });
    }

    public function down(): void
    {
        Schema::table('ai_escalations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replied_by');
            $table->dropColumn(['staff_reply', 'replied_at']);
        });
    }
};
