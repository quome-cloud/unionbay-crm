<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('integrations')) {
            Schema::create('integrations', function (Blueprint $table) {
                $table->id();
                $table->string('provider')->unique(); // mailchimp, zapier, quickbooks, etc.
                $table->boolean('active')->default(true);
                $table->json('settings')->nullable(); // provider-specific config
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mailchimp_syncs')) {
            Schema::create('mailchimp_syncs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('contact_id');
                $table->string('audience_id');
                $table->string('subscriber_hash', 32);
                $table->string('email');
                $table->string('status')->default('subscribed'); // subscribed, unsubscribed, pending
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->unique(['contact_id', 'audience_id']);
                $table->foreign('contact_id')->references('id')->on('persons')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mailchimp_syncs');
        Schema::dropIfExists('integrations');
    }
};
