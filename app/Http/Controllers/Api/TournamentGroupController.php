<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TournamentGroupResource;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TournamentGroupController extends Controller
{
    public function index(Request $request, Tournament $tournament): AnonymousResourceCollection
    {
        $this->assertTenant($request, $tournament->tenant_id);

        $groups = TournamentGroup::query()
            ->where('tournament_id', $tournament->id)
            ->withCount('teams')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return TournamentGroupResource::collection($groups);
    }

    public function store(Request $request, Tournament $tournament): JsonResponse
    {
        $this->assertTenant($request, $tournament->tenant_id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $maxSort = (int) TournamentGroup::query()
            ->where('tournament_id', $tournament->id)
            ->max('sort_order');

        $group = TournamentGroup::create([
            'tenant_id' => $tournament->tenant_id,
            'tournament_id' => $tournament->id,
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? ($maxSort + 1),
        ]);

        if ($tournament->format !== 'groups') {
            $tournament->update(['format' => 'groups']);
        }

        return (new TournamentGroupResource($group->loadCount('teams')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Tournament $tournament, TournamentGroup $group): TournamentGroupResource
    {
        $this->assertTenant($request, $tournament->tenant_id);
        abort_unless($group->tournament_id === $tournament->id, 404);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $group->update($data);

        return new TournamentGroupResource($group->fresh()->loadCount('teams'));
    }

    public function destroy(Request $request, Tournament $tournament, TournamentGroup $group): JsonResponse
    {
        $this->assertTenant($request, $tournament->tenant_id);
        abort_unless($group->tournament_id === $tournament->id, 404);

        Team::query()
            ->where('tournament_group_id', $group->id)
            ->update(['tournament_group_id' => null]);

        $group->delete();

        return response()->json(null, 204);
    }

    private function assertTenant(Request $request, int $tenantId): void
    {
        abort_unless($tenantId === $this->tenantId($request), 404);
    }
}
