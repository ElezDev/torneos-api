<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class StandingResource extends CamelCaseResource
{
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'tournament_id' => $this->tournament_id,
            'tournament_group_id' => $this->tournament_group_id,
            'team_id' => $this->team_id,
            'played' => $this->played,
            'won' => $this->won,
            'drawn' => $this->drawn,
            'lost' => $this->lost,
            'goals_for' => $this->goals_for,
            'goals_against' => $this->goals_against,
            'goal_difference' => $this->goal_difference,
            'points' => $this->points,
            'rank_position' => $this->rank_position,
            'team' => $this->when(
                $this->relationLoaded('team') && $this->team,
                fn () => new TeamResource($this->team)
            ),
        ];
    }
}
