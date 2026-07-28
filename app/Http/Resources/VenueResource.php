<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class VenueResource extends CamelCaseResource
{
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'address' => $this->address,
            'department' => $this->department,
            'city' => $this->city,
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
