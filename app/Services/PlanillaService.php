<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\MatchEvent;
use App\Models\MatchSheet;
use App\Models\MatchSheetPlayer;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanillaService
{
    public function __construct(
        private StandingRecalculator $standings,
        private BracketAdvancer $bracket,
    ) {}

    public function ensureSheets(GameMatch $match): array
    {
        $match->loadMissing(['homeTeam', 'awayTeam']);

        $sheets = [];
        foreach ([$match->home_team_id, $match->away_team_id] as $teamId) {
            if (! $teamId) {
                continue;
            }
            $sheets[] = MatchSheet::query()->firstOrCreate(
                [
                    'match_id' => $match->id,
                    'team_id' => $teamId,
                ],
                [
                    'tenant_id' => $match->tenant_id,
                    'status' => 'draft',
                ]
            );
        }

        return $sheets;
    }

    public function loadPlanilla(GameMatch $match): GameMatch
    {
        $this->ensureSheets($match);

        return $match->fresh()->load([
            'homeTeam',
            'awayTeam',
            'venue',
            'group',
            'tournament.sport',
            'sheets.team',
            'sheets.players.player',
            'events.player',
            'events.relatedPlayer',
            'events.team',
        ]);
    }

    /**
     * @param  list<array{playerId:int,jerseyNumber?:?int,isStarter:bool}>  $players
     */
    public function syncLineup(MatchSheet $sheet, array $players): MatchSheet
    {
        if ($sheet->status === 'closed') {
            throw ValidationException::withMessages([
                'sheet' => ['La planilla ya está cerrada.'],
            ]);
        }

        $teamPlayerIds = Player::query()
            ->where('team_id', $sheet->team_id)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($sheet, $players, $teamPlayerIds) {
            MatchSheetPlayer::query()->where('match_sheet_id', $sheet->id)->delete();

            foreach ($players as $row) {
                $playerId = (int) $row['playerId'];
                if (! in_array($playerId, $teamPlayerIds, true)) {
                    throw ValidationException::withMessages([
                        'players' => ['Hay jugadores que no pertenecen al equipo.'],
                    ]);
                }

                MatchSheetPlayer::create([
                    'tenant_id' => $sheet->tenant_id,
                    'match_sheet_id' => $sheet->id,
                    'player_id' => $playerId,
                    'jersey_number' => $row['jerseyNumber'] ?? null,
                    'is_starter' => (bool) ($row['isStarter'] ?? true),
                ]);
            }
        });

        return $sheet->fresh()->load(['players.player', 'team']);
    }

    /**
     * @param  array{
     *   type: string,
     *   teamId: int,
     *   playerId: int,
     *   relatedPlayerId?: ?int,
     *   minute?: ?int,
     *   notes?: ?string
     * }  $data
     */
    public function addEvent(GameMatch $match, array $data): MatchEvent
    {
        $sheet = MatchSheet::query()
            ->where('match_id', $match->id)
            ->where('team_id', $data['teamId'])
            ->firstOrFail();

        if ($sheet->status === 'closed') {
            throw ValidationException::withMessages([
                'sheet' => ['La planilla del equipo ya está cerrada.'],
            ]);
        }

        $types = ['goal', 'ownGoal', 'yellowCard', 'redCard', 'secondYellow', 'substitution'];
        if (! in_array($data['type'], $types, true)) {
            throw ValidationException::withMessages([
                'type' => ['Tipo de evento inválido.'],
            ]);
        }

        if ($data['type'] === 'substitution' && empty($data['relatedPlayerId'])) {
            throw ValidationException::withMessages([
                'relatedPlayerId' => ['En un cambio indicá quién entra.'],
            ]);
        }

        return MatchEvent::create([
            'tenant_id' => $match->tenant_id,
            'match_id' => $match->id,
            'match_sheet_id' => $sheet->id,
            'team_id' => $data['teamId'],
            'player_id' => $data['playerId'],
            'related_player_id' => $data['relatedPlayerId'] ?? null,
            'type' => $data['type'],
            'minute' => $data['minute'] ?? null,
            'notes' => $data['notes'] ?? null,
        ])->load(['player', 'relatedPlayer', 'team']);
    }

    public function closeAndFinish(GameMatch $match, User $user, ?string $notes = null): GameMatch
    {
        return DB::transaction(function () use ($match, $user, $notes) {
            $match = $this->loadPlanilla($match);
            $sheets = $match->sheets;

            if ($sheets->count() < 2) {
                throw ValidationException::withMessages([
                    'planilla' => ['Faltan planillas de ambos equipos.'],
                ]);
            }

            foreach ($sheets as $sheet) {
                if ($sheet->players()->count() === 0) {
                    throw ValidationException::withMessages([
                        'lineup' => ["Cargá la nómina de {$sheet->team->name}."],
                    ]);
                }
                $sheet->update([
                    'status' => 'closed',
                    'closed_at' => now(),
                    'closed_by' => $user->id,
                ]);
            }

            $homeGoals = MatchEvent::query()
                ->where('match_id', $match->id)
                ->where('team_id', $match->home_team_id)
                ->where('type', 'goal')
                ->count();
            $homeOwn = MatchEvent::query()
                ->where('match_id', $match->id)
                ->where('team_id', $match->away_team_id)
                ->where('type', 'ownGoal')
                ->count();
            $awayGoals = MatchEvent::query()
                ->where('match_id', $match->id)
                ->where('team_id', $match->away_team_id)
                ->where('type', 'goal')
                ->count();
            $awayOwn = MatchEvent::query()
                ->where('match_id', $match->id)
                ->where('team_id', $match->home_team_id)
                ->where('type', 'ownGoal')
                ->count();

            $homeScore = $homeGoals + $homeOwn;
            $awayScore = $awayGoals + $awayOwn;

            if ($match->stage === 'knockout' && $homeScore === $awayScore) {
                throw ValidationException::withMessages([
                    'score' => ['En eliminación no puede empatar: cargá un gol decisivo o resolvé el resultado.'],
                ]);
            }

            $winnerId = null;
            if ($homeScore > $awayScore) {
                $winnerId = $match->home_team_id;
            } elseif ($awayScore > $homeScore) {
                $winnerId = $match->away_team_id;
            }

            $match->update([
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'winner_team_id' => $winnerId,
                'status' => 'finished',
                'notes' => $notes ?? $match->notes,
            ]);

            $this->applyCardCounts($match);

            $match->load('tournament');
            if ($match->tournament->format !== 'knockout') {
                $this->standings->recalculate($match->tournament);
            } else {
                $this->bracket->advanceFrom($match->fresh());
            }

            return $this->loadPlanilla($match);
        });
    }

    private function applyCardCounts(GameMatch $match): void
    {
        $events = MatchEvent::query()
            ->where('match_id', $match->id)
            ->whereIn('type', ['yellowCard', 'redCard', 'secondYellow'])
            ->get();

        foreach ($events->groupBy('player_id') as $playerId => $playerEvents) {
            $player = Player::query()->find($playerId);
            if (! $player) {
                continue;
            }

            $yellows = $playerEvents->where('type', 'yellowCard')->count()
                + $playerEvents->where('type', 'secondYellow')->count();
            $reds = $playerEvents->where('type', 'redCard')->count()
                + $playerEvents->where('type', 'secondYellow')->count();

            $player->yellow_cards_count = (int) $player->yellow_cards_count + $yellows;
            $player->red_cards_count = (int) $player->red_cards_count + $reds;

            if ($reds > 0 || $playerEvents->where('type', 'secondYellow')->isNotEmpty()) {
                $player->suspension_matches_left = max((int) $player->suspension_matches_left, 1);
                $player->status = 'suspended';
            }

            $player->save();
        }
    }
}
