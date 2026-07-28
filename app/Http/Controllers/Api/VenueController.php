<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VenueResource;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VenueController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $venues = Venue::query()
            ->forTenant($this->tenantId($request))
            ->orderBy('name')
            ->get();

        return VenueResource::collection($venues);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
        ]);

        $venue = Venue::create([
            ...$data,
            'tenant_id' => $this->tenantId($request),
        ]);

        return (new VenueResource($venue))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Venue $venue): VenueResource
    {
        $this->assertTenant($request, $venue->tenant_id);

        return new VenueResource($venue);
    }

    public function update(Request $request, Venue $venue): VenueResource
    {
        $this->assertTenant($request, $venue->tenant_id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
        ]);

        $venue->update($data);

        return new VenueResource($venue->refresh());
    }

    public function destroy(Request $request, Venue $venue): JsonResponse
    {
        $this->assertTenant($request, $venue->tenant_id);
        $venue->delete();

        return response()->json(null, 204);
    }

    private function assertTenant(Request $request, int $tenantId): void
    {
        abort_unless($tenantId === $this->tenantId($request), 404);
    }
}
