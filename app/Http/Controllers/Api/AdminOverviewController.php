<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOverviewController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $tenants = Tenant::query()
            ->withCount(['tournaments', 'users'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => [
                'summary' => [
                    'tenantsCount' => Tenant::count(),
                    'activeTenants' => Tenant::where('is_active', true)->count(),
                    'tournamentsCount' => Tournament::count(),
                    'usersCount' => User::count(),
                ],
                'tenants' => TenantResource::collection($tenants)->resolve(),
            ],
        ]);
    }
}
