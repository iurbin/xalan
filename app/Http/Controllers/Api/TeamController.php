<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index(): JsonResponse
    {
        $members = User::query()
            ->where('tenant_id', app(TenantContext::class)->id())
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name', 'email', 'role', 'created_at']);

        return response()->json($members);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['admin', 'technician'])],
        ]);

        $member = $request->user()->tenant()->firstOrFail()->users()->create($validated);

        return response()->json([
            'id' => $member->id,
            'tenant_id' => $member->tenant_id,
            'name' => $member->name,
            'email' => $member->email,
            'role' => $member->role,
            'created_at' => $member->created_at,
        ], 201);
    }
}
