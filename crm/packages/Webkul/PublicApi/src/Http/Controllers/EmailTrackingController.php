<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmailTrackingController extends Controller
{
    /**
     * Record an email open event (tracking pixel hit).
     * This is a public endpoint — no auth required.
     */
    public function trackOpen(Request $request, string $trackingId): Response
    {
        $email = DB::table('emails')->where('tracking_id', $trackingId)->first();

        if ($email) {
            DB::table('email_tracking_events')->insert([
                'email_id'   => $email->id,
                'event_type' => 'open',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        }

        // Return a 1x1 transparent GIF
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($pixel, 200)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Record a click event and redirect to the target URL.
     * This is a public endpoint — no auth required.
     */
    public function trackClick(Request $request, string $trackingId): mixed
    {
        $url = $request->get('url');

        if (! $url) {
            return response('Missing URL', 400);
        }

        $email = DB::table('emails')->where('tracking_id', $trackingId)->first();

        if ($email) {
            DB::table('email_tracking_events')->insert([
                'email_id'    => $email->id,
                'event_type'  => 'click',
                'ip_address'  => $request->ip(),
                'user_agent'  => $request->userAgent(),
                'url_clicked' => $url,
                'created_at'  => now(),
            ]);
        }

        return redirect()->away($url);
    }

    /**
     * Get tracking events for an email (authenticated).
     */
    public function events(Request $request, int $emailId): JsonResponse
    {
        $events = DB::table('email_tracking_events')
            ->where('email_id', $emailId)
            ->orderByDesc('created_at')
            ->get();

        $opens = $events->where('event_type', 'open');
        $clicks = $events->where('event_type', 'click');

        return response()->json([
            'data' => [
                'email_id'    => $emailId,
                'open_count'  => $opens->count(),
                'click_count' => $clicks->count(),
                'first_opened_at' => $opens->last()?->created_at,
                'last_opened_at'  => $opens->first()?->created_at,
                'events'      => $events->values(),
            ],
        ]);
    }

    /**
     * Generate a tracking ID for an email and return tracking URLs.
     */
    public function generateTracking(Request $request): JsonResponse
    {
        $request->validate([
            'email_id' => 'required|integer|exists:emails,id',
        ]);

        $emailId = $request->input('email_id');
        $email = DB::table('emails')->find($emailId);

        $trackingId = $email->tracking_id;
        if (! $trackingId) {
            $trackingId = Str::uuid()->toString();
            DB::table('emails')->where('id', $emailId)->update([
                'tracking_id' => $trackingId,
            ]);
        }

        $baseUrl = config('app.url');

        return response()->json([
            'data' => [
                'tracking_id' => $trackingId,
                'pixel_url'   => "{$baseUrl}/api/v1/track/open/{$trackingId}",
                'click_url'   => "{$baseUrl}/api/v1/track/click/{$trackingId}?url=",
            ],
        ]);
    }

    /**
     * Get tracking summary for multiple emails.
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'email_ids'   => 'sometimes|array',
            'email_ids.*' => 'integer',
        ]);

        $query = DB::table('email_tracking_events')
            ->select(
                'email_id',
                DB::raw("SUM(CASE WHEN event_type = 'open' THEN 1 ELSE 0 END) as open_count"),
                DB::raw("SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as click_count"),
                DB::raw("MIN(CASE WHEN event_type = 'open' THEN created_at END) as first_opened_at"),
                DB::raw("MAX(CASE WHEN event_type = 'open' THEN created_at END) as last_opened_at"),
            )
            ->groupBy('email_id');

        if ($emailIds = $request->get('email_ids')) {
            $query->whereIn('email_id', $emailIds);
        }

        $results = $query->get();

        return response()->json(['data' => $results]);
    }
}
