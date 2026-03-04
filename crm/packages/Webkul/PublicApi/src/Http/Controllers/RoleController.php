<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    /**
     * List all roles.
     */
    public function index(): JsonResponse
    {
        $roles = DB::table('roles')->get()->map(function ($role) {
            $role->permissions = json_decode($role->permissions, true);
            $role->users_count = DB::table('users')->where('role_id', $role->id)->count();

            return $role;
        });

        return response()->json(['data' => $roles]);
    }

    /**
     * Show a specific role.
     */
    public function show(int $id): JsonResponse
    {
        $role = DB::table('roles')->where('id', $id)->first();

        if (! $role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        $role->permissions = json_decode($role->permissions, true);
        $role->users_count = DB::table('users')->where('role_id', $role->id)->count();

        return response()->json(['data' => $role]);
    }
}
