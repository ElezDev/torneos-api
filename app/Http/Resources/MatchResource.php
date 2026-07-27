<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class MatchResource extends CamelCaseResource
{
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'tournament_id' => $this->tournament_id,
            'tournament_group_id' => $this->tournament_group_id,
            'venue_id' => $this->venue_id,
            'home_team_id' => $this->home_team_id,
            'away_team_id' => $this->away_team_id,
            'home_from_match_id' => $this->home_from_match_id,
            'away_from_match_id' => $this->away_from_match_id,
            'home_from_result' => $this->home_from_result,
            'away_from_result' => $this->away_from_result,
            'matchday' => $this->matchday,
            'round_name' => $this->round_name,
            'stage' => $this->stage,
            'bracket_slot' => $this->bracket_slot,
            'bracket_code' => $this->bracket_code,
            'scheduled_at' => optional($this->scheduled_at)?->toISOString(),
            'status' => $this->status,
            'home_score' => $this->home_score,
            'away_score' => $this->away_score,
            'winner_team_id' => $this->winner_team_id,
            'notes' => $this->notes,
            'referee_name' => $this->referee_name,
            'home_placeholder' => $this->placeholderLabel('home'),
            'away_placeholder' => $this->placeholderLabel('away'),
            'home_team' => $this->when(
                $this->relationLoaded('homeTeam') && $this->homeTeam,
                fn () => new TeamResource($this->homeTeam)
            ),
            'away_team' => $this->when(
                $this->relationLoaded('awayTeam') && $this->awayTeam,
                fn () => new TeamResource($this->awayTeam)
            ),
            'venue' => $this->when(
                $this->relationLoaded('venue') && $this->venue,
                fn () => new VenueResource($this->venue)
            ),
            'group' => $this->when(
                $this->relationLoaded('group') && $this->group,
                fn () => new TournamentGroupResource($this->group)
            ),
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }

    private function placeholderLabel(string $side): ?string
    {
        $fromId = $side === 'home' ? $this->home_from_match_id : $this->away_from_match_id;
        $result = $side === 'home' ? $this->home_from_result : $this->away_from_result;
        if (! $fromId) {
            return null;
        }

        $prefix = $result === 'loser' ? 'Perdedor' : 'Ganador';
        $source = null;
        if ($side === 'home' && $this->relationLoaded('homeFromMatch') && $this->homeFromMatch) {
            $source = $this->homeFromMatch;
        }
        if ($side === 'away' && $this->relationLoaded('awayFromMatch') && $this->awayFromMatch) {
            $source = $this->awayFromMatch;
        }

        $slot = $source?->bracket_slot ?? $fromId;

        return "{$prefix} partido {$slot}";
    }
}
