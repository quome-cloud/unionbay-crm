<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class MailchimpController extends Controller
{
    /**
     * Get Mailchimp connection status and config.
     */
    public function status(): JsonResponse
    {
        $config = DB::table('integrations')
            ->where('provider', 'mailchimp')
            ->first();

        if (! $config) {
            return response()->json([
                'data' => ['connected' => false],
            ]);
        }

        $settings = json_decode($config->settings, true) ?? [];

        return response()->json([
            'data' => [
                'connected'   => (bool) $config->active,
                'server'      => $settings['server'] ?? null,
                'connected_at' => $config->created_at,
            ],
        ]);
    }

    /**
     * Connect to Mailchimp (store API key).
     */
    public function connect(Request $request): JsonResponse
    {
        $request->validate([
            'api_key' => 'required|string',
        ]);

        $apiKey = $request->input('api_key');

        // Extract data center from API key (format: key-dc)
        $parts = explode('-', $apiKey);
        $dc = end($parts);

        if (! $dc || strlen($dc) < 2) {
            return response()->json(['message' => 'Invalid API key format. Expected format: key-dc'], 422);
        }

        // Verify the API key by pinging Mailchimp
        $response = Http::withBasicAuth('anystring', $apiKey)
            ->get("https://{$dc}.api.mailchimp.com/3.0/ping");

        if (! $response->ok()) {
            return response()->json(['message' => 'Invalid Mailchimp API key'], 422);
        }

        DB::table('integrations')->updateOrInsert(
            ['provider' => 'mailchimp'],
            [
                'active'     => true,
                'settings'   => json_encode([
                    'api_key' => $apiKey,
                    'server'  => $dc,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return response()->json([
            'data' => [
                'connected' => true,
                'server'    => $dc,
            ],
            'message' => 'Mailchimp connected successfully.',
        ]);
    }

    /**
     * Disconnect Mailchimp.
     */
    public function disconnect(): JsonResponse
    {
        DB::table('integrations')
            ->where('provider', 'mailchimp')
            ->delete();

        return response()->json([
            'message' => 'Mailchimp disconnected.',
        ]);
    }

    /**
     * List Mailchimp audiences (lists).
     */
    public function audiences(): JsonResponse
    {
        [$apiKey, $dc] = $this->getCredentials();

        if (! $apiKey) {
            return response()->json(['message' => 'Mailchimp not connected'], 422);
        }

        $response = Http::withBasicAuth('anystring', $apiKey)
            ->get("https://{$dc}.api.mailchimp.com/3.0/lists", [
                'count'  => 100,
                'fields' => 'lists.id,lists.name,lists.stats.member_count',
            ]);

        if (! $response->ok()) {
            return response()->json(['message' => 'Failed to fetch audiences'], 502);
        }

        return response()->json([
            'data' => $response->json('lists') ?? [],
        ]);
    }

    /**
     * Sync a CRM contact to a Mailchimp audience.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'contact_id'  => 'required|integer',
            'audience_id' => 'required|string',
            'tags'        => 'sometimes|array',
        ]);

        [$apiKey, $dc] = $this->getCredentials();

        if (! $apiKey) {
            return response()->json(['message' => 'Mailchimp not connected'], 422);
        }

        $contact = DB::table('persons')->where('id', $request->input('contact_id'))->first();

        if (! $contact) {
            return response()->json(['message' => 'Contact not found'], 404);
        }

        $emails = json_decode($contact->emails, true) ?? [];
        $email = $emails[0]['value'] ?? null;

        if (! $email) {
            return response()->json(['message' => 'Contact has no email address'], 422);
        }

        $audienceId = $request->input('audience_id');
        $subscriberHash = md5(strtolower($email));

        $payload = [
            'email_address' => $email,
            'status_if_new' => 'subscribed',
            'status'        => 'subscribed',
            'merge_fields'  => [
                'FNAME' => explode(' ', $contact->name)[0] ?? '',
                'LNAME' => implode(' ', array_slice(explode(' ', $contact->name), 1)) ?? '',
            ],
        ];

        $response = Http::withBasicAuth('anystring', $apiKey)
            ->put("https://{$dc}.api.mailchimp.com/3.0/lists/{$audienceId}/members/{$subscriberHash}", $payload);

        if (! $response->ok() && $response->status() !== 200) {
            return response()->json([
                'message' => 'Failed to subscribe contact',
                'error'   => $response->json('detail'),
            ], 502);
        }

        // Add tags if provided
        $tags = $request->input('tags', []);
        if (! empty($tags)) {
            Http::withBasicAuth('anystring', $apiKey)
                ->post("https://{$dc}.api.mailchimp.com/3.0/lists/{$audienceId}/members/{$subscriberHash}/tags", [
                    'tags' => array_map(fn ($tag) => ['name' => $tag, 'status' => 'active'], $tags),
                ]);
        }

        // Store sync record
        DB::table('mailchimp_syncs')->updateOrInsert(
            ['contact_id' => $contact->id, 'audience_id' => $audienceId],
            [
                'subscriber_hash' => $subscriberHash,
                'email'           => $email,
                'status'          => 'subscribed',
                'synced_at'       => now(),
                'updated_at'      => now(),
            ]
        );

        return response()->json([
            'data' => [
                'contact_id'  => $contact->id,
                'audience_id' => $audienceId,
                'email'       => $email,
                'status'      => 'subscribed',
            ],
            'message' => 'Contact subscribed to Mailchimp audience.',
        ], 201);
    }

    /**
     * Unsubscribe a contact from a Mailchimp audience.
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate([
            'contact_id'  => 'required|integer',
            'audience_id' => 'required|string',
        ]);

        [$apiKey, $dc] = $this->getCredentials();

        if (! $apiKey) {
            return response()->json(['message' => 'Mailchimp not connected'], 422);
        }

        $sync = DB::table('mailchimp_syncs')
            ->where('contact_id', $request->input('contact_id'))
            ->where('audience_id', $request->input('audience_id'))
            ->first();

        if (! $sync) {
            return response()->json(['message' => 'Contact is not subscribed to this audience'], 404);
        }

        $audienceId = $request->input('audience_id');

        Http::withBasicAuth('anystring', $apiKey)
            ->patch("https://{$dc}.api.mailchimp.com/3.0/lists/{$audienceId}/members/{$sync->subscriber_hash}", [
                'status' => 'unsubscribed',
            ]);

        DB::table('mailchimp_syncs')
            ->where('id', $sync->id)
            ->update(['status' => 'unsubscribed', 'updated_at' => now()]);

        return response()->json([
            'message' => 'Contact unsubscribed from Mailchimp audience.',
        ]);
    }

    /**
     * Get Mailchimp sync status for a contact.
     */
    public function contactStatus(int $contactId): JsonResponse
    {
        $syncs = DB::table('mailchimp_syncs')
            ->where('contact_id', $contactId)
            ->get();

        return response()->json([
            'data' => $syncs,
        ]);
    }

    /**
     * Get campaign stats for a contact's email.
     */
    public function campaignStats(int $contactId): JsonResponse
    {
        [$apiKey, $dc] = $this->getCredentials();

        if (! $apiKey) {
            return response()->json(['message' => 'Mailchimp not connected'], 422);
        }

        $contact = DB::table('persons')->where('id', $contactId)->first();

        if (! $contact) {
            return response()->json(['message' => 'Contact not found'], 404);
        }

        $emails = json_decode($contact->emails, true) ?? [];
        $email = $emails[0]['value'] ?? null;

        if (! $email) {
            return response()->json(['data' => []]);
        }

        $subscriberHash = md5(strtolower($email));

        // Get all audiences this contact is in
        $syncs = DB::table('mailchimp_syncs')
            ->where('contact_id', $contactId)
            ->get();

        $stats = [];
        foreach ($syncs as $sync) {
            $response = Http::withBasicAuth('anystring', $apiKey)
                ->get("https://{$dc}.api.mailchimp.com/3.0/lists/{$sync->audience_id}/members/{$subscriberHash}/activity");

            if ($response->ok()) {
                $activity = $response->json('activity') ?? [];
                $stats[] = [
                    'audience_id' => $sync->audience_id,
                    'email'       => $sync->email,
                    'status'      => $sync->status,
                    'activity'    => array_slice($activity, 0, 20),
                ];
            }
        }

        return response()->json(['data' => $stats]);
    }

    /**
     * Get stored Mailchimp credentials.
     */
    private function getCredentials(): array
    {
        $config = DB::table('integrations')
            ->where('provider', 'mailchimp')
            ->where('active', true)
            ->first();

        if (! $config) {
            return [null, null];
        }

        $settings = json_decode($config->settings, true) ?? [];

        return [$settings['api_key'] ?? null, $settings['server'] ?? null];
    }
}
