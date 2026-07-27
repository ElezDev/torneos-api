<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlayerResource;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PlayerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Player::query()
            ->forTenant($this->tenantId($request))
            ->with('team')
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($request->filled('team_id')) {
            $query->where('team_id', $request->integer('team_id'));
        }

        if ($request->filled('tournament_id')) {
            $query->whereHas('team', function ($teamQuery) use ($request) {
                $teamQuery->where('tournament_id', $request->integer('tournament_id'));
            });
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($inner) use ($search) {
                $inner->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('document_id', 'like', "%{$search}%");
            });
        }

        return PlayerResource::collection($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $data = $request->validate([
            'team_id' => ['required', 'integer'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'jersey_number' => ['nullable', 'integer', 'min:0', 'max:999'],
            'document_id' => [
                'required',
                'string',
                'max:50',
                Rule::unique('players', 'document_id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'birth_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['enabled', 'suspended'])],
        ]);

        Team::query()
            ->forTenant($tenantId)
            ->whereKey($data['team_id'])
            ->firstOrFail();

        $player = Player::create([
            ...$data,
            'tenant_id' => $tenantId,
            'status' => $data['status'] ?? 'enabled',
        ]);

        $player->load('team');

        return (new PlayerResource($player))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Player $player): PlayerResource
    {
        $this->assertTenant($request, $player->tenant_id);

        return new PlayerResource($player);
    }

    public function update(Request $request, Player $player): PlayerResource
    {
        $this->assertTenant($request, $player->tenant_id);

        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'jersey_number' => ['nullable', 'integer', 'min:0', 'max:999'],
            'document_id' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('players', 'document_id')
                    ->where(fn ($q) => $q->where('tenant_id', $player->tenant_id))
                    ->ignore($player->id),
            ],
            'birth_date' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(['enabled', 'suspended'])],
            'team_id' => ['sometimes', 'integer'],
        ]);

        if (isset($data['team_id'])) {
            Team::query()
                ->forTenant($this->tenantId($request))
                ->whereKey($data['team_id'])
                ->firstOrFail();
        }

        $player->update($data);

        return new PlayerResource($player->refresh());
    }

    public function destroy(Request $request, Player $player): JsonResponse
    {
        $this->assertTenant($request, $player->tenant_id);
        $player->delete();

        return response()->json(null, 204);
    }

    private function assertTenant(Request $request, int $tenantId): void
    {
        abort_unless($tenantId === $this->tenantId($request), 404);
    }
}
