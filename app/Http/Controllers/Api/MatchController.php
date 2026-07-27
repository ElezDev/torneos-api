<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Models\Venue;
use App\Services\BracketAdvancer;
use App\Services\StandingRecalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MatchController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = GameMatch::query()
            ->forTenant($this->tenantId($request))
            ->with(['homeTeam', 'awayTeam', 'venue', 'group', 'homeFromMatch', 'awayFromMatch'])
            ->orderBy('scheduled_at');

        if ($request->filled('tournament_id')) {
            $query->where('tournament_id', $request->integer('tournament_id'));
        }

        if ($request->filled('tournament_group_id')) {
            $query->where('tournament_group_id', $request->integer('tournament_group_id'));
        }

        return MatchResource::collection($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tournament_id' => ['required', 'integer'],
            'tournament_group_id' => ['nullable', 'integer'],
            'venue_id' => ['nullable', 'integer'],
            'home_team_id' => ['nullable', 'integer'],
            'away_team_id' => ['nullable', 'integer', 'different:home_team_id'],
            'matchday' => ['nullable', 'integer', 'min:1'],
            'round_name' => ['nullable', 'string', 'max:80'],
            'stage' => ['nullable', 'string', 'max:40'],
            'scheduled_at' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['scheduled', 'live', 'finished', 'postponed', 'cancelled'])],
            'notes' => ['nullable', 'string'],
        ]);

        $tenantId = $this->tenantId($request);

        Tournament::query()
            ->forTenant($tenantId)
            ->whereKey($data['tournament_id'])
            ->firstOrFail();

        $this->assertRelated($tenantId, $data);
        $this->assertIntraGroupMatch($data);

        $match = GameMatch::create([
            ...$data,
            'tenant_id' => $tenantId,
            'stage' => $data['stage'] ?? 'league',
            'status' => $data['status'] ?? 'scheduled',
        ]);

        $match->load(['homeTeam', 'awayTeam', 'venue', 'group']);

        return (new MatchResource($match))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, GameMatch $match): MatchResource
    {
        $this->assertTenant($request, $match->tenant_id);
        $match->load(['homeTeam', 'awayTeam', 'venue', 'group']);

        return new MatchResource($match);
    }

    public function update(Request $request, GameMatch $match, StandingRecalculator $recalculator, BracketAdvancer $advancer): MatchResource
    {
        $this->assertTenant($request, $match->tenant_id);

        $data = $request->validate([
            'tournament_group_id' => ['nullable', 'integer'],
            'venue_id' => ['nullable', 'integer'],
            'home_team_id' => ['nullable', 'integer'],
            'away_team_id' => ['nullable', 'integer', 'different:home_team_id'],
            'matchday' => ['nullable', 'integer', 'min:1'],
            'round_name' => ['nullable', 'string', 'max:80'],
            'stage' => ['nullable', 'string', 'max:40'],
            'scheduled_at' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['scheduled', 'live', 'finished', 'postponed', 'cancelled'])],
            'home_score' => ['nullable', 'integer', 'min:0'],
            'away_score' => ['nullable', 'integer', 'min:0'],
            'winner_team_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $payload = array_merge(
            [
                'tournament_id' => $match->tournament_id,
                'home_team_id' => $data['home_team_id'] ?? $match->home_team_id,
                'away_team_id' => $data['away_team_id'] ?? $match->away_team_id,
                'venue_id' => array_key_exists('venue_id', $data) ? $data['venue_id'] : $match->venue_id,
                'tournament_group_id' => array_key_exists('tournament_group_id', $data)
                    ? $data['tournament_group_id']
                    : $match->tournament_group_id,
            ],
            $data
        );

        $this->assertRelated($this->tenantId($request), $payload);
        $this->assertIntraGroupMatch($payload);

        $nextStatus = $data['status'] ?? $match->status;
        $homeScore = array_key_exists('home_score', $data) ? $data['home_score'] : $match->home_score;
        $awayScore = array_key_exists('away_score', $data) ? $data['away_score'] : $match->away_score;

        if ($nextStatus === 'finished') {
            if ($homeScore === null || $awayScore === null) {
                throw ValidationException::withMessages([
                    'homeScore' => ['Para finalizar el partido cargá el marcador local y visitante.'],
                ]);
            }

            if ($match->stage === 'knockout' && (int) $homeScore === (int) $awayScore) {
                throw ValidationException::withMessages([
                    'homeScore' => ['En eliminación no puede empatar: tiene que haber un ganador.'],
                ]);
            }

            if (! array_key_exists('winner_team_id', $data)) {
                if ($homeScore > $awayScore) {
                    $data['winner_team_id'] = $match->home_team_id;
                } elseif ($awayScore > $homeScore) {
                    $data['winner_team_id'] = $match->away_team_id;
                } else {
                    $data['winner_team_id'] = null;
                }
            }
        }

        $match->update($data);
        $match->load(['homeTeam', 'awayTeam', 'venue', 'group', 'tournament', 'homeFromMatch', 'awayFromMatch']);

        if (
            array_key_exists('status', $data)
            || array_key_exists('home_score', $data)
            || array_key_exists('away_score', $data)
        ) {
            if ($match->tournament->format !== 'knockout') {
                $recalculator->recalculate($match->tournament);
            }
        }

        if ($match->fresh()->status === 'finished' && $match->stage === 'knockout') {
            $advancer->advanceFrom($match->fresh());
        }

        return new MatchResource($match->fresh()->load([
            'homeTeam',
            'awayTeam',
            'venue',
            'group',
            'homeFromMatch',
            'awayFromMatch',
        ]));
    }

    public function destroy(Request $request, GameMatch $match, StandingRecalculator $recalculator): JsonResponse
    {
        $this->assertTenant($request, $match->tenant_id);
        $tournament = $match->tournament;
        $wasFinished = $match->status === 'finished';
        $match->delete();

        if ($wasFinished && $tournament) {
            $recalculator->recalculate($tournament);
        }

        return response()->json(null, 204);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertRelated(int $tenantId, array $data): void
    {
        if (! empty($data['tournament_group_id'])) {
            TournamentGroup::query()
                ->forTenant($tenantId)
                ->where('tournament_id', $data['tournament_id'])
                ->whereKey($data['tournament_group_id'])
                ->firstOrFail();
        }

        if (! empty($data['venue_id'])) {
            Venue::query()->forTenant($tenantId)->whereKey($data['venue_id'])->firstOrFail();
        }

        foreach (['home_team_id', 'away_team_id', 'winner_team_id'] as $teamKey) {
            if (! empty($data[$teamKey])) {
                Team::query()
                    ->forTenant($tenantId)
                    ->where('tournament_id', $data['tournament_id'])
                    ->whereKey($data[$teamKey])
                    ->firstOrFail();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertIntraGroupMatch(array $data): void
    {
        $homeId = $data['home_team_id'] ?? null;
        $awayId = $data['away_team_id'] ?? null;
        if (! $homeId || ! $awayId) {
            return;
        }

        $home = Team::query()->find($homeId);
        $away = Team::query()->find($awayId);
        if (! $home || ! $away) {
            return;
        }

        $tournament = Tournament::query()->find($data['tournament_id'] ?? $home->tournament_id);
        if (! $tournament || $tournament->format !== 'groups') {
            return;
        }

        if ($home->tournament_group_id && $away->tournament_group_id
            && (int) $home->tournament_group_id !== (int) $away->tournament_group_id) {
            throw ValidationException::withMessages([
                'awayTeamId' => ['En torneos por grupos solo se permiten partidos entre equipos del mismo grupo.'],
            ]);
        }

        $groupId = $data['tournament_group_id'] ?? null;
        if ($groupId && $home->tournament_group_id && (int) $groupId !== (int) $home->tournament_group_id) {
            throw ValidationException::withMessages([
                'tournamentGroupId' => ['El grupo del partido no coincide con el grupo del equipo local.'],
            ]);
        }
    }

    private function assertTenant(Request $request, int $tenantId): void
    {
        abort_unless($tenantId === $this->tenantId($request), 404);
    }
}
