<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Database-level protection: audit history cannot be edited or deleted,
 * even outside the application models. Only actor_id may be nulled
 * (FK nullOnDelete) so the FK definition keeps working.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER audit_logs_no_update BEFORE UPDATE OF action, target_type, target_id, context, created_at ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit history is immutable'); END;");
            DB::unprepared("CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit history is immutable'); END;");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::unprepared("CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs FOR EACH ROW BEGIN IF NOT (NEW.action <=> OLD.action AND NEW.target_type <=> OLD.target_type AND NEW.target_id <=> OLD.target_id AND NEW.context <=> OLD.context AND NEW.created_at <=> OLD.created_at) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit history is immutable'; END IF; END");
            DB::unprepared("CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit history is immutable'; END");
        }

        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function ($table) {
                $table->index(['action', 'created_at']);
                $table->index(['actor_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete');

        Schema::table('audit_logs', function ($table) {
            $table->dropIndex(['action', 'created_at']);
            $table->dropIndex(['actor_id', 'created_at']);
        });
    }
};
