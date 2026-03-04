<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_definitions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('entity_type'); // leads, contacts, activities, products
            $table->json('columns');       // array of column definitions
            $table->json('filters')->nullable();   // array of filter conditions
            $table->string('group_by')->nullable(); // column to group by
            $table->string('sort_by')->nullable();  // column to sort by
            $table->string('sort_order')->default('desc'); // asc or desc
            $table->string('chart_type')->nullable(); // bar, line, pie, table
            $table->unsignedInteger('user_id');
            $table->boolean('is_public')->default(false); // shared with team
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index('entity_type');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_definitions');
    }
};
