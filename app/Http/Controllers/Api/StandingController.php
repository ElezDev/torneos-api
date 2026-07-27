<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StandingResource;
use App\Models\Standing;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StandingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'tournament_id' => ['required', 'integer'],
            'tournament_group_id' => ['nullable', 'integer'],
        ]);

        $tenantId = $this->tenantId($request);

        Tournament::query()
            ->forTenant($tenantId)
            ->whereKey($data['tournament_id'])
            ->firstOrFail();

        $query = Standing::query()
            ->forTenant($tenantId)
            ->where('tournament_id', $data['tournament_id'])
            ->with('team')
            ->orderByDesc('points')
            ->orderByDesc('goal_difference')
            ->orderByDesc('goals_for');

        if (! empty($data['tournament_group_id'])) {
            $query->where('tournament_group_id', $data['tournament_group_id']);
        }

        return StandingResource::collection($query->get());
    }
}
