<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class MatchEventResource extends CamelCaseResource
{
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->id,
            'match_id' => $this->match_id,
            'match_sheet_id' => $this->match_sheet_id,
            'team_id' => $this->team_id,
            'player_id' => $this->player_id,
            'related_player_id' => $this->related_player_id,
            'type' => $this->type,
            'minute' => $this->minute,
            'notes' => $this->notes,
            'player' => $this->when(
                $this->relationLoaded('player') && $this->player,
                fn () => new PlayerResource($this->player)
            ),
            'related_player' => $this->when(
                $this->relationLoaded('relatedPlayer') && $this->relatedPlayer,
                fn () => new PlayerResource($this->relatedPlayer)
            ),
            'team' => $this->when(
                $this->relationLoaded('team') && $this->team,
                fn () => new TeamResource($this->team)
            ),
            'created_at' => optional($this->created_at)?->toISOString(),
        ];
    }
}
