<?php

namespace App\Http\Resources;

use App\Services\MediaStorageService;
use Illuminate\Http\Request;

class TenantResource extends CamelCaseResource
{
    protected function payload(Request $request): array
    {
        /** @var MediaStorageService $media */
        $media = app(MediaStorageService::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_path' => $this->logo_path,
            'logo_url' => $media->url($this->logo_path),
            'login_image_path' => $this->login_image_path,
            'login_image_url' => $media->url($this->login_image_path),
            'is_active' => $this->is_active,
            'is_owner' => $this->whenPivotLoaded('tenant_user', fn () => (bool) $this->pivot->is_owner),
            'tournaments_count' => $this->when(isset($this->tournaments_count), fn () => $this->tournaments_count),
            'users_count' => $this->when(isset($this->users_count), fn () => $this->users_count),
            'owner' => $this->when(
                $this->relationLoaded('users'),
                function () {
                    $owner = $this->users->first();

                    return $owner ? [
                        'id' => $owner->id,
                        'name' => $owner->name,
                        'email' => $owner->email,
                    ] : null;
                }
            ),
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
