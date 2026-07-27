<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class MatchSheetResource extends CamelCaseResource
{
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'match_id' => $this->match_id,
            'team_id' => $this->team_id,
            'status' => $this->status,
            'delegate_name' => $this->delegate_name,
            'observations' => $this->observations,
            'closed_at' => optional($this->closed_at)?->toISOString(),
            'team' => $this->when(
                $this->relationLoaded('team') && $this->team,
                fn () => new TeamResource($this->team)
            ),
            'players' => $this->when(
                $this->relationLoaded('players'),
                fn () => $this->players->map(fn ($row) => [
                    'id' => $row->id,
                    'playerId' => $row->player_id,
                    'jerseyNumber' => $row->jersey_number,
                    'isStarter' => $row->is_starter,
                    'player' => $row->relationLoaded('player') && $row->player
                        ? (new PlayerResource($row->player))->resolve()
                        : null,
                ])->values()->all()
            ),
        ];
    }
}
