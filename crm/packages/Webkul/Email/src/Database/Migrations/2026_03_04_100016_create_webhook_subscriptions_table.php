<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('event'); // new_contact, new_lead, lead_stage_changed, deal_won, deal_lost, new_activity, email_received
            $table->string('target_url');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->integer('failure_count')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['event', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_subscriptions');
    }
};
