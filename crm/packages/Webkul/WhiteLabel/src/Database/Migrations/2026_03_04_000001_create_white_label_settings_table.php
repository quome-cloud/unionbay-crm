<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('white_label_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('app_name')->default('CRM');
            $table->string('logo_url')->nullable();
            $table->string('logo_dark_url')->nullable();
            $table->string('favicon_url')->nullable();
            $table->string('primary_color')->default('#1E40AF');
            $table->string('secondary_color')->default('#7C3AED');
            $table->string('accent_color')->default('#F59E0B');
            $table->string('email_sender_name')->default('CRM');
            $table->string('support_url')->nullable();
            $table->string('login_bg_image')->nullable();
            $table->text('custom_css')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('white_label_settings');
    }
};
