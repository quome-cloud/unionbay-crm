<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('next_actions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('actionable_type')->comment('persons or leads');
            $table->unsignedInteger('actionable_id');
            $table->unsignedInteger('user_id');
            $table->string('action_type')->default('call')->comment('call, email, meeting, task, custom');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->time('due_time')->nullable();
            $table->string('priority')->default('normal')->comment('urgent, high, normal, low');
            $table->string('status')->default('pending')->comment('pending, completed, snoozed');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index(['actionable_type', 'actionable_id']);
            $table->index(['user_id', 'status', 'due_date']);
            $table->index(['status', 'due_date']);
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('next_actions');
    }
};
