<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Http\Resources\PlayerResource;
use App\Http\Resources\StandingResource;
use App\Http\Resources\TournamentResource;
use App\Models\GameMatch;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\Standing;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PublicTournamentController extends Controller
{
    public function show(string $tenantSlug, string $tournamentSlug): TournamentResource|JsonResponse
    {
        $tournament = $this->findPublicTournament($tenantSlug, $tournamentSlug);

        if (! $tournament) {
            return response()->json(['message' => 'Tournament not found'], 404);
        }

        $tournament->load(['sport', 'groups']);

        return new TournamentResource($tournament);
    }

    public function matches(string $tenantSlug, string $tournamentSlug): AnonymousResourceCollection|JsonResponse
    {
        $tournament = $this->findPublicTournament($tenantSlug, $tournamentSlug);

        if (! $tournament) {
            return response()->json(['message' => 'Tournament not found'], 404);
        }

        $matches = GameMatch::query()
            ->where('tournament_id', $tournament->id)
            ->with(['homeTeam', 'awayTeam', 'venue'])
            ->orderBy('scheduled_at')
            ->get();

        return MatchResource::collection($matches);
    }

    public function standings(string $tenantSlug, string $tournamentSlug, Request $request): AnonymousResourceCollection|JsonResponse
    {
        $tournament = $this->findPublicTournament($tenantSlug, $tournamentSlug);

        if (! $tournament) {
            return response()->json(['message' => 'Tournament not found'], 404);
        }

        $query = Standing::query()
            ->where('tournament_id', $tournament->id)
            ->with('team')
            ->orderByDesc('points')
            ->orderByDesc('goal_difference')
            ->orderByDesc('goals_for');

        if ($request->filled('tournament_group_id') || $request->filled('tournamentGroupId')) {
            $groupId = $request->input('tournament_group_id', $request->input('tournamentGroupId'));
            $query->where('tournament_group_id', $groupId);
        }

        return StandingResource::collection($query->get());
    }

    public function scorers(string $tenantSlug, string $tournamentSlug): JsonResponse
    {
        $tournament = $this->findPublicTournament($tenantSlug, $tournamentSlug);

        if (! $tournament) {
            return response()->json(['message' => 'Tournament not found'], 404);
        }

        $rows = MatchEvent::query()
            ->select('player_id', DB::raw('COUNT(*) as goals'))
            ->whereIn('match_id', GameMatch::query()->where('tournament_id', $tournament->id)->select('id'))
            ->where('type', 'goal')
            ->groupBy('player_id')
            ->orderByDesc('goals')
            ->limit(50)
            ->get();

        $players = Player::query()
            ->whereIn('id', $rows->pluck('player_id'))
            ->get()
            ->keyBy('id');

        $data = $rows->map(function ($row) use ($players) {
            $player = $players->get($row->player_id);

            return [
                'playerId' => $row->player_id,
                'goals' => (int) $row->goals,
                'player' => $player ? (new PlayerResource($player))->resolve() : null,
            ];
        });

        return response()->json(['data' => $data]);
    }

    private function findPublicTournament(string $tenantSlug, string $tournamentSlug): ?Tournament
    {
        return Tournament::query()
            ->where('slug', $tournamentSlug)
            ->where('is_public', true)
            ->whereHas('tenant', fn ($q) => $q->where('slug', $tenantSlug)->where('is_active', true))
            ->first();
    }
}
