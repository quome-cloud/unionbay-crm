<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Allow email accounts to be shared with team members
        Schema::create('email_account_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('email_account_id');
            $table->unsignedInteger('user_id');
            $table->string('role')->default('member'); // owner, admin, member
            $table->timestamps();

            $table->foreign('email_account_id')->references('id')->on('email_accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['email_account_id', 'user_id']);
        });

        // Track email thread assignment to team members
        Schema::table('emails', function (Blueprint $table) {
            $table->unsignedInteger('assigned_to')->nullable()->after('person_id');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
        });

        // Track per-user read status for shared inboxes
        Schema::create('email_read_status', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('email_id');
            $table->unsignedInteger('user_id');
            $table->timestamp('read_at')->nullable();

            $table->foreign('email_id')->references('id')->on('emails')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['email_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_read_status');

        Schema::table('emails', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn('assigned_to');
        });

        Schema::dropIfExists('email_account_members');
    }
};
