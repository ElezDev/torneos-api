<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PlayerResource extends CamelCaseResource
{
    protected function payload(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'team_id' => $this->team_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'jersey_number' => $this->jersey_number,
            'document_id' => $this->document_id,
            'birth_date' => optional($this->birth_date)?->toDateString(),
            'status' => $this->status,
            'yellow_cards_count' => $this->yellow_cards_count,
            'red_cards_count' => $this->red_cards_count,
            'suspension_matches_left' => $this->suspension_matches_left,
            'team' => $this->whenLoaded('team', fn () => [
                'id' => $this->team->id,
                'name' => $this->team->name,
                'short_name' => $this->team->short_name,
                'tournament_id' => $this->team->tournament_id,
            ]),
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
