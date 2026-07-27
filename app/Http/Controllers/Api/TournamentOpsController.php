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
use App\Models\Team;
use App\Models\Tournament;
use App\Models\Venue;
use App\Services\FixtureGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TournamentOpsController extends Controller
{
    public function overview(Request $request, Tournament $tournament): JsonResponse
    {
        $this->assertTenant($request, $tournament->tenant_id);

        $tournament->load(['sport', 'groups']);

        $teamIds = Team::query()
            ->where('tournament_id', $tournament->id)
            ->pluck('id');

        $matchesQuery = GameMatch::query()->where('tournament_id', $tournament->id);

        $summary = [
            'teamsCount' => $teamIds->count(),
            'playersCount' => Player::query()->whereIn('team_id', $teamIds)->count(),
            'matchesCount' => (clone $matchesQuery)->count(),
            'matchesScheduled' => (clone $matchesQuery)->where('status', 'scheduled')->count(),
            'matchesFinished' => (clone $matchesQuery)->where('status', 'finished')->count(),
            'venuesUsed' => (clone $matchesQuery)
                ->whereNotNull('venue_id')
                ->pluck('venue_id')
                ->unique()
                ->count(),
        ];

        $standings = Standing::query()
            ->where('tournament_id', $tournament->id)
            ->with(['team', 'group'])
            ->orderByDesc('points')
            ->orderByDesc('goal_difference')
            ->orderByDesc('goals_for')
            ->get();

        $standingsByGroup = $tournament->groups
            ->sortBy('sort_order')
            ->map(function ($group) use ($standings) {
                $rows = $standings
                    ->where('tournament_group_id', $group->id)
                    ->sort(function ($a, $b) {
                        return [$b->points, $b->goal_difference, $b->goals_for]
                            <=> [$a->points, $a->goal_difference, $a->goals_for];
                    })
                    ->values();

                return [
                    'group' => [
                        'id' => $group->id,
                        'name' => $group->name,
                        'sortOrder' => $group->sort_order,
                    ],
                    'standings' => StandingResource::collection($rows)->resolve(),
                ];
            })
            ->values()
            ->all();

        $scorers = MatchEvent::query()
            ->select('player_id', DB::raw('COUNT(*) as goals'))
            ->whereIn('match_id', GameMatch::query()->where('tournament_id', $tournament->id)->select('id'))
            ->where('type', 'goal')
            ->groupBy('player_id')
            ->orderByDesc('goals')
            ->limit(15)
            ->get();

        $players = Player::query()
            ->whereIn('id', $scorers->pluck('player_id'))
            ->get()
            ->keyBy('id');

        $scorerRows = $scorers->map(fn ($row) => [
            'playerId' => $row->player_id,
            'goals' => (int) $row->goals,
            'player' => $players->get($row->player_id)
                ? (new PlayerResource($players->get($row->player_id)))->resolve()
                : null,
        ]);

        $cardLeaders = Player::query()
            ->whereIn('team_id', $teamIds)
            ->where(function ($q) {
                $q->where('yellow_cards_count', '>', 0)
                    ->orWhere('red_cards_count', '>', 0);
            })
            ->orderByDesc('red_cards_count')
            ->orderByDesc('yellow_cards_count')
            ->limit(15)
            ->get();

        $upcoming = GameMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('status', 'scheduled')
            ->with(['homeTeam', 'awayTeam', 'venue', 'group', 'homeFromMatch', 'awayFromMatch'])
            ->orderBy('scheduled_at')
            ->limit(8)
            ->get();

        $bracketMatches = GameMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('stage', 'knockout')
            ->with(['homeTeam', 'awayTeam', 'venue', 'homeFromMatch', 'awayFromMatch'])
            ->orderBy('bracket_slot')
            ->get();

        $bracketRounds = $bracketMatches
            ->groupBy('round_name')
            ->map(fn ($rows, $name) => [
                'roundName' => $name,
                'matches' => MatchResource::collection($rows->values())->resolve(),
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'tournament' => (new TournamentResource($tournament))->resolve(),
                'summary' => $summary,
                'standings' => StandingResource::collection($standings)->resolve(),
                'standingsByGroup' => $standingsByGroup,
                'scorers' => $scorerRows,
                'cardLeaders' => PlayerResource::collection($cardLeaders)->resolve(),
                'upcomingMatches' => MatchResource::collection($upcoming)->resolve(),
                'bracketRounds' => $bracketRounds,
            ],
        ]);
    }

    public function generateFixture(Request $request, Tournament $tournament, FixtureGenerator $generator): JsonResponse
    {
        $this->assertTenant($request, $tournament->tenant_id);

        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'kickoff_time' => ['required', 'date_format:H:i'],
            'days_between_matchdays' => ['nullable', 'integer', 'min:1', 'max:30'],
            'legs' => ['nullable', 'integer', 'in:1,2'],
            'venue_ids' => ['nullable', 'array'],
            'venue_ids.*' => ['integer'],
            'clear_existing' => ['nullable', 'boolean'],
            'match_interval_minutes' => ['nullable', 'integer', 'min:0', 'max:300'],
            'group_count' => ['nullable', 'integer', 'min:2', 'max:16'],
            'distribute_teams' => ['nullable', 'boolean'],
            'shuffle_teams' => ['nullable', 'boolean'],
            'include_third_place' => ['nullable', 'boolean'],
            'mode' => ['nullable', Rule::in(['league', 'groups', 'knockout'])],
        ]);

        $venueIds = $data['venue_ids'] ?? [];
        if ($venueIds !== []) {
            $validCount = Venue::query()
                ->forTenant($this->tenantId($request))
                ->whereIn('id', $venueIds)
                ->count();

            if ($validCount !== count($venueIds)) {
                throw ValidationException::withMessages([
                    'venueIds' => ['Una o más sedes no pertenecen al inquilino.'],
                ]);
            }
        }

        try {
            $matches = $generator->generate($tournament, [
                'startDate' => $data['start_date'],
                'kickoffTime' => $data['kickoff_time'],
                'daysBetweenMatchdays' => $data['days_between_matchdays'] ?? 7,
                'legs' => $data['legs'] ?? 1,
                'venueIds' => $venueIds,
                'clearExisting' => $data['clear_existing'] ?? true,
                'matchIntervalMinutes' => $data['match_interval_minutes'] ?? 90,
                'groupCount' => $data['group_count'] ?? null,
                'distributeTeams' => $data['distribute_teams'] ?? true,
                'shuffleTeams' => $data['shuffle_teams'] ?? true,
                'includeThirdPlace' => $data['include_third_place'] ?? true,
                'mode' => $data['mode'] ?? null,
            ]);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'fixture' => [$e->getMessage()],
            ]);
        }

        $tournament->refresh()->load('groups');

        $bracketRounds = $matches
            ->where('stage', 'knockout')
            ->groupBy('round_name')
            ->map(fn ($rows, $name) => [
                'roundName' => $name,
                'matches' => MatchResource::collection($rows->values())->resolve(),
            ])
            ->values()
            ->all();

        return response()->json([
            'message' => 'Fixture generado',
            'data' => [
                'matchesCreated' => $matches->count(),
                'matchdays' => $matches->pluck('matchday')->unique()->count(),
                'groupsCreated' => $tournament->groups->count(),
                'bracketRounds' => $bracketRounds,
                'matches' => MatchResource::collection($matches)->resolve(),
            ],
        ], 201);
    }

    private function assertTenant(Request $request, int $tenantId): void
    {
        abort_unless($tenantId === $this->tenantId($request), 404);
    }
}
