<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class SharedInboxController extends Controller
{
    /**
     * List shared inboxes the current user has access to.
     */
    public function index(): JsonResponse
    {
        $userId = auth()->id();

        // Get accounts where user is owner or a member
        $accounts = DB::table('email_accounts')
            ->leftJoin('email_account_members', 'email_accounts.id', '=', 'email_account_members.email_account_id')
            ->where(function ($q) use ($userId) {
                $q->where('email_accounts.user_id', $userId)
                    ->orWhere('email_account_members.user_id', $userId);
            })
            ->select(
                'email_accounts.id',
                'email_accounts.email_address',
                'email_accounts.display_name',
                'email_accounts.provider',
                'email_accounts.status',
                'email_accounts.last_sync_at',
                'email_accounts.user_id as owner_id',
            )
            ->distinct()
            ->get()
            ->map(function ($a) use ($userId) {
                $memberCount = DB::table('email_account_members')
                    ->where('email_account_id', $a->id)
                    ->count();

                $role = $a->owner_id == $userId ? 'owner' : (
                    DB::table('email_account_members')
                        ->where('email_account_id', $a->id)
                        ->where('user_id', $userId)
                        ->value('role') ?? 'member'
                );

                return [
                    'id'            => $a->id,
                    'email_address' => $a->email_address,
                    'display_name'  => $a->display_name,
                    'provider'      => $a->provider,
                    'status'        => $a->status,
                    'last_sync_at'  => $a->last_sync_at,
                    'member_count'  => $memberCount + 1, // +1 for owner
                    'your_role'     => $role,
                    'is_shared'     => $memberCount > 0,
                ];
            });

        return response()->json(['data' => $accounts]);
    }

    /**
     * Get aggregated shared inbox emails across team accounts.
     */
    public function emails(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $limit = min($request->input('limit', 25), 100);
        $page = max($request->input('page', 1), 1);

        // Get all account IDs user has access to
        $accountIds = $this->getUserAccountIds($userId);

        if (empty($accountIds)) {
            return response()->json(['data' => []]);
        }

        $query = DB::table('emails')
            ->select(
                'emails.id',
                'emails.subject',
                'emails.from',
                'emails.sender',
                'emails.is_read',
                'emails.folders',
                'emails.person_id',
                'emails.assigned_to',
                'emails.created_at',
                'emails.parent_id',
            );

        // Filter by account email addresses
        $accountEmails = DB::table('email_accounts')
            ->whereIn('id', $accountIds)
            ->pluck('email_address')
            ->toArray();

        $query->where(function ($q) use ($accountEmails) {
            foreach ($accountEmails as $email) {
                $q->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(`from`, '$[0]')) = ?", [$email])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(sender, '$[0]')) = ?", [$email]);
            }
        });

        // Optional filters
        if ($request->has('assigned_to')) {
            $assignedTo = $request->input('assigned_to');
            if ($assignedTo === 'unassigned') {
                $query->whereNull('emails.assigned_to');
            } else {
                $query->where('emails.assigned_to', $assignedTo);
            }
        }

        if ($request->has('account_id')) {
            $accId = $request->input('account_id');
            if (in_array($accId, $accountIds)) {
                $accEmail = DB::table('email_accounts')->where('id', $accId)->value('email_address');
                if ($accEmail) {
                    $query->where(function ($q) use ($accEmail) {
                        $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(`from`, '$[0]')) = ?", [$accEmail])
                            ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(sender, '$[0]')) = ?", [$accEmail]);
                    });
                }
            }
        }

        $emails = $query->orderByDesc('emails.created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(function ($e) use ($userId) {
                // Check per-user read status
                $readStatus = DB::table('email_read_status')
                    ->where('email_id', $e->id)
                    ->where('user_id', $userId)
                    ->first();

                $assignedUser = null;
                if ($e->assigned_to) {
                    $assignedUser = DB::table('users')
                        ->where('id', $e->assigned_to)
                        ->select('id', 'name', 'email')
                        ->first();
                }

                return [
                    'id'          => $e->id,
                    'subject'     => $e->subject,
                    'from'        => json_decode($e->from, true),
                    'is_read'     => $readStatus ? true : (bool) $e->is_read,
                    'folders'     => json_decode($e->folders, true),
                    'person_id'   => $e->person_id,
                    'assigned_to' => $assignedUser ? [
                        'id'    => $assignedUser->id,
                        'name'  => $assignedUser->name,
                        'email' => $assignedUser->email,
                    ] : null,
                    'parent_id'   => $e->parent_id,
                    'created_at'  => $e->created_at,
                ];
            });

        return response()->json(['data' => $emails]);
    }

    /**
     * Add a member to an email account (share it).
     */
    public function addMember(Request $request, int $accountId): JsonResponse
    {
        $userId = auth()->id();

        // Only account owner can add members
        $account = DB::table('email_accounts')
            ->where('id', $accountId)
            ->where('user_id', $userId)
            ->first();

        if (! $account) {
            return response()->json(['message' => 'Email account not found or not owned by you'], 404);
        }

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role'    => 'sometimes|string|in:admin,member',
        ]);

        $memberId = $request->input('user_id');

        if ($memberId == $userId) {
            return response()->json(['message' => 'Cannot add yourself as a member'], 422);
        }

        // Check if already a member
        $exists = DB::table('email_account_members')
            ->where('email_account_id', $accountId)
            ->where('user_id', $memberId)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'User is already a member'], 422);
        }

        DB::table('email_account_members')->insert([
            'email_account_id' => $accountId,
            'user_id'          => $memberId,
            'role'             => $request->input('role', 'member'),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $user = DB::table('users')->where('id', $memberId)->select('id', 'name', 'email')->first();

        return response()->json([
            'data' => [
                'user_id'          => $user->id,
                'name'             => $user->name,
                'email'            => $user->email,
                'role'             => $request->input('role', 'member'),
                'email_account_id' => $accountId,
            ],
            'message' => 'Member added to shared inbox.',
        ], 201);
    }

    /**
     * Remove a member from an email account.
     */
    public function removeMember(int $accountId, int $memberId): JsonResponse
    {
        $userId = auth()->id();

        // Only account owner can remove members
        $account = DB::table('email_accounts')
            ->where('id', $accountId)
            ->where('user_id', $userId)
            ->first();

        if (! $account) {
            return response()->json(['message' => 'Email account not found or not owned by you'], 404);
        }

        $deleted = DB::table('email_account_members')
            ->where('email_account_id', $accountId)
            ->where('user_id', $memberId)
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Member not found'], 404);
        }

        return response()->json(['message' => 'Member removed from shared inbox.']);
    }

    /**
     * List members of a shared inbox.
     */
    public function members(int $accountId): JsonResponse
    {
        $userId = auth()->id();

        // User must have access to this account
        if (! $this->userHasAccess($userId, $accountId)) {
            return response()->json(['message' => 'Email account not found'], 404);
        }

        $account = DB::table('email_accounts')->where('id', $accountId)->first();

        $owner = DB::table('users')
            ->where('id', $account->user_id)
            ->select('id', 'name', 'email')
            ->first();

        $members = DB::table('email_account_members')
            ->join('users', 'email_account_members.user_id', '=', 'users.id')
            ->where('email_account_members.email_account_id', $accountId)
            ->select('users.id', 'users.name', 'users.email', 'email_account_members.role')
            ->get()
            ->map(fn ($m) => [
                'id'    => $m->id,
                'name'  => $m->name,
                'email' => $m->email,
                'role'  => $m->role,
            ]);

        $allMembers = collect([[
            'id'    => $owner->id,
            'name'  => $owner->name,
            'email' => $owner->email,
            'role'  => 'owner',
        ]])->concat($members);

        return response()->json(['data' => $allMembers->values()]);
    }

    /**
     * Assign an email thread to a team member.
     */
    public function assign(Request $request, int $emailId): JsonResponse
    {
        $userId = auth()->id();

        $request->validate([
            'assigned_to' => 'nullable|integer|exists:users,id',
        ]);

        $email = DB::table('emails')->where('id', $emailId)->first();

        if (! $email) {
            return response()->json(['message' => 'Email not found'], 404);
        }

        $assignTo = $request->input('assigned_to');

        DB::table('emails')->where('id', $emailId)->update([
            'assigned_to' => $assignTo,
            'updated_at'  => now(),
        ]);

        // Also assign all emails in the thread
        if ($email->parent_id) {
            DB::table('emails')
                ->where('parent_id', $email->parent_id)
                ->orWhere('id', $email->parent_id)
                ->update(['assigned_to' => $assignTo, 'updated_at' => now()]);
        } else {
            DB::table('emails')
                ->where('parent_id', $emailId)
                ->update(['assigned_to' => $assignTo, 'updated_at' => now()]);
        }

        $assignedUser = null;
        if ($assignTo) {
            $assignedUser = DB::table('users')->where('id', $assignTo)->select('id', 'name', 'email')->first();
        }

        return response()->json([
            'data' => [
                'email_id'    => $emailId,
                'assigned_to' => $assignedUser ? [
                    'id'    => $assignedUser->id,
                    'name'  => $assignedUser->name,
                    'email' => $assignedUser->email,
                ] : null,
            ],
            'message' => $assignTo ? 'Email assigned.' : 'Email unassigned.',
        ]);
    }

    /**
     * Mark email as read for the current user.
     */
    public function markRead(int $emailId): JsonResponse
    {
        $userId = auth()->id();

        $email = DB::table('emails')->where('id', $emailId)->first();

        if (! $email) {
            return response()->json(['message' => 'Email not found'], 404);
        }

        DB::table('email_read_status')->updateOrInsert(
            ['email_id' => $emailId, 'user_id' => $userId],
            ['read_at' => now()]
        );

        return response()->json(['message' => 'Email marked as read.']);
    }

    /**
     * Get email account IDs the user has access to.
     */
    private function getUserAccountIds(int $userId): array
    {
        $owned = DB::table('email_accounts')
            ->where('user_id', $userId)
            ->pluck('id')
            ->toArray();

        $shared = DB::table('email_account_members')
            ->where('user_id', $userId)
            ->pluck('email_account_id')
            ->toArray();

        return array_unique(array_merge($owned, $shared));
    }

    /**
     * Check if user has access to an email account.
     */
    private function userHasAccess(int $userId, int $accountId): bool
    {
        return DB::table('email_accounts')
                ->where('id', $accountId)
                ->where('user_id', $userId)
                ->exists()
            || DB::table('email_account_members')
                ->where('email_account_id', $accountId)
                ->where('user_id', $userId)
                ->exists();
    }
}
