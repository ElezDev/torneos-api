<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TournamentPostResource;
use App\Models\GameMatch;
use App\Models\Tournament;
use App\Models\TournamentPost;
use App\Services\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TournamentPostController extends Controller
{
    public function index(Request $request, Tournament $tournament): AnonymousResourceCollection
    {
        abort_unless($tournament->tenant_id === $this->tenantId($request), 404);

        $posts = TournamentPost::query()
            ->where('tournament_id', $tournament->id)
            ->with(['user', 'match.homeTeam', 'match.awayTeam'])
            ->latest()
            ->get();

        return TournamentPostResource::collection($posts);
    }

    public function store(Request $request, Tournament $tournament, MediaStorageService $media): JsonResponse
    {
        abort_unless($tournament->tenant_id === $this->tenantId($request), 404);

        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:2000'],
            'match_id' => ['nullable', 'integer'],
            'image' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        if (empty($data['caption']) && ! $request->hasFile('image')) {
            return response()->json([
                'message' => 'Agrega una imagen o un texto para publicar.',
                'errors' => [
                    'caption' => ['Agrega una imagen o un texto para publicar.'],
                ],
            ], 422);
        }

        if (! empty($data['match_id'])) {
            $match = GameMatch::query()->findOrFail($data['match_id']);
            abort_unless(
                $match->tenant_id === $tournament->tenant_id && $match->tournament_id === $tournament->id,
                422,
                'El partido no pertenece a este torneo.'
            );
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $media->storeImage(
                $data['image'],
                "tenants/{$tournament->tenant_id}/tournaments/{$tournament->id}/posts"
            );
        }

        $post = TournamentPost::create([
            'tenant_id' => $tournament->tenant_id,
            'tournament_id' => $tournament->id,
            'match_id' => $data['match_id'] ?? null,
            'user_id' => $request->user()->id,
            'caption' => $data['caption'] ?? null,
            'image_path' => $imagePath,
        ]);

        $post->load(['user', 'match.homeTeam', 'match.awayTeam']);

        return (new TournamentPostResource($post))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, Tournament $tournament, TournamentPost $post, MediaStorageService $media): JsonResponse
    {
        abort_unless($tournament->tenant_id === $this->tenantId($request), 404);
        abort_unless($post->tournament_id === $tournament->id, 404);

        $media->delete($post->image_path);
        $post->delete();

        return response()->json(null, 204);
    }
}
