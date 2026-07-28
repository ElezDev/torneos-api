<?php

namespace App\Http\Resources;

use App\Services\MediaStorageService;
use Illuminate\Http\Request;

class TournamentResource extends CamelCaseResource
{
    protected function payload(Request $request): array
    {
        /** @var MediaStorageService $media */
        $media = app(MediaStorageService::class);

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'sport_id' => $this->sport_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'format' => $this->format,
            'status' => $this->status,
            'season_label' => $this->season_label,
            'starts_on' => optional($this->starts_on)?->toDateString(),
            'ends_on' => optional($this->ends_on)?->toDateString(),
            'is_public' => $this->is_public,
            'banner_path' => $this->banner_path,
            'banner_url' => $media->url($this->banner_path),
            'points_config' => $this->points_config,
            'sanction_rules' => $this->sanction_rules,
            'tiebreaker_rules' => $this->tiebreaker_rules,
            'format_config' => $this->format_config,
            'sport' => $this->when(
                $this->relationLoaded('sport') && $this->sport,
                fn () => new SportResource($this->sport)
            ),
            'groups' => $this->whenLoaded(
                'groups',
                fn () => TournamentGroupResource::collection($this->groups)
            ),
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
