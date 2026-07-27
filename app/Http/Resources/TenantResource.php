<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class TenantResource extends CamelCaseResource
{
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_active' => $this->is_active,
            'is_owner' => $this->whenPivotLoaded('tenant_user', fn () => (bool) $this->pivot->is_owner),
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
