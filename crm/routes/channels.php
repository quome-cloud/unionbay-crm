<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Private user channel — only the user themselves can listen
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// CRM contacts channel — any authenticated admin user can listen
Broadcast::channel('crm.contacts', function ($user) {
    return $user !== null;
});

// CRM leads channel — any authenticated admin user can listen
Broadcast::channel('crm.leads', function ($user) {
    return $user !== null;
});

// Pipeline-specific channel — any authenticated admin user can listen
Broadcast::channel('crm.pipeline.{pipelineId}', function ($user, $pipelineId) {
    return $user !== null;
});

// Presence channel for team collaboration
Broadcast::channel('crm.team', function ($user) {
    if ($user) {
        return [
            'id'   => $user->id,
            'name' => $user->name,
        ];
    }

    return false;
});
