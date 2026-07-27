<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentTenant
{
    public const HEADER = 'X-Tenant-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->header(self::HEADER) ?? $request->query('tenantId');

        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant required. Send header X-Tenant-Id.',
            ], 400);
        }

        $user = $request->user();

        if (! $user || ! $user->belongsToTenant((int) $tenantId)) {
            return response()->json([
                'message' => 'You do not belong to this tenant.',
            ], 403);
        }

        $tenant = Tenant::query()
            ->whereKey($tenantId)
            ->where('is_active', true)
            ->first();

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant not found or inactive.',
            ], 404);
        }

        $request->attributes->set('tenant', $tenant);
        app()->instance('currentTenant', $tenant);

        return $next($request);
    }
}
