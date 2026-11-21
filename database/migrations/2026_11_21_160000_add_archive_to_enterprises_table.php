<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enterprises', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('progress_summary');
            $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
            $table->string('archive_reason')->nullable()->after('archived_by');
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        // SQLite rebuilds the table on dropColumn; drop index/FK in separate steps first.
        Schema::table('enterprises', function (Blueprint $table) {
            $table->dropForeign(['archived_by']);
        });

        Schema::table('enterprises', function (Blueprint $table) {
            $table->dropIndex('enterprises_archived_at_index');
        });

        Schema::table('enterprises', function (Blueprint $table) {
            $table->dropColumn(['archived_at', 'archived_by', 'archive_reason']);
        });
    }
};
