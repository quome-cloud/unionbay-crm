<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->string('history_import_status')->nullable()->after('last_error'); // pending, in_progress, completed, failed
            $table->integer('history_import_days')->nullable()->after('history_import_status');
            $table->integer('history_import_total')->default(0)->after('history_import_days');
            $table->integer('history_import_processed')->default(0)->after('history_import_total');
            $table->timestamp('history_import_started_at')->nullable()->after('history_import_processed');
            $table->timestamp('history_import_completed_at')->nullable()->after('history_import_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'history_import_status',
                'history_import_days',
                'history_import_total',
                'history_import_processed',
                'history_import_started_at',
                'history_import_completed_at',
            ]);
        });
    }
};
