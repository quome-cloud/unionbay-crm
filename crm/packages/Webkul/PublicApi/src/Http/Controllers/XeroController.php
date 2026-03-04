<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class XeroController extends Controller
{
    /**
     * Get Xero connection status.
     */
    public function status(): JsonResponse
    {
        $config = DB::table('integrations')
            ->where('provider', 'xero')
            ->first();

        if (! $config) {
            return response()->json(['data' => ['connected' => false]]);
        }

        $settings = json_decode($config->settings, true) ?? [];

        return response()->json([
            'data' => [
                'connected'    => (bool) $config->active,
                'tenant_id'    => $settings['tenant_id'] ?? null,
                'connected_at' => $config->created_at,
            ],
        ]);
    }

    /**
     * Get OAuth2 authorization URL.
     */
    public function authUrl(Request $request): JsonResponse
    {
        $request->validate([
            'client_id'     => 'required|string',
            'client_secret' => 'required|string',
            'redirect_uri'  => 'required|url',
        ]);

        DB::table('integrations')->updateOrInsert(
            ['provider' => 'xero'],
            [
                'active'   => false,
                'settings' => json_encode([
                    'client_id'     => $request->input('client_id'),
                    'client_secret' => $request->input('client_secret'),
                    'redirect_uri'  => $request->input('redirect_uri'),
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => $request->input('client_id'),
            'redirect_uri'  => $request->input('redirect_uri'),
            'scope'         => 'openid profile email accounting.transactions accounting.contacts',
            'state'         => csrf_token(),
        ]);

        return response()->json([
            'data' => [
                'auth_url' => "https://login.xero.com/identity/connect/authorize?{$params}",
            ],
        ]);
    }

    /**
     * Handle OAuth2 callback.
     */
    public function callback(Request $request): JsonResponse
    {
        $request->validate([
            'code'      => 'required|string',
            'tenant_id' => 'required|string',
        ]);

        $config = DB::table('integrations')->where('provider', 'xero')->first();

        if (! $config) {
            return response()->json(['message' => 'Xero not initialized'], 422);
        }

        $settings = json_decode($config->settings, true) ?? [];

        $response = Http::asForm()
            ->withBasicAuth($settings['client_id'] ?? '', $settings['client_secret'] ?? '')
            ->post('https://identity.xero.com/connect/token', [
                'grant_type'   => 'authorization_code',
                'code'         => $request->input('code'),
                'redirect_uri' => $settings['redirect_uri'] ?? '',
            ]);

        if (! $response->ok()) {
            return response()->json(['message' => 'Token exchange failed'], 502);
        }

        $tokens = $response->json();

        DB::table('integrations')->where('provider', 'xero')->update([
            'active'   => true,
            'settings' => json_encode(array_merge($settings, [
                'tenant_id'     => $request->input('tenant_id'),
                'access_token'  => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_at'    => now()->addSeconds($tokens['expires_in'] ?? 1800)->toIso8601String(),
            ])),
            'updated_at' => now(),
        ]);

        return response()->json([
            'data'    => ['connected' => true, 'tenant_id' => $request->input('tenant_id')],
            'message' => 'Xero connected successfully.',
        ]);
    }

    /**
     * Disconnect Xero.
     */
    public function disconnect(): JsonResponse
    {
        DB::table('integrations')->where('provider', 'xero')->delete();

        return response()->json(['message' => 'Xero disconnected.']);
    }

    /**
     * Create an invoice in Xero.
     */
    public function createInvoice(Request $request): JsonResponse
    {
        $request->validate([
            'contact_id'  => 'required|integer',
            'line_items'  => 'required|array|min:1',
            'line_items.*.description' => 'required|string',
            'line_items.*.amount'      => 'required|numeric|min:0',
            'line_items.*.quantity'     => 'sometimes|integer|min:1',
            'due_date'    => 'sometimes|date',
        ]);

        [$settings, $error] = $this->getActiveConfig();

        if ($error) {
            return $error;
        }

        $contact = DB::table('persons')->where('id', $request->input('contact_id'))->first();

        if (! $contact) {
            return response()->json(['message' => 'Contact not found'], 404);
        }

        $lineItems = [];
        foreach ($request->input('line_items') as $item) {
            $lineItems[] = [
                'Description' => $item['description'],
                'UnitAmount'  => $item['amount'],
                'Quantity'    => $item['quantity'] ?? 1,
                'AccountCode' => '200',
            ];
        }

        $invoiceData = [
            'Type'      => 'ACCREC',
            'Contact'   => ['Name' => $contact->name],
            'LineItems' => $lineItems,
            'Status'    => 'DRAFT',
        ];

        if ($request->has('due_date')) {
            $invoiceData['DueDate'] = $request->input('due_date');
        }

        $response = $this->xeroRequest($settings, 'POST', '/api.xro/2.0/Invoices', ['Invoices' => [$invoiceData]]);

        if (! $response->ok()) {
            return response()->json([
                'message' => 'Failed to create invoice in Xero',
                'error'   => $response->json(),
            ], 502);
        }

        $invoices = $response->json('Invoices') ?? [];
        $invoice = $invoices[0] ?? [];

        DB::table('xero_syncs')->insert([
            'contact_id'    => $contact->id,
            'xero_type'     => 'invoice',
            'xero_id'       => $invoice['InvoiceID'] ?? null,
            'xero_number'   => $invoice['InvoiceNumber'] ?? null,
            'amount'        => $invoice['Total'] ?? 0,
            'status'        => $invoice['Status'] ?? 'DRAFT',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json([
            'data' => [
                'invoice_id' => $invoice['InvoiceID'] ?? null,
                'number'     => $invoice['InvoiceNumber'] ?? null,
                'total'      => $invoice['Total'] ?? 0,
                'status'     => $invoice['Status'] ?? 'DRAFT',
                'contact_id' => $contact->id,
            ],
            'message' => 'Invoice created in Xero.',
        ], 201);
    }

    /**
     * Sync a CRM contact to Xero.
     */
    public function syncContact(Request $request): JsonResponse
    {
        $request->validate([
            'contact_id' => 'required|integer',
        ]);

        [$settings, $error] = $this->getActiveConfig();

        if ($error) {
            return $error;
        }

        $contact = DB::table('persons')->where('id', $request->input('contact_id'))->first();

        if (! $contact) {
            return response()->json(['message' => 'Contact not found'], 404);
        }

        $emails = json_decode($contact->emails, true) ?? [];
        $email = $emails[0]['value'] ?? null;

        $contactData = [
            'Name' => $contact->name,
        ];

        if ($email) {
            $contactData['EmailAddress'] = $email;
        }

        $response = $this->xeroRequest($settings, 'POST', '/api.xro/2.0/Contacts', [
            'Contacts' => [$contactData],
        ]);

        if (! $response->ok()) {
            return response()->json([
                'message' => 'Failed to sync contact to Xero',
                'error'   => $response->json(),
            ], 502);
        }

        $contacts = $response->json('Contacts') ?? [];
        $xeroContact = $contacts[0] ?? [];

        DB::table('xero_syncs')->insert([
            'contact_id' => $contact->id,
            'xero_type'  => 'contact',
            'xero_id'    => $xeroContact['ContactID'] ?? null,
            'status'     => 'synced',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'contact_id'     => $contact->id,
                'xero_contact_id' => $xeroContact['ContactID'] ?? null,
            ],
            'message' => 'Contact synced to Xero.',
        ], 201);
    }

    /**
     * List Xero sync records for a contact.
     */
    public function contactSyncs(int $contactId): JsonResponse
    {
        $syncs = DB::table('xero_syncs')
            ->where('contact_id', $contactId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $syncs]);
    }

    /**
     * Make a Xero API request.
     */
    private function xeroRequest(array $settings, string $method, string $endpoint, array $data = [])
    {
        return Http::withToken($settings['access_token'] ?? '')
            ->withHeaders([
                'Xero-Tenant-Id' => $settings['tenant_id'] ?? '',
                'Accept'         => 'application/json',
            ])
            ->{strtolower($method)}("https://api.xero.com{$endpoint}", $data);
    }

    /**
     * Get active Xero config.
     */
    private function getActiveConfig(): array
    {
        $config = DB::table('integrations')
            ->where('provider', 'xero')
            ->where('active', true)
            ->first();

        if (! $config) {
            return [null, response()->json(['message' => 'Xero not connected'], 422)];
        }

        return [json_decode($config->settings, true) ?? [], null];
    }
}
