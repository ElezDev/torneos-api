<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Http\Resources\TournamentResource;
use App\Models\GameMatch;
use App\Models\Tournament;
use App\Services\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaBannerController extends Controller
{
    public function uploadTournamentBanner(
        Request $request,
        Tournament $tournament,
        MediaStorageService $media
    ): JsonResponse {
        abort_unless($tournament->tenant_id === $this->tenantId($request), 404);

        $data = $request->validate([
            'image' => ['required', 'file', 'image', 'max:5120'],
        ]);

        $path = $media->replace(
            $tournament->banner_path,
            $data['image'],
            "tenants/{$tournament->tenant_id}/tournaments/{$tournament->id}"
        );

        $tournament->update(['banner_path' => $path]);

        return response()->json([
            'data' => (new TournamentResource($tournament->fresh()))->resolve(),
        ]);
    }

    public function deleteTournamentBanner(
        Request $request,
        Tournament $tournament,
        MediaStorageService $media
    ): JsonResponse {
        abort_unless($tournament->tenant_id === $this->tenantId($request), 404);

        $media->delete($tournament->banner_path);
        $tournament->update(['banner_path' => null]);

        return response()->json([
            'data' => (new TournamentResource($tournament->fresh()))->resolve(),
        ]);
    }

    public function uploadMatchBanner(
        Request $request,
        GameMatch $match,
        MediaStorageService $media
    ): JsonResponse {
        abort_unless($match->tenant_id === $this->tenantId($request), 404);

        $data = $request->validate([
            'image' => ['required', 'file', 'image', 'max:5120'],
        ]);

        $path = $media->replace(
            $match->banner_path,
            $data['image'],
            "tenants/{$match->tenant_id}/matches/{$match->id}"
        );

        $match->update(['banner_path' => $path]);

        return response()->json([
            'data' => (new MatchResource($match->fresh()))->resolve(),
        ]);
    }

    public function deleteMatchBanner(
        Request $request,
        GameMatch $match,
        MediaStorageService $media
    ): JsonResponse {
        abort_unless($match->tenant_id === $this->tenantId($request), 404);

        $media->delete($match->banner_path);
        $match->update(['banner_path' => null]);

        return response()->json([
            'data' => (new MatchResource($match->fresh()))->resolve(),
        ]);
    }
}
