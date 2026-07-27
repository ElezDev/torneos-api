<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class TeamResource extends CamelCaseResource
{
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'tournament_id' => $this->tournament_id,
            'tournament_group_id' => $this->tournament_group_id,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'logo_path' => $this->logo_path,
            'group' => $this->when(
                $this->relationLoaded('group') && $this->group,
                fn () => new TournamentGroupResource($this->group)
            ),
            'players' => $this->whenLoaded(
                'players',
                fn () => PlayerResource::collection($this->players)
            ),
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
