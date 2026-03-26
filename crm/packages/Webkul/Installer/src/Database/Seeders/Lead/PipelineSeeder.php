<?php

namespace Webkul\Installer\Database\Seeders\Lead;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PipelineSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        DB::table('lead_pipelines')->delete();

        DB::table('lead_pipeline_stages')->delete();

        $now = Carbon::now();

        $defaultLocale = $parameters['locale'] ?? config('app.locale');

        DB::table('lead_pipelines')->insert([
            [
                'id'         => 1,
                'name'       => trans('installer::app.seeders.lead.pipeline.default', [], $defaultLocale),
                'is_default' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('lead_pipeline_stages')->insert($data = [
            [
                'id'               => 1,
                'code'             => 'new',
                'name'             => 'New',
                'probability'      => 10,
                'sort_order'       => 1,
                'lead_pipeline_id' => 1,
            ], [
                'id'               => 2,
                'code'             => 'recruits',
                'name'             => 'Recruits',
                'probability'      => 15,
                'sort_order'       => 2,
                'lead_pipeline_id' => 1,
            ], [
                'id'               => 3,
                'code'             => 'prospect',
                'name'             => 'Prospect',
                'probability'      => 25,
                'sort_order'       => 3,
                'lead_pipeline_id' => 1,
            ], [
                'id'               => 4,
                'code'             => 'data-gathering',
                'name'             => 'Data Gathering',
                'probability'      => 40,
                'sort_order'       => 4,
                'lead_pipeline_id' => 1,
            ], [
                'id'               => 5,
                'code'             => 'quoting',
                'name'             => 'Quoting',
                'probability'      => 60,
                'sort_order'       => 5,
                'lead_pipeline_id' => 1,
            ], [
                'id'               => 6,
                'code'             => 'presenting',
                'name'             => 'Presenting',
                'probability'      => 80,
                'sort_order'       => 6,
                'lead_pipeline_id' => 1,
            ], [
                'id'               => 7,
                'code'             => 'won',
                'name'             => 'Won',
                'probability'      => 100,
                'sort_order'       => 7,
                'lead_pipeline_id' => 1,
            ], [
                'id'               => 8,
                'code'             => 'lost',
                'name'             => 'Loss',
                'probability'      => 0,
                'sort_order'       => 8,
                'lead_pipeline_id' => 1,
            ],
        ]);
    }
}
