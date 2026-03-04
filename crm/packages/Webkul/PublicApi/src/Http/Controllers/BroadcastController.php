<?php

namespace Webkul\PublicApi\Http\Controllers;

use App\Events\ActionStreamUpdated;
use App\Events\ContactUpdated;
use App\Events\EmailReceived;
use App\Events\LeadStageChanged;
use App\Events\NotificationReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BroadcastController extends Controller
{
    /**
     * Fire a test broadcast event (useful for testing WebSocket connectivity).
     */
    public function test(Request $request): JsonResponse
    {
        $request->validate([
            'event'   => 'required|string|in:notification,contact,lead,email,action_stream',
            'user_id' => 'sometimes|integer',
            'data'    => 'sometimes|array',
        ]);

        $user = $request->user();
        $userId = $request->input('user_id', $user->id);
        $data = $request->input('data', []);

        switch ($request->input('event')) {
            case 'notification':
                event(new NotificationReceived($userId, array_merge([
                    'id'      => 0,
                    'type'    => 'test',
                    'title'   => 'Test Notification',
                    'message' => 'This is a test broadcast.',
                ], $data)));
                break;

            case 'contact':
                event(new ContactUpdated(
                    $data['contact_id'] ?? 1,
                    $data['action'] ?? 'updated',
                    $data
                ));
                break;

            case 'lead':
                event(new LeadStageChanged(
                    $data['lead_id'] ?? 1,
                    $data['pipeline_id'] ?? 1,
                    $data['from_stage_id'] ?? 1,
                    $data['to_stage_id'] ?? 2,
                    $data
                ));
                break;

            case 'email':
                event(new EmailReceived($userId, $data['email_id'] ?? 1, $data));
                break;

            case 'action_stream':
                event(new ActionStreamUpdated($userId, $data['action'] ?? 'created', $data));
                break;
        }

        return response()->json([
            'data' => [
                'event'   => $request->input('event'),
                'user_id' => $userId,
                'fired'   => true,
            ],
            'message' => 'Broadcast event fired.',
        ]);
    }

    /**
     * List available broadcast channels.
     */
    public function channels(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'private' => [
                    'user.' . $user->id,
                    'crm.contacts',
                    'crm.leads',
                ],
                'presence' => [
                    'crm.team',
                ],
                'config' => [
                    'host'   => config('broadcasting.connections.pusher.options.host', 'localhost'),
                    'port'   => config('broadcasting.connections.pusher.options.port', 6001),
                    'key'    => config('broadcasting.connections.pusher.key'),
                    'scheme' => config('broadcasting.connections.pusher.options.scheme', 'http'),
                ],
            ],
        ]);
    }
}
