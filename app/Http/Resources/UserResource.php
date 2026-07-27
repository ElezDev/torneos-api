<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class UserResource extends CamelCaseResource
{
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->whenLoaded(
                'roles',
                fn () => $this->getRoleNames()->values()->all()
            ),
            'permissions' => $this->when(
                $this->relationLoaded('roles') || $this->relationLoaded('permissions'),
                fn () => $this->getAllPermissions()->pluck('name')->values()->all()
            ),
            'tenants' => $this->whenLoaded(
                'tenants',
                fn () => TenantResource::collection($this->tenants)
            ),
            'created_at' => optional($this->created_at)?->toISOString(),
        ];
    }
}
