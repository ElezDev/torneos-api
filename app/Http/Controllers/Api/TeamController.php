<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeamController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Team::query()
            ->forTenant($this->tenantId($request))
            ->with(['group'])
            ->orderBy('name');

        if ($request->filled('tournament_id')) {
            $query->where('tournament_id', $request->integer('tournament_id'));
        }

        return TeamResource::collection($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tournament_id' => ['required', 'integer'],
            'tournament_group_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'short_name' => ['nullable', 'string', 'max:20'],
            'logo_path' => ['nullable', 'string', 'max:255'],
        ]);

        $tenantId = $this->tenantId($request);

        $tournament = Tournament::query()
            ->forTenant($tenantId)
            ->whereKey($data['tournament_id'])
            ->firstOrFail();

        if (! empty($data['tournament_group_id'])) {
            TournamentGroup::query()
                ->forTenant($tenantId)
                ->where('tournament_id', $tournament->id)
                ->whereKey($data['tournament_group_id'])
                ->firstOrFail();
        }

        $team = Team::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        $team->load('group');

        return (new TeamResource($team))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Team $team): TeamResource
    {
        $this->assertTenant($request, $team->tenant_id);
        $team->load(['group', 'players']);

        return new TeamResource($team);
    }

    public function update(Request $request, Team $team): TeamResource
    {
        $this->assertTenant($request, $team->tenant_id);

        $data = $request->validate([
            'tournament_group_id' => ['nullable', 'integer'],
            'name' => ['sometimes', 'string', 'max:150'],
            'short_name' => ['nullable', 'string', 'max:20'],
            'logo_path' => ['nullable', 'string', 'max:255'],
        ]);

        if (array_key_exists('tournament_group_id', $data) && $data['tournament_group_id']) {
            TournamentGroup::query()
                ->forTenant($this->tenantId($request))
                ->where('tournament_id', $team->tournament_id)
                ->whereKey($data['tournament_group_id'])
                ->firstOrFail();
        }

        $team->update($data);
        $team->load(['group', 'players']);

        return new TeamResource($team);
    }

    public function destroy(Request $request, Team $team): JsonResponse
    {
        $this->assertTenant($request, $team->tenant_id);
        $team->delete();

        return response()->json(null, 204);
    }

    private function assertTenant(Request $request, int $tenantId): void
    {
        abort_unless($tenantId === $this->tenantId($request), 404);
    }
}
