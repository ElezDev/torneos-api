<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TournamentResource;
use App\Models\Sport;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TournamentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tournaments = Tournament::query()
            ->forTenant($this->tenantId($request))
            ->with(['sport', 'groups'])
            ->latest()
            ->get();

        return TournamentResource::collection($tournaments);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sport_id' => ['required', 'integer', 'exists:sports,id'],
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:180'],
            'format' => ['required', Rule::in(['league', 'knockout', 'groups'])],
            'status' => ['nullable', Rule::in(['draft', 'registration', 'active', 'finished', 'cancelled'])],
            'season_label' => ['nullable', 'string', 'max:80'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_public' => ['nullable', 'boolean'],
            'points_config' => ['nullable', 'array'],
            'points_config.win' => ['nullable', 'integer'],
            'points_config.draw' => ['nullable', 'integer'],
            'points_config.loss' => ['nullable', 'integer'],
            'sanction_rules' => ['nullable', 'array'],
            'tiebreaker_rules' => ['nullable', 'array'],
            'format_config' => ['nullable', 'array'],
            'groups' => ['nullable', 'array'],
            'groups.*.name' => ['required_with:groups', 'string', 'max:80'],
            'groups.*.sort_order' => ['nullable', 'integer'],
        ]);

        Sport::query()->whereKey($data['sport_id'])->where('is_active', true)->firstOrFail();

        $tenantId = $this->tenantId($request);
        $slug = $this->uniqueSlug($tenantId, $data['slug'] ?? $data['name']);

        $tournament = DB::transaction(function () use ($data, $tenantId, $slug) {
            $tournament = Tournament::create([
                'tenant_id' => $tenantId,
                'sport_id' => $data['sport_id'],
                'name' => $data['name'],
                'slug' => $slug,
                'format' => $data['format'],
                'status' => $data['status'] ?? 'draft',
                'season_label' => $data['season_label'] ?? null,
                'starts_on' => $data['starts_on'] ?? null,
                'ends_on' => $data['ends_on'] ?? null,
                'is_public' => $data['is_public'] ?? true,
                'points_config' => $data['points_config'] ?? ['win' => 3, 'draw' => 1, 'loss' => 0],
                'sanction_rules' => $data['sanction_rules'] ?? [
                    'yellowsForSuspension' => 2,
                    'redDirectSuspension' => true,
                    'suspensionMatches' => 1,
                ],
                'tiebreaker_rules' => $data['tiebreaker_rules'] ?? [
                    'points',
                    'goalDifference',
                    'goalsFor',
                    'headToHead',
                ],
                'format_config' => $data['format_config'] ?? null,
            ]);

            foreach ($data['groups'] ?? [] as $index => $group) {
                TournamentGroup::create([
                    'tenant_id' => $tenantId,
                    'tournament_id' => $tournament->id,
                    'name' => $group['name'],
                    'sort_order' => $group['sort_order'] ?? $index,
                ]);
            }

            return $tournament;
        });

        $tournament->load(['sport', 'groups']);

        return (new TournamentResource($tournament))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Tournament $tournament): TournamentResource
    {
        $this->assertTenant($request, $tournament->tenant_id);
        $tournament->load(['sport', 'groups']);

        return new TournamentResource($tournament);
    }

    public function update(Request $request, Tournament $tournament): TournamentResource
    {
        $this->assertTenant($request, $tournament->tenant_id);

        $data = $request->validate([
            'sport_id' => ['sometimes', 'integer', 'exists:sports,id'],
            'name' => ['sometimes', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:180'],
            'format' => ['sometimes', Rule::in(['league', 'knockout', 'groups'])],
            'status' => ['sometimes', Rule::in(['draft', 'registration', 'active', 'finished', 'cancelled'])],
            'season_label' => ['nullable', 'string', 'max:80'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
            'is_public' => ['nullable', 'boolean'],
            'points_config' => ['nullable', 'array'],
            'sanction_rules' => ['nullable', 'array'],
            'tiebreaker_rules' => ['nullable', 'array'],
            'format_config' => ['nullable', 'array'],
        ]);

        if (array_key_exists('slug', $data) && $data['slug']) {
            $data['slug'] = $this->uniqueSlug($tournament->tenant_id, $data['slug'], $tournament->id);
        } elseif (array_key_exists('name', $data) && ! array_key_exists('slug', $data)) {
            // keep existing slug when only name changes
        }

        $tournament->update($data);
        $tournament->load(['sport', 'groups']);

        return new TournamentResource($tournament);
    }

    public function destroy(Request $request, Tournament $tournament): JsonResponse
    {
        $this->assertTenant($request, $tournament->tenant_id);
        $tournament->delete();

        return response()->json(null, 204);
    }

    private function uniqueSlug(int $tenantId, string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'tournament';
        $slug = $base;
        $i = 1;

        while (
            Tournament::query()
                ->where('tenant_id', $tenantId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function assertTenant(Request $request, int $tenantId): void
    {
        abort_unless($tenantId === $this->tenantId($request), 404);
    }
}
