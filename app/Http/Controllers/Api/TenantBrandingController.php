<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantBrandingController extends Controller
{
    public function uploadLogo(Request $request, Tenant $tenant, MediaStorageService $media): JsonResponse
    {
        $this->assertOwner($request, $tenant);

        $data = $request->validate([
            'image' => ['required', 'file', 'image', 'max:5120'],
        ]);

        $path = $media->replace(
            $tenant->logo_path,
            $data['image'],
            "tenants/{$tenant->id}/branding"
        );

        $tenant->update(['logo_path' => $path]);

        return $this->tenantResponse($request, $tenant);
    }

    public function uploadLoginImage(Request $request, Tenant $tenant, MediaStorageService $media): JsonResponse
    {
        $this->assertOwner($request, $tenant);

        $data = $request->validate([
            'image' => ['required', 'file', 'image', 'max:5120'],
        ]);

        $path = $media->replace(
            $tenant->login_image_path,
            $data['image'],
            "tenants/{$tenant->id}/branding"
        );

        $tenant->update(['login_image_path' => $path]);

        return $this->tenantResponse($request, $tenant);
    }

    public function deleteLogo(Request $request, Tenant $tenant, MediaStorageService $media): JsonResponse
    {
        $this->assertOwner($request, $tenant);
        $media->delete($tenant->logo_path);
        $tenant->update(['logo_path' => null]);

        return $this->tenantResponse($request, $tenant);
    }

    public function deleteLoginImage(Request $request, Tenant $tenant, MediaStorageService $media): JsonResponse
    {
        $this->assertOwner($request, $tenant);
        $media->delete($tenant->login_image_path);
        $tenant->update(['login_image_path' => null]);

        return $this->tenantResponse($request, $tenant);
    }

    private function assertOwner(Request $request, Tenant $tenant): void
    {
        abort_unless($request->user()->belongsToTenant($tenant->id), 404);

        $membership = $request->user()->tenants()->where('tenants.id', $tenant->id)->first();
        abort_unless((bool) $membership?->pivot?->is_owner, 403, 'Solo el dueño puede editar la marca.');
    }

    private function tenantResponse(Request $request, Tenant $tenant): JsonResponse
    {
        $membership = $request->user()->tenants()->where('tenants.id', $tenant->id)->firstOrFail();

        return response()->json([
            'data' => (new TenantResource($membership))->resolve(),
        ]);
    }
}
