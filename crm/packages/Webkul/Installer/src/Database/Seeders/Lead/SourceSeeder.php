<?php

namespace Webkul\Installer\Database\Seeders\Lead;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SourceSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        DB::table('lead_sources')->delete();

        $now = Carbon::now();

        $defaultLocale = $parameters['locale'] ?? config('app.locale');

        DB::table('lead_sources')->insert([
            ['id' => 1,  'name' => 'Referral',           'created_at' => $now, 'updated_at' => $now],
            ['id' => 2,  'name' => 'Cold Call',           'created_at' => $now, 'updated_at' => $now],
            ['id' => 3,  'name' => 'Networking Event',    'created_at' => $now, 'updated_at' => $now],
            ['id' => 4,  'name' => 'Website',             'created_at' => $now, 'updated_at' => $now],
            ['id' => 5,  'name' => 'Social Media',        'created_at' => $now, 'updated_at' => $now],
            ['id' => 6,  'name' => 'Email',               'created_at' => $now, 'updated_at' => $now],
            ['id' => 7,  'name' => 'Phone',               'created_at' => $now, 'updated_at' => $now],
            ['id' => 8,  'name' => 'Walk-in',             'created_at' => $now, 'updated_at' => $now],
            ['id' => 9,  'name' => 'Conference / Trade Show', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 10, 'name' => 'Partner / Agent',     'created_at' => $now, 'updated_at' => $now],
            ['id' => 11, 'name' => 'Other',               'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
