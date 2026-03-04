<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    private const VALID_EVENTS = [
        'new_contact',
        'new_lead',
        'lead_stage_changed',
        'deal_won',
        'deal_lost',
        'new_activity',
        'email_received',
    ];

    /**
     * List webhook subscriptions for the authenticated user.
     */
    public function index(): JsonResponse
    {
        $subscriptions = DB::table('webhook_subscriptions')
            ->where('user_id', auth()->id())
            ->get()
            ->map(fn ($s) => $this->formatSubscription($s));

        return response()->json(['data' => $subscriptions]);
    }

    /**
     * Subscribe to a webhook event (used by Zapier triggers).
     */
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'event'      => 'required|string|in:' . implode(',', self::VALID_EVENTS),
            'target_url' => 'required|url',
        ]);

        $id = DB::table('webhook_subscriptions')->insertGetId([
            'user_id'    => auth()->id(),
            'event'      => $request->input('event'),
            'target_url' => $request->input('target_url'),
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subscription = DB::table('webhook_subscriptions')->where('id', $id)->first();

        return response()->json([
            'data'    => $this->formatSubscription($subscription),
            'message' => 'Webhook subscription created.',
        ], 201);
    }

    /**
     * Unsubscribe from a webhook event (used by Zapier).
     */
    public function unsubscribe(int $id): JsonResponse
    {
        $deleted = DB::table('webhook_subscriptions')
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Webhook subscription not found'], 404);
        }

        return response()->json(['message' => 'Webhook subscription removed.']);
    }

    /**
     * Get a specific subscription.
     */
    public function show(int $id): JsonResponse
    {
        $subscription = DB::table('webhook_subscriptions')
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (! $subscription) {
            return response()->json(['message' => 'Webhook subscription not found'], 404);
        }

        return response()->json(['data' => $this->formatSubscription($subscription)]);
    }

    /**
     * List available webhook events.
     */
    public function events(): JsonResponse
    {
        $events = array_map(fn ($e) => [
            'event'       => $e,
            'description' => $this->getEventDescription($e),
        ], self::VALID_EVENTS);

        return response()->json(['data' => $events]);
    }

    /**
     * Test a webhook subscription by sending a sample payload.
     */
    public function test(int $id): JsonResponse
    {
        $subscription = DB::table('webhook_subscriptions')
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (! $subscription) {
            return response()->json(['message' => 'Webhook subscription not found'], 404);
        }

        $samplePayload = $this->getSamplePayload($subscription->event);

        try {
            $response = Http::timeout(10)->post($subscription->target_url, $samplePayload);

            return response()->json([
                'data' => [
                    'success'     => $response->successful(),
                    'status_code' => $response->status(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'data' => [
                    'success' => false,
                    'error'   => $e->getMessage(),
                ],
            ]);
        }
    }

    /**
     * Trigger webhooks for a given event (called internally).
     */
    public static function dispatch(string $event, array $payload): void
    {
        $subscriptions = DB::table('webhook_subscriptions')
            ->where('event', $event)
            ->where('is_active', true)
            ->get();

        foreach ($subscriptions as $subscription) {
            try {
                Http::timeout(10)->post($subscription->target_url, array_merge($payload, [
                    'event'      => $event,
                    'triggered_at' => now()->toIso8601String(),
                ]));

                DB::table('webhook_subscriptions')->where('id', $subscription->id)->update([
                    'last_triggered_at' => now(),
                    'failure_count'     => 0,
                    'updated_at'        => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning("Webhook delivery failed for subscription {$subscription->id}: " . $e->getMessage());

                $failureCount = $subscription->failure_count + 1;
                $updates = [
                    'failure_count' => $failureCount,
                    'updated_at'    => now(),
                ];

                // Disable after 5 consecutive failures
                if ($failureCount >= 5) {
                    $updates['is_active'] = false;
                }

                DB::table('webhook_subscriptions')->where('id', $subscription->id)->update($updates);
            }
        }
    }

    /**
     * Format subscription for API response.
     */
    private function formatSubscription(object $subscription): array
    {
        return [
            'id'                => $subscription->id,
            'event'             => $subscription->event,
            'target_url'        => $subscription->target_url,
            'is_active'         => (bool) $subscription->is_active,
            'last_triggered_at' => $subscription->last_triggered_at,
            'failure_count'     => $subscription->failure_count,
            'created_at'        => $subscription->created_at,
        ];
    }

    /**
     * Get human-readable event description.
     */
    private function getEventDescription(string $event): string
    {
        return match ($event) {
            'new_contact'        => 'Triggered when a new contact is created',
            'new_lead'           => 'Triggered when a new lead is created',
            'lead_stage_changed' => 'Triggered when a lead changes pipeline stage',
            'deal_won'           => 'Triggered when a deal is marked as won',
            'deal_lost'          => 'Triggered when a deal is marked as lost',
            'new_activity'       => 'Triggered when a new activity is logged',
            'email_received'     => 'Triggered when a new email is received',
            default              => 'Unknown event',
        };
    }

    /**
     * Get sample payload for testing.
     */
    private function getSamplePayload(string $event): array
    {
        return match ($event) {
            'new_contact' => [
                'event' => 'new_contact',
                'data'  => ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
            ],
            'new_lead' => [
                'event' => 'new_lead',
                'data'  => ['id' => 1, 'title' => 'Sample Lead', 'status' => 'new'],
            ],
            'lead_stage_changed' => [
                'event' => 'lead_stage_changed',
                'data'  => ['id' => 1, 'title' => 'Sample Lead', 'old_stage' => 'new', 'new_stage' => 'qualified'],
            ],
            'deal_won' => [
                'event' => 'deal_won',
                'data'  => ['id' => 1, 'title' => 'Sample Deal', 'value' => 10000],
            ],
            'deal_lost' => [
                'event' => 'deal_lost',
                'data'  => ['id' => 1, 'title' => 'Sample Deal', 'reason' => 'Budget constraints'],
            ],
            'new_activity' => [
                'event' => 'new_activity',
                'data'  => ['id' => 1, 'type' => 'call', 'title' => 'Follow-up call'],
            ],
            'email_received' => [
                'event' => 'email_received',
                'data'  => ['id' => 1, 'subject' => 'Re: Proposal', 'from' => 'client@example.com'],
            ],
            default => ['event' => $event, 'data' => ['test' => true]],
        };
    }
}
