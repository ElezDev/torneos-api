<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Standing;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FixtureGenerator
{
    /**
     * @param  array{
     *   startDate: string,
     *   kickoffTime: string,
     *   daysBetweenMatchdays?: int,
     *   legs?: int,
     *   venueIds?: list<int>,
     *   clearExisting?: bool,
     *   matchIntervalMinutes?: int,
     *   groupCount?: int,
     *   distributeTeams?: bool,
     *   shuffleTeams?: bool
     * }  $options
     * @return Collection<int, GameMatch>
     */
    public function generate(Tournament $tournament, array $options): Collection
    {
        if (! in_array($tournament->format, ['league', 'groups', 'knockout'], true)) {
            throw new InvalidArgumentException('La generación automática está disponible para formatos liga, grupos o eliminación.');
        }

        if ($tournament->format === 'knockout' || ($options['mode'] ?? null) === 'knockout') {
            return app(KnockoutBracketGenerator::class)->generate($tournament, $options);
        }

        $teams = Team::query()
            ->where('tournament_id', $tournament->id)
            ->orderBy('id')
            ->get();

        if ($teams->count() < 2) {
            throw new InvalidArgumentException('Necesitas al menos 2 equipos para generar el fixture.');
        }

        $startDate = Carbon::parse($options['startDate'])->startOfDay();
        $kickoffTime = $options['kickoffTime'] ?? '18:00';
        $daysBetween = max(1, (int) ($options['daysBetweenMatchdays'] ?? 7));
        $legs = max(1, min(2, (int) ($options['legs'] ?? 1)));
        $venueIds = array_values(array_filter($options['venueIds'] ?? []));
        $clearExisting = (bool) ($options['clearExisting'] ?? true);
        $intervalMinutes = max(0, (int) ($options['matchIntervalMinutes'] ?? 90));

        return DB::transaction(function () use (
            $tournament,
            $teams,
            $options,
            $startDate,
            $kickoffTime,
            $daysBetween,
            $legs,
            $venueIds,
            $clearExisting,
            $intervalMinutes
        ) {
            if ($clearExisting) {
                GameMatch::query()
                    ->where('tournament_id', $tournament->id)
                    ->whereIn('status', ['scheduled', 'postponed'])
                    ->delete();
            }

            $pools = $this->preparePools($tournament, $teams, $options);
            $teams = Team::query()
                ->where('tournament_id', $tournament->id)
                ->orderBy('id')
                ->get();

            $created = collect();
            $globalIndex = 0;
            [$hour, $minute] = array_map('intval', explode(':', $kickoffTime));

            foreach ($pools as $pool) {
                /** @var Collection<int, Team> $poolTeams */
                $poolTeams = $pool['teams'];
                $groupId = $pool['groupId'];
                $groupName = $pool['groupName'];
                $rounds = $this->buildRoundRobin($poolTeams->pluck('id')->all(), $legs);

                foreach ($rounds as $matchday => $pairs) {
                    $day = $startDate->copy()->addDays(($matchday - 1) * $daysBetween);
                    $slot = 0;

                    foreach ($pairs as [$homeId, $awayId]) {
                        if ($homeId === null || $awayId === null) {
                            continue;
                        }

                        $this->assertSameGroupPair($poolTeams, $homeId, $awayId, $groupName);

                        $scheduledAt = $day->copy()
                            ->setTime($hour, $minute)
                            ->addMinutes($slot * $intervalMinutes);

                        $venueId = $venueIds === []
                            ? null
                            : $venueIds[$globalIndex % count($venueIds)];

                        $roundLabel = $groupName
                            ? $groupName.' · Fecha '.$matchday
                            : 'Fecha '.$matchday;

                        $match = GameMatch::create([
                            'tenant_id' => $tournament->tenant_id,
                            'tournament_id' => $tournament->id,
                            'tournament_group_id' => $groupId,
                            'venue_id' => $venueId,
                            'home_team_id' => $homeId,
                            'away_team_id' => $awayId,
                            'matchday' => $matchday,
                            'round_name' => $roundLabel,
                            'stage' => $groupId ? 'group' : 'league',
                            'scheduled_at' => $scheduledAt,
                            'status' => 'scheduled',
                        ]);

                        $created->push($match);
                        $globalIndex++;
                        $slot++;
                    }
                }
            }

            $this->ensureStandings($tournament, $teams);

            if ($tournament->status === 'draft' || $tournament->status === 'registration') {
                $tournament->update(['status' => 'active']);
            }

            $ids = $created->pluck('id')->all();

            return GameMatch::query()
                ->with(['homeTeam', 'awayTeam', 'venue', 'group'])
                ->whereIn('id', $ids)
                ->orderBy('matchday')
                ->orderBy('scheduled_at')
                ->get();
        });
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @param  array<string, mixed>  $options
     * @return list<array{groupId: ?int, groupName: ?string, teams: Collection<int, Team>}>
     */
    private function preparePools(Tournament $tournament, Collection $teams, array $options): array
    {
        if ($tournament->format !== 'groups') {
            return [[
                'groupId' => null,
                'groupName' => null,
                'teams' => $teams,
            ]];
        }

        $distribute = array_key_exists('distributeTeams', $options)
            ? (bool) $options['distributeTeams']
            : true;
        $shuffle = (bool) ($options['shuffleTeams'] ?? true);

        if ($distribute) {
            $this->distributeTeamsIntoGroups($tournament, $teams, $options, $shuffle);
            $tournament->load('groups');
            $teams = Team::query()
                ->where('tournament_id', $tournament->id)
                ->orderBy('id')
                ->get();
        } else {
            $tournament->loadMissing('groups');
            if ($tournament->groups->isEmpty()) {
                throw new InvalidArgumentException(
                    'No hay grupos. Activa “Distribuir equipos en grupos” o crea grupos antes.'
                );
            }
            $ungrouped = $teams->whereNull('tournament_group_id');
            if ($ungrouped->isNotEmpty()) {
                throw new InvalidArgumentException(
                    'Hay equipos sin grupo. Activa la distribución automática o asígnalos manualmente: '
                    .$ungrouped->pluck('name')->join(', ')
                );
            }
        }

        $pools = [];
        foreach ($tournament->groups->sortBy('sort_order')->values() as $group) {
            $groupTeams = $teams->where('tournament_group_id', $group->id)->values();
            if ($groupTeams->count() < 2) {
                throw new InvalidArgumentException(
                    "El grupo {$group->name} quedó con {$groupTeams->count()} equipo(s). Necesita al menos 2."
                );
            }
            $pools[] = [
                'groupId' => $group->id,
                'groupName' => $group->name,
                'teams' => $groupTeams,
            ];
        }

        if ($pools === []) {
            throw new InvalidArgumentException('No se pudieron armar grupos para el fixture.');
        }

        return $pools;
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @param  array<string, mixed>  $options
     */
    private function distributeTeamsIntoGroups(
        Tournament $tournament,
        Collection $teams,
        array $options,
        bool $shuffle
    ): void {
        $existingCount = TournamentGroup::query()
            ->where('tournament_id', $tournament->id)
            ->count();

        $requested = (int) ($options['groupCount'] ?? 0);
        $groupCount = $requested > 0 ? $requested : ($existingCount > 0 ? $existingCount : 2);
        $groupCount = max(2, min(16, $groupCount));

        $teamCount = $teams->count();
        if ($teamCount < $groupCount * 2) {
            throw new InvalidArgumentException(
                "Para {$groupCount} grupos necesitas al menos ".($groupCount * 2)
                ." equipos (tienes {$teamCount}). Baja la cantidad de grupos o agrega equipos."
            );
        }

        // Ensure exactly groupCount groups (create missing / trim excess empty ones carefully).
        $groups = TournamentGroup::query()
            ->where('tournament_id', $tournament->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $labels = range('A', 'Z');
        while ($groups->count() < $groupCount) {
            $index = $groups->count();
            $name = 'Grupo '.($labels[$index] ?? (string) ($index + 1));
            $group = TournamentGroup::create([
                'tenant_id' => $tournament->tenant_id,
                'tournament_id' => $tournament->id,
                'name' => $name,
                'sort_order' => $index,
            ]);
            $groups->push($group);
        }

        if ($groups->count() > $groupCount) {
            $keep = $groups->take($groupCount)->values();
            $remove = $groups->slice($groupCount);
            Team::query()
                ->whereIn('tournament_group_id', $remove->pluck('id'))
                ->update(['tournament_group_id' => null]);
            TournamentGroup::query()->whereIn('id', $remove->pluck('id'))->delete();
            $groups = $keep;
        }

        // Reset assignments and redistribute evenly (snake / round-robin).
        Team::query()
            ->where('tournament_id', $tournament->id)
            ->update(['tournament_group_id' => null]);

        Standing::query()
            ->where('tournament_id', $tournament->id)
            ->delete();

        $ordered = $teams->values();
        if ($shuffle) {
            $ordered = $ordered->shuffle()->values();
        }

        $groupIds = $groups->pluck('id')->values();
        foreach ($ordered as $index => $team) {
            $groupId = $groupIds[$index % $groupCount];
            $team->update(['tournament_group_id' => $groupId]);
        }

        if ($tournament->format !== 'groups') {
            $tournament->update(['format' => 'groups']);
        }
    }

    /**
     * @param  Collection<int, Team>  $poolTeams
     */
    private function assertSameGroupPair(Collection $poolTeams, int $homeId, int $awayId, ?string $groupName): void
    {
        $ids = $poolTeams->pluck('id')->all();
        if (! in_array($homeId, $ids, true) || ! in_array($awayId, $ids, true)) {
            throw new InvalidArgumentException(
                'Se intentó crear un partido entre equipos de distintos grupos'
                .($groupName ? " ({$groupName})" : '').'.'
            );
        }
    }

    /**
     * @param  list<int>  $teamIds
     * @return array<int, list<array{0: ?int, 1: ?int}>>
     */
    private function buildRoundRobin(array $teamIds, int $legs): array
    {
        $ids = array_values($teamIds);
        if (count($ids) % 2 === 1) {
            $ids[] = null;
        }

        $n = count($ids);
        $roundsCount = $n - 1;
        $half = $n / 2;
        $rounds = [];

        for ($round = 0; $round < $roundsCount; $round++) {
            $pairs = [];
            for ($i = 0; $i < $half; $i++) {
                $home = $ids[$i];
                $away = $ids[$n - 1 - $i];
                if ($round % 2 === 1) {
                    [$home, $away] = [$away, $home];
                }
                $pairs[] = [$home, $away];
            }
            $rounds[$round + 1] = $pairs;

            $fixed = array_shift($ids);
            $last = array_pop($ids);
            array_unshift($ids, $last);
            array_unshift($ids, $fixed);
        }

        if ($legs === 2) {
            $secondLeg = [];
            $offset = count($rounds);
            foreach ($rounds as $matchday => $pairs) {
                $reversed = array_map(
                    fn (array $pair) => [$pair[1], $pair[0]],
                    $pairs
                );
                $secondLeg[$offset + $matchday] = $reversed;
            }
            $rounds = $rounds + $secondLeg;
        }

        return $rounds;
    }

    /**
     * @param  Collection<int, Team>  $teams
     */
    private function ensureStandings(Tournament $tournament, Collection $teams): void
    {
        foreach ($teams as $team) {
            Standing::query()->firstOrCreate(
                [
                    'tournament_id' => $tournament->id,
                    'team_id' => $team->id,
                    'tournament_group_id' => $team->tournament_group_id,
                ],
                [
                    'tenant_id' => $tournament->tenant_id,
                    'played' => 0,
                    'won' => 0,
                    'drawn' => 0,
                    'lost' => 0,
                    'goals_for' => 0,
                    'goals_against' => 0,
                    'goal_difference' => 0,
                    'points' => 0,
                ]
            );
        }
    }
}
