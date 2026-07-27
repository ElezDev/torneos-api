<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Tournament;

class BracketAdvancer
{
    public function advanceFrom(GameMatch $match): void
    {
        if ($match->status !== 'finished' || ! $match->winner_team_id) {
            // Draw in knockout: still need a winner. Skip if no winner.
            if ($match->status !== 'finished') {
                return;
            }
            if ($match->home_score === $match->away_score) {
                return;
            }
        }

        $winnerId = $match->winner_team_id;
        $loserId = null;

        if ($match->home_team_id && $match->away_team_id && $winnerId) {
            $loserId = (int) $winnerId === (int) $match->home_team_id
                ? $match->away_team_id
                : $match->home_team_id;
        }

        if (! $winnerId && $match->home_score !== null && $match->away_score !== null) {
            if ($match->home_score > $match->away_score) {
                $winnerId = $match->home_team_id;
                $loserId = $match->away_team_id;
            } elseif ($match->away_score > $match->home_score) {
                $winnerId = $match->away_team_id;
                $loserId = $match->home_team_id;
            }
        }

        if (! $winnerId) {
            return;
        }

        $dependents = GameMatch::query()
            ->where('tournament_id', $match->tournament_id)
            ->where(function ($q) use ($match) {
                $q->where('home_from_match_id', $match->id)
                    ->orWhere('away_from_match_id', $match->id);
            })
            ->get();

        foreach ($dependents as $next) {
            $changed = false;

            if ((int) $next->home_from_match_id === (int) $match->id) {
                $teamId = ($next->home_from_result === 'loser') ? $loserId : $winnerId;
                if ($teamId) {
                    $next->home_team_id = $teamId;
                    $changed = true;
                }
            }

            if ((int) $next->away_from_match_id === (int) $match->id) {
                $teamId = ($next->away_from_result === 'loser') ? $loserId : $winnerId;
                if ($teamId) {
                    $next->away_team_id = $teamId;
                    $changed = true;
                }
            }

            if ($changed) {
                $next->save();
            }
        }
    }

    public function rebuild(Tournament $tournament): void
    {
        $finished = GameMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('stage', 'knockout')
            ->where('status', 'finished')
            ->orderBy('bracket_slot')
            ->get();

        foreach ($finished as $match) {
            $this->advanceFrom($match);
        }
    }
}
