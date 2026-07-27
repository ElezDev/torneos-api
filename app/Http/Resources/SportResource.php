<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class SportResource extends CamelCaseResource
{
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'scoring_label' => $this->scoring_label,
            'is_active' => $this->is_active,
        ];
    }
}
