<?php

namespace Webkul\WhiteLabel\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WhiteLabelSeeder extends Seeder
{
    public function run()
    {
        if (DB::table('white_label_settings')->count() === 0) {
            DB::table('white_label_settings')->insert([
                'app_name'          => 'CRM',
                'primary_color'     => '#1E40AF',
                'secondary_color'   => '#7C3AED',
                'accent_color'      => '#F59E0B',
                'email_sender_name' => 'CRM',
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }
    }
}
