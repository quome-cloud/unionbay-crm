<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $pipelineId = DB::table('lead_pipelines')->where('is_default', 1)->value('id');

        if (! $pipelineId) {
            return;
        }

        // Remove old stages that aren't in the new set
        // Keep any leads on deleted stages by reassigning to 'new' first
        $newStageId = DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', $pipelineId)
            ->where('code', 'new')
            ->value('id');

        $keepCodes = ['new', 'prospect', 'won', 'lost'];
        $removeCodes = ['follow-up', 'negotiation'];

        // Reassign leads from stages being removed to 'new'
        if ($newStageId) {
            $removeIds = DB::table('lead_pipeline_stages')
                ->where('lead_pipeline_id', $pipelineId)
                ->whereIn('code', $removeCodes)
                ->pluck('id');

            if ($removeIds->isNotEmpty()) {
                DB::table('leads')
                    ->whereIn('lead_pipeline_stage_id', $removeIds)
                    ->update(['lead_pipeline_stage_id' => $newStageId]);
            }
        }

        // Delete old stages that are being replaced
        DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', $pipelineId)
            ->whereIn('code', $removeCodes)
            ->delete();

        // Define the new insurance pipeline stages
        $stages = [
            ['code' => 'new',             'name' => 'New',             'probability' => 10,  'sort_order' => 1],
            ['code' => 'recruits',        'name' => 'Recruits',        'probability' => 15,  'sort_order' => 2],
            ['code' => 'prospect',        'name' => 'Prospect',        'probability' => 25,  'sort_order' => 3],
            ['code' => 'data-gathering',  'name' => 'Data Gathering',  'probability' => 40,  'sort_order' => 4],
            ['code' => 'quoting',         'name' => 'Quoting',         'probability' => 60,  'sort_order' => 5],
            ['code' => 'presenting',      'name' => 'Presenting',      'probability' => 80,  'sort_order' => 6],
            ['code' => 'won',             'name' => 'Won',             'probability' => 100, 'sort_order' => 7],
            ['code' => 'lost',            'name' => 'Loss',            'probability' => 0,   'sort_order' => 8],
        ];

        foreach ($stages as $stage) {
            $existing = DB::table('lead_pipeline_stages')
                ->where('lead_pipeline_id', $pipelineId)
                ->where('code', $stage['code'])
                ->first();

            if ($existing) {
                DB::table('lead_pipeline_stages')
                    ->where('id', $existing->id)
                    ->update([
                        'name'        => $stage['name'],
                        'probability' => $stage['probability'],
                        'sort_order'  => $stage['sort_order'],
                    ]);
            } else {
                DB::table('lead_pipeline_stages')->insert(array_merge($stage, [
                    'lead_pipeline_id' => $pipelineId,
                ]));
            }
        }
    }

    public function down(): void
    {
        // Revert to default CRM stages
        $pipelineId = DB::table('lead_pipelines')->where('is_default', 1)->value('id');

        if (! $pipelineId) {
            return;
        }

        // Remove insurance-specific stages
        DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', $pipelineId)
            ->whereIn('code', ['recruits', 'data-gathering', 'quoting', 'presenting'])
            ->delete();

        // Restore original stages
        $defaults = [
            ['code' => 'new',         'name' => 'New',         'probability' => 100, 'sort_order' => 1],
            ['code' => 'follow-up',   'name' => 'Follow Up',   'probability' => 100, 'sort_order' => 2],
            ['code' => 'prospect',    'name' => 'Prospect',    'probability' => 100, 'sort_order' => 3],
            ['code' => 'negotiation', 'name' => 'Negotiation', 'probability' => 100, 'sort_order' => 4],
            ['code' => 'won',         'name' => 'Won',         'probability' => 100, 'sort_order' => 5],
            ['code' => 'lost',        'name' => 'Lost',        'probability' => 0,   'sort_order' => 6],
        ];

        foreach ($defaults as $stage) {
            $existing = DB::table('lead_pipeline_stages')
                ->where('lead_pipeline_id', $pipelineId)
                ->where('code', $stage['code'])
                ->first();

            if ($existing) {
                DB::table('lead_pipeline_stages')
                    ->where('id', $existing->id)
                    ->update($stage);
            } else {
                DB::table('lead_pipeline_stages')->insert(array_merge($stage, [
                    'lead_pipeline_id' => $pipelineId,
                ]));
            }
        }
    }
};
