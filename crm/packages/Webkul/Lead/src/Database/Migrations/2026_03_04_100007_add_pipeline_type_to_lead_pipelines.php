<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_pipelines', function (Blueprint $table) {
            $table->string('pipeline_type')->default('sales')->after('name'); // sales, delivery
            $table->index('pipeline_type');
        });

        // Set existing pipelines to 'sales' type
        DB::table('lead_pipelines')->update(['pipeline_type' => 'sales']);
    }

    public function down(): void
    {
        Schema::table('lead_pipelines', function (Blueprint $table) {
            $table->dropIndex(['pipeline_type']);
            $table->dropColumn('pipeline_type');
        });
    }
};
