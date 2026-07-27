<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Standing;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Support\Facades\DB;

class StandingRecalculator
{
    public function recalculate(Tournament $tournament): void
    {
        $points = $tournament->points_config ?? ['win' => 3, 'draw' => 1, 'loss' => 0];
        $win = (int) ($points['win'] ?? 3);
        $draw = (int) ($points['draw'] ?? 1);
        $loss = (int) ($points['loss'] ?? 0);

        DB::transaction(function () use ($tournament, $win, $draw, $loss) {
            $teams = Team::query()
                ->where('tournament_id', $tournament->id)
                ->get();

            foreach ($teams as $team) {
                Standing::query()->updateOrCreate(
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
                        'rank_position' => null,
                    ]
                );
            }

            // Zero out then re-apply finished results.
            Standing::query()
                ->where('tournament_id', $tournament->id)
                ->update([
                    'played' => 0,
                    'won' => 0,
                    'drawn' => 0,
                    'lost' => 0,
                    'goals_for' => 0,
                    'goals_against' => 0,
                    'goal_difference' => 0,
                    'points' => 0,
                    'rank_position' => null,
                ]);

            $matches = GameMatch::query()
                ->where('tournament_id', $tournament->id)
                ->where('status', 'finished')
                ->whereNotNull('home_team_id')
                ->whereNotNull('away_team_id')
                ->whereNotNull('home_score')
                ->whereNotNull('away_score')
                ->get();

            foreach ($matches as $match) {
                $this->applyMatch($match, $win, $draw, $loss);
            }

            $this->rank($tournament);
        });
    }

    private function applyMatch(GameMatch $match, int $win, int $draw, int $loss): void
    {
        $home = Standing::query()
            ->where('tournament_id', $match->tournament_id)
            ->where('team_id', $match->home_team_id)
            ->first();
        $away = Standing::query()
            ->where('tournament_id', $match->tournament_id)
            ->where('team_id', $match->away_team_id)
            ->first();

        if (! $home || ! $away) {
            return;
        }

        $homeScore = (int) $match->home_score;
        $awayScore = (int) $match->away_score;

        $home->played++;
        $away->played++;
        $home->goals_for += $homeScore;
        $home->goals_against += $awayScore;
        $away->goals_for += $awayScore;
        $away->goals_against += $homeScore;

        if ($homeScore > $awayScore) {
            $home->won++;
            $away->lost++;
            $home->points += $win;
            $away->points += $loss;
        } elseif ($homeScore < $awayScore) {
            $away->won++;
            $home->lost++;
            $away->points += $win;
            $home->points += $loss;
        } else {
            $home->drawn++;
            $away->drawn++;
            $home->points += $draw;
            $away->points += $draw;
        }

        $home->goal_difference = $home->goals_for - $home->goals_against;
        $away->goal_difference = $away->goals_for - $away->goals_against;
        $home->save();
        $away->save();
    }

    private function rank(Tournament $tournament): void
    {
        $groups = Standing::query()
            ->where('tournament_id', $tournament->id)
            ->get()
            ->groupBy(fn (Standing $row) => $row->tournament_group_id ?? 0);

        foreach ($groups as $rows) {
            $sorted = $rows->sort(function (Standing $a, Standing $b) {
                return [$b->points, $b->goal_difference, $b->goals_for]
                    <=> [$a->points, $a->goal_difference, $a->goals_for];
            })->values();

            foreach ($sorted as $index => $row) {
                $row->update(['rank_position' => $index + 1]);
            }
        }
    }
}
