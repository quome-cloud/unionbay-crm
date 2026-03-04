<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContactUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $contactId,
        public string $action,
        public array $data = []
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('crm.contacts'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'contact.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'contact_id' => $this->contactId,
            'action'     => $this->action,
            'data'       => $this->data,
        ];
    }
}
