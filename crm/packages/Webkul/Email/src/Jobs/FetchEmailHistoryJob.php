<?php

namespace Webkul\Email\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FetchEmailHistoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        private int $accountId,
        private int $days = 30,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $account = DB::table('email_accounts')->where('id', $this->accountId)->first();

        if (! $account) {
            Log::error("FetchEmailHistoryJob: Account {$this->accountId} not found");

            return;
        }

        DB::table('email_accounts')->where('id', $this->accountId)->update([
            'history_import_status'     => 'in_progress',
            'history_import_days'       => $this->days,
            'history_import_started_at' => now(),
            'history_import_total'      => 0,
            'history_import_processed'  => 0,
            'updated_at'               => now(),
        ]);

        try {
            $processed = $this->fetchHistory($account);

            DB::table('email_accounts')->where('id', $this->accountId)->update([
                'history_import_status'       => 'completed',
                'history_import_processed'    => $processed,
                'history_import_completed_at' => now(),
                'updated_at'                 => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("FetchEmailHistoryJob failed for account {$this->accountId}: " . $e->getMessage());

            DB::table('email_accounts')->where('id', $this->accountId)->update([
                'history_import_status'       => 'failed',
                'last_error'                  => 'History import failed: ' . $e->getMessage(),
                'history_import_completed_at' => now(),
                'updated_at'                 => now(),
            ]);
        }
    }

    /**
     * Fetch historical emails via IMAP.
     *
     * In production, this connects to the IMAP server and fetches
     * messages from the specified time range. For now, it processes
     * existing unmatched emails as a simulation.
     */
    private function fetchHistory(object $account): int
    {
        $since = now()->subDays($this->days);
        $processed = 0;

        // Count existing emails in the time range that could be matched
        $emails = DB::table('emails')
            ->where('created_at', '>=', $since)
            ->whereNull('person_id')
            ->get();

        DB::table('email_accounts')->where('id', $this->accountId)->update([
            'history_import_total' => $emails->count(),
            'updated_at'          => now(),
        ]);

        $filterRules = json_decode($account->filter_rules ?? '[]', true) ?: [];
        $contactOnly = (bool) ($account->contact_only ?? true);

        foreach ($emails as $email) {
            // Apply filter rules
            if ($this->shouldFilterEmail($email, $filterRules)) {
                $processed++;
                $this->updateProgress($processed);

                continue;
            }

            // Try to match to a CRM contact
            $matched = $this->matchEmailToContact($email);

            if (! $contactOnly || $matched) {
                $processed++;
            } else {
                $processed++;
            }

            $this->updateProgress($processed);
        }

        return $processed;
    }

    /**
     * Update import progress.
     */
    private function updateProgress(int $processed): void
    {
        // Update progress every 10 emails to reduce DB writes
        if ($processed % 10 === 0 || $processed <= 1) {
            DB::table('email_accounts')->where('id', $this->accountId)->update([
                'history_import_processed' => $processed,
                'updated_at'              => now(),
            ]);
        }
    }

    /**
     * Match an email to a CRM contact by email address.
     */
    private function matchEmailToContact(object $email): bool
    {
        $fromAddresses = json_decode($email->from, true) ?? [];
        $senderAddresses = json_decode($email->sender, true) ?? [];

        $emailAddresses = array_merge(
            array_column($fromAddresses, 'address'),
            array_column($senderAddresses, 'address'),
            $fromAddresses,
            $senderAddresses
        );

        $emailAddresses = array_filter(array_unique($emailAddresses), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));

        foreach ($emailAddresses as $address) {
            $person = DB::table('persons')
                ->whereRaw('emails LIKE ?', ['%' . $address . '%'])
                ->first();

            if ($person) {
                DB::table('emails')->where('id', $email->id)->update([
                    'person_id'  => $person->id,
                    'updated_at' => now(),
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * Check if an email should be filtered out based on rules.
     */
    private function shouldFilterEmail(object $email, array $rules): bool
    {
        if (empty($rules)) {
            return false;
        }

        $fromAddresses = json_decode($email->from, true) ?? [];
        $senderEmails = array_merge(
            array_column($fromAddresses, 'address'),
            $fromAddresses
        );
        $senderEmails = array_filter(array_unique($senderEmails), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
        $senderDomains = array_map(fn ($e) => strtolower(explode('@', $e)[1] ?? ''), $senderEmails);

        $hasAllowRule = false;
        $passesAllow = false;

        foreach ($rules as $rule) {
            $type = $rule['type'] ?? '';
            $value = strtolower($rule['value'] ?? '');

            switch ($type) {
                case 'block_domain':
                    if (in_array($value, $senderDomains)) {
                        return true;
                    }
                    break;
                case 'block_sender':
                    if (in_array($value, array_map('strtolower', $senderEmails))) {
                        return true;
                    }
                    break;
                case 'block_subject_pattern':
                    if ($email->subject && stripos($email->subject, $rule['value']) !== false) {
                        return true;
                    }
                    break;
                case 'allow_domain':
                    $hasAllowRule = true;
                    if (in_array($value, $senderDomains)) {
                        $passesAllow = true;
                    }
                    break;
                case 'allow_sender':
                    $hasAllowRule = true;
                    if (in_array($value, array_map('strtolower', $senderEmails))) {
                        $passesAllow = true;
                    }
                    break;
            }
        }

        if ($hasAllowRule && ! $passesAllow) {
            return true;
        }

        return false;
    }
}
