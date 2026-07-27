<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class TournamentGroupResource extends CamelCaseResource
{
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'tournament_id' => $this->tournament_id,
            'name' => $this->name,
            'sort_order' => $this->sort_order,
            'teams_count' => $this->when(isset($this->teams_count), $this->teams_count),
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
