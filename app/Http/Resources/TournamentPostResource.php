<?php

namespace App\Http\Resources;

use App\Services\MediaStorageService;
use Illuminate\Http\Request;

class TournamentPostResource extends CamelCaseResource
{
    protected function payload(Request $request): array
    {
        /** @var MediaStorageService $media */
        $media = app(MediaStorageService::class);

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'tournament_id' => $this->tournament_id,
            'match_id' => $this->match_id,
            'user_id' => $this->user_id,
            'caption' => $this->caption,
            'image_path' => $this->image_path,
            'image_url' => $media->url($this->image_path),
            'user' => $this->when(
                $this->relationLoaded('user') && $this->user,
                fn () => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ]
            ),
            'match' => $this->when(
                $this->relationLoaded('match') && $this->match,
                fn () => (new MatchResource($this->match))->resolve()
            ),
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
