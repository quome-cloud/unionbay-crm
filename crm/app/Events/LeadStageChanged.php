<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadStageChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $leadId,
        public int $pipelineId,
        public int $fromStageId,
        public int $toStageId,
        public array $data = []
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('crm.leads'),
            new PrivateChannel('crm.pipeline.' . $this->pipelineId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'lead.stage_changed';
    }

    public function broadcastWith(): array
    {
        return [
            'lead_id'       => $this->leadId,
            'pipeline_id'   => $this->pipelineId,
            'from_stage_id' => $this->fromStageId,
            'to_stage_id'   => $this->toStageId,
            'data'          => $this->data,
        ];
    }
}
