<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Team;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class KnockoutBracketGenerator
{
    /**
     * @param  array{
     *   startDate: string,
     *   kickoffTime: string,
     *   daysBetweenMatchdays?: int,
     *   venueIds?: list<int>,
     *   clearExisting?: bool,
     *   matchIntervalMinutes?: int,
     *   shuffleTeams?: bool,
     *   includeThirdPlace?: bool
     * }  $options
     * @return Collection<int, GameMatch>
     */
    public function generate(Tournament $tournament, array $options): Collection
    {
        $teams = Team::query()
            ->where('tournament_id', $tournament->id)
            ->orderBy('id')
            ->get();

        $count = $teams->count();
        if ($count < 4) {
            throw new InvalidArgumentException('La eliminación directa necesita al menos 4 equipos.');
        }

        if (($count & ($count - 1)) !== 0) {
            throw new InvalidArgumentException(
                "Para el bracket necesitas una potencia de 2 equipos (4, 8, 16…). Tienes {$count}."
            );
        }

        $startDate = Carbon::parse($options['startDate'])->startOfDay();
        $kickoffTime = $options['kickoffTime'] ?? '18:00';
        $daysBetween = max(1, (int) ($options['daysBetweenMatchdays'] ?? 3));
        $venueIds = array_values(array_filter($options['venueIds'] ?? []));
        $clearExisting = (bool) ($options['clearExisting'] ?? true);
        $intervalMinutes = max(0, (int) ($options['matchIntervalMinutes'] ?? 90));
        $shuffle = (bool) ($options['shuffleTeams'] ?? true);
        $includeThirdPlace = (bool) ($options['includeThirdPlace'] ?? true);

        return DB::transaction(function () use (
            $tournament,
            $teams,
            $count,
            $startDate,
            $kickoffTime,
            $daysBetween,
            $venueIds,
            $clearExisting,
            $intervalMinutes,
            $shuffle,
            $includeThirdPlace
        ) {
            if ($clearExisting) {
                GameMatch::query()
                    ->where('tournament_id', $tournament->id)
                    ->whereIn('status', ['scheduled', 'postponed'])
                    ->delete();
            }

            $ordered = $shuffle ? $teams->shuffle()->values() : $teams->values();
            $rounds = $this->roundLabels($count);
            [$hour, $minute] = array_map('intval', explode(':', $kickoffTime));

            $createdByRound = [];
            $slot = 1;
            $globalIndex = 0;

            // Round 1: random pairings with real teams
            $firstRoundName = $rounds[0];
            $firstRoundMatches = [];
            $day = $startDate->copy();
            $pairIndex = 0;

            for ($i = 0; $i < $count; $i += 2) {
                $scheduledAt = $day->copy()
                    ->setTime($hour, $minute)
                    ->addMinutes($pairIndex * $intervalMinutes);

                $venueId = $venueIds === [] ? null : $venueIds[$globalIndex % count($venueIds)];

                $match = GameMatch::create([
                    'tenant_id' => $tournament->tenant_id,
                    'tournament_id' => $tournament->id,
                    'venue_id' => $venueId,
                    'home_team_id' => $ordered[$i]->id,
                    'away_team_id' => $ordered[$i + 1]->id,
                    'matchday' => 1,
                    'round_name' => $firstRoundName,
                    'stage' => 'knockout',
                    'bracket_slot' => $slot,
                    'bracket_code' => $this->codeFor($firstRoundName, count($firstRoundMatches) + 1),
                    'scheduled_at' => $scheduledAt,
                    'status' => 'scheduled',
                ]);

                $firstRoundMatches[] = $match;
                $slot++;
                $globalIndex++;
                $pairIndex++;
            }

            $createdByRound[] = $firstRoundMatches;
            $previous = $firstRoundMatches;

            // Later rounds: placeholders linked to previous winners
            for ($roundIndex = 1; $roundIndex < count($rounds); $roundIndex++) {
                $roundName = $rounds[$roundIndex];
                $day = $startDate->copy()->addDays($roundIndex * $daysBetween);
                $roundMatches = [];
                $pairIndex = 0;

                for ($i = 0; $i < count($previous); $i += 2) {
                    $homeSource = $previous[$i];
                    $awaySource = $previous[$i + 1];
                    $scheduledAt = $day->copy()
                        ->setTime($hour, $minute)
                        ->addMinutes($pairIndex * $intervalMinutes);
                    $venueId = $venueIds === [] ? null : $venueIds[$globalIndex % count($venueIds)];

                    $match = GameMatch::create([
                        'tenant_id' => $tournament->tenant_id,
                        'tournament_id' => $tournament->id,
                        'venue_id' => $venueId,
                        'home_team_id' => null,
                        'away_team_id' => null,
                        'home_from_match_id' => $homeSource->id,
                        'away_from_match_id' => $awaySource->id,
                        'home_from_result' => 'winner',
                        'away_from_result' => 'winner',
                        'matchday' => $roundIndex + 1,
                        'round_name' => $roundName,
                        'stage' => 'knockout',
                        'bracket_slot' => $slot,
                        'bracket_code' => $this->codeFor($roundName, count($roundMatches) + 1),
                        'scheduled_at' => $scheduledAt,
                        'status' => 'scheduled',
                    ]);

                    $roundMatches[] = $match;
                    $slot++;
                    $globalIndex++;
                    $pairIndex++;
                }

                $createdByRound[] = $roundMatches;
                $previous = $roundMatches;
            }

            // Third place: losers of semi-finals
            if ($includeThirdPlace && count($createdByRound) >= 2) {
                $semis = $createdByRound[count($createdByRound) - 2];
                if (count($semis) === 2) {
                    $finalDay = $startDate->copy()->addDays((count($rounds) - 1) * $daysBetween);
                    $thirdAt = $finalDay->copy()->setTime($hour, $minute)->subMinutes(max($intervalMinutes, 60));
                    $venueId = $venueIds === [] ? null : $venueIds[$globalIndex % count($venueIds)];

                    GameMatch::create([
                        'tenant_id' => $tournament->tenant_id,
                        'tournament_id' => $tournament->id,
                        'venue_id' => $venueId,
                        'home_team_id' => null,
                        'away_team_id' => null,
                        'home_from_match_id' => $semis[0]->id,
                        'away_from_match_id' => $semis[1]->id,
                        'home_from_result' => 'loser',
                        'away_from_result' => 'loser',
                        'matchday' => count($rounds),
                        'round_name' => 'Tercer lugar',
                        'stage' => 'knockout',
                        'bracket_slot' => $slot,
                        'bracket_code' => '3RD',
                        'scheduled_at' => $thirdAt,
                        'status' => 'scheduled',
                    ]);
                }
            }

            if ($tournament->status === 'draft' || $tournament->status === 'registration') {
                $tournament->update(['status' => 'active', 'format' => 'knockout']);
            } elseif ($tournament->format !== 'knockout') {
                $tournament->update(['format' => 'knockout']);
            }

            return GameMatch::query()
                ->with(['homeTeam', 'awayTeam', 'venue', 'homeFromMatch', 'awayFromMatch'])
                ->where('tournament_id', $tournament->id)
                ->where('stage', 'knockout')
                ->orderBy('bracket_slot')
                ->get();
        });
    }

    /**
     * @return list<string>
     */
    private function roundLabels(int $teamCount): array
    {
        return match ($teamCount) {
            4 => ['Semifinal', 'Final'],
            8 => ['Cuartos', 'Semifinal', 'Final'],
            16 => ['Octavos', 'Cuartos', 'Semifinal', 'Final'],
            32 => ['Dieciseisavos', 'Octavos', 'Cuartos', 'Semifinal', 'Final'],
            default => throw new InvalidArgumentException("Cantidad de equipos no soportada: {$teamCount}"),
        };
    }

    private function codeFor(string $roundName, int $index): string
    {
        $prefix = match ($roundName) {
            'Dieciseisavos' => 'R32',
            'Octavos' => 'R16',
            'Cuartos' => 'QF',
            'Semifinal' => 'SF',
            'Final' => 'F',
            default => 'R',
        };

        return $prefix === 'F' ? 'F' : $prefix.'-'.$index;
    }
}
