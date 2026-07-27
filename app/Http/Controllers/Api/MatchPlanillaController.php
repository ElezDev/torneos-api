<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchEventResource;
use App\Http\Resources\MatchResource;
use App\Http\Resources\MatchSheetResource;
use App\Http\Resources\PlayerResource;
use App\Models\GameMatch;
use App\Models\MatchEvent;
use App\Models\MatchSheet;
use App\Models\Player;
use App\Models\Team;
use App\Services\PlanillaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class MatchPlanillaController extends Controller
{
    public function show(Request $request, GameMatch $match, PlanillaService $planilla): JsonResponse
    {
        $this->assertTenant($request, $match->tenant_id);
        $match = $planilla->loadPlanilla($match);

        $roster = [];
        foreach ([$match->home_team_id, $match->away_team_id] as $teamId) {
            if (! $teamId) {
                continue;
            }
            $roster[$teamId] = PlayerResource::collection(
                Player::query()->where('team_id', $teamId)->orderBy('last_name')->get()
            )->resolve();
        }

        return response()->json([
            'data' => [
                'match' => (new MatchResource($match))->resolve(),
                'sheets' => MatchSheetResource::collection($match->sheets)->resolve(),
                'events' => MatchEventResource::collection(
                    $match->events->sortBy('minute')->values()
                )->resolve(),
                'roster' => $roster,
            ],
        ]);
    }

    public function updateMeta(Request $request, GameMatch $match, PlanillaService $planilla): JsonResponse
    {
        $this->assertTenant($request, $match->tenant_id);
        $planilla->ensureSheets($match);

        $data = $request->validate([
            'referee_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'home_delegate_name' => ['nullable', 'string', 'max:150'],
            'away_delegate_name' => ['nullable', 'string', 'max:150'],
            'home_observations' => ['nullable', 'string', 'max:2000'],
            'away_observations' => ['nullable', 'string', 'max:2000'],
        ]);

        if (array_key_exists('referee_name', $data)) {
            $match->update(['referee_name' => $data['referee_name']]);
        }
        if (array_key_exists('notes', $data)) {
            $match->update(['notes' => $data['notes']]);
        }

        $homeSheet = MatchSheet::query()->where('match_id', $match->id)->where('team_id', $match->home_team_id)->first();
        $awaySheet = MatchSheet::query()->where('match_id', $match->id)->where('team_id', $match->away_team_id)->first();

        if ($homeSheet) {
            $homePayload = [];
            if (array_key_exists('home_delegate_name', $data)) {
                $homePayload['delegate_name'] = $data['home_delegate_name'];
            }
            if (array_key_exists('home_observations', $data)) {
                $homePayload['observations'] = $data['home_observations'];
            }
            if ($homePayload !== []) {
                $homeSheet->update($homePayload);
            }
        }

        if ($awaySheet) {
            $awayPayload = [];
            if (array_key_exists('away_delegate_name', $data)) {
                $awayPayload['delegate_name'] = $data['away_delegate_name'];
            }
            if (array_key_exists('away_observations', $data)) {
                $awayPayload['observations'] = $data['away_observations'];
            }
            if ($awayPayload !== []) {
                $awaySheet->update($awayPayload);
            }
        }

        return response()->json(['message' => 'Planilla actualizada']);
    }

    public function syncLineup(
        Request $request,
        GameMatch $match,
        Team $team,
        PlanillaService $planilla
    ): JsonResponse {
        $this->assertTenant($request, $match->tenant_id);
        abort_unless(in_array($team->id, [$match->home_team_id, $match->away_team_id], true), 404);

        $data = $request->validate([
            'players' => ['required', 'array', 'min:1'],
            'players.*.player_id' => ['required', 'integer'],
            'players.*.jersey_number' => ['nullable', 'integer', 'min:0', 'max:999'],
            'players.*.is_starter' => ['required', 'boolean'],
        ]);

        $sheet = MatchSheet::query()->firstOrCreate(
            ['match_id' => $match->id, 'team_id' => $team->id],
            ['tenant_id' => $match->tenant_id, 'status' => 'draft']
        );

        $players = array_map(fn ($row) => [
            'playerId' => $row['player_id'],
            'jerseyNumber' => $row['jersey_number'] ?? null,
            'isStarter' => $row['is_starter'],
        ], $data['players']);

        $sheet = $planilla->syncLineup($sheet, $players);

        return response()->json([
            'data' => (new MatchSheetResource($sheet))->resolve(),
        ]);
    }

    public function storeEvent(Request $request, GameMatch $match, PlanillaService $planilla): JsonResponse
    {
        $this->assertTenant($request, $match->tenant_id);
        $planilla->ensureSheets($match);

        $data = $request->validate([
            'type' => ['required', Rule::in(['goal', 'ownGoal', 'yellowCard', 'redCard', 'secondYellow', 'substitution'])],
            'team_id' => ['required', 'integer'],
            'player_id' => ['required', 'integer'],
            'related_player_id' => ['nullable', 'integer'],
            'minute' => ['nullable', 'integer', 'min:0', 'max:200'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless(in_array($data['team_id'], [$match->home_team_id, $match->away_team_id], true), 422);

        $event = $planilla->addEvent($match, [
            'type' => $data['type'],
            'teamId' => $data['team_id'],
            'playerId' => $data['player_id'],
            'relatedPlayerId' => $data['related_player_id'] ?? null,
            'minute' => $data['minute'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'data' => (new MatchEventResource($event))->resolve(),
        ], 201);
    }

    public function destroyEvent(Request $request, GameMatch $match, MatchEvent $event): JsonResponse
    {
        $this->assertTenant($request, $match->tenant_id);
        abort_unless($event->match_id === $match->id, 404);

        $sheet = $event->sheet;
        if ($sheet && $sheet->status === 'closed') {
            abort(422, 'La planilla está cerrada.');
        }

        $event->delete();

        return response()->json(null, 204);
    }

    public function close(Request $request, GameMatch $match, PlanillaService $planilla): JsonResponse
    {
        $this->assertTenant($request, $match->tenant_id);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $match = $planilla->closeAndFinish($match, $request->user(), $data['notes'] ?? null);

        return response()->json([
            'message' => 'Planilla cerrada y partido finalizado',
            'data' => [
                'match' => (new MatchResource($match))->resolve(),
                'sheets' => MatchSheetResource::collection($match->sheets)->resolve(),
                'events' => MatchEventResource::collection($match->events)->resolve(),
            ],
        ]);
    }

    public function pdf(Request $request, GameMatch $match, PlanillaService $planilla): Response
    {
        $this->assertTenant($request, $match->tenant_id);
        $match = $planilla->loadPlanilla($match);

        $homeSheet = $match->sheets->firstWhere('team_id', $match->home_team_id);
        $awaySheet = $match->sheets->firstWhere('team_id', $match->away_team_id);
        $events = $match->events->sortBy(fn ($e) => [$e->minute ?? 999, $e->id])->values();

        $pdf = Pdf::loadView('pdf.planilla', [
            'match' => $match,
            'homeSheet' => $homeSheet,
            'awaySheet' => $awaySheet,
            'events' => $events,
        ])->setPaper('a4', 'portrait');

        $filename = 'planilla-'.$match->id.'.pdf';

        return $pdf->download($filename);
    }

    private function assertTenant(Request $request, int $tenantId): void
    {
        abort_unless($tenantId === $this->tenantId($request), 404);
    }
}
