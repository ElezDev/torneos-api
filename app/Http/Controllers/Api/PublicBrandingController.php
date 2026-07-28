<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\MediaStorageService;
use Illuminate\Http\JsonResponse;

class PublicBrandingController extends Controller
{
    public function show(string $tenantSlug, MediaStorageService $media): JsonResponse
    {
        $tenant = Tenant::query()
            ->where('slug', $tenantSlug)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'logoUrl' => $media->url($tenant->logo_path),
                'loginImageUrl' => $media->url($tenant->login_image_path),
            ],
        ]);
    }
}
