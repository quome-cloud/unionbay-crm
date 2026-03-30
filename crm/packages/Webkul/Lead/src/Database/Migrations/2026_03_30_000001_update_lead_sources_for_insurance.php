<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();

        // Rename existing sources to insurance-relevant names
        DB::table('lead_sources')->where('id', 1)->update(['name' => 'Referral']);
        DB::table('lead_sources')->where('id', 2)->update(['name' => 'Cold Call']);
        DB::table('lead_sources')->where('id', 3)->update(['name' => 'Networking Event']);
        DB::table('lead_sources')->where('id', 4)->update(['name' => 'Website']);
        DB::table('lead_sources')->where('id', 5)->update(['name' => 'Social Media']);

        // Add new sources
        $newSources = [
            ['name' => 'Email'],
            ['name' => 'Phone'],
            ['name' => 'Walk-in'],
            ['name' => 'Conference / Trade Show'],
            ['name' => 'Partner / Agent'],
            ['name' => 'Other'],
        ];

        foreach ($newSources as $source) {
            if (! DB::table('lead_sources')->where('name', $source['name'])->exists()) {
                DB::table('lead_sources')->insert(array_merge($source, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        // Revert to original source names
        DB::table('lead_sources')->where('id', 1)->update(['name' => 'Email']);
        DB::table('lead_sources')->where('id', 2)->update(['name' => 'Web']);
        DB::table('lead_sources')->where('id', 3)->update(['name' => 'Web Form']);
        DB::table('lead_sources')->where('id', 4)->update(['name' => 'Phone']);
        DB::table('lead_sources')->where('id', 5)->update(['name' => 'Direct']);

        // Remove added sources
        DB::table('lead_sources')
            ->whereIn('name', ['Walk-in', 'Conference / Trade Show', 'Partner / Agent', 'Other', 'Social Media'])
            ->whereNotIn('id', [1, 2, 3, 4, 5])
            ->delete();
    }
};
