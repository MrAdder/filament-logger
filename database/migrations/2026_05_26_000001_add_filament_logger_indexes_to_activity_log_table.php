<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\ActivitylogServiceProvider;

/**
 * Optional indexes for the activity log table.
 *
 * The activity resource sorts by `created_at` and filters by `event` and
 * `created_at` on every page load. Spatie's own migration only indexes
 * `log_name` and the subject/causer morphs, so these two indexes are the
 * difference between a range scan and a full table scan once the audit trail
 * grows past a few hundred thousand rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = $this->tableName();

        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->index('created_at', 'activity_log_created_at_index');
            $blueprint->index(['event', 'created_at'], 'activity_log_event_created_at_index');
        });
    }

    public function down(): void
    {
        $table = $this->tableName();

        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropIndex('activity_log_created_at_index');
            $blueprint->dropIndex('activity_log_event_created_at_index');
        });
    }

    protected function tableName(): string
    {
        $model = ActivitylogServiceProvider::determineActivityModel();

        return (new $model)->getTable();
    }
};
