<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tenants = $request->user()
            ->tenants()
            ->orderBy('name')
            ->get();

        return TenantResource::collection($tenants);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150'],
        ]);

        $user = $request->user();

        $tenant = DB::transaction(function () use ($data, $user) {
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['slug'] ?? $data['name']),
                'is_active' => true,
            ]);

            $tenant->users()->attach($user->id, ['is_owner' => true]);

            if (! $user->hasRole('organizador') && ! $user->hasRole('super-admin')) {
                $user->assignRole('organizador');
            }

            return $user->tenants()->where('tenants.id', $tenant->id)->firstOrFail();
        });

        return (new TenantResource($tenant))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Tenant $tenant): TenantResource
    {
        abort_unless($request->user()->belongsToTenant($tenant->id), 404);

        $membership = $request->user()->tenants()->where('tenants.id', $tenant->id)->first();

        return new TenantResource($membership);
    }

    public function update(Request $request, Tenant $tenant): TenantResource
    {
        abort_unless($request->user()->belongsToTenant($tenant->id), 404);

        $membership = $request->user()->tenants()->where('tenants.id', $tenant->id)->first();
        abort_unless((bool) $membership?->pivot?->is_owner, 403, 'Solo el dueño puede editar el inquilino.');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('slug', $data) && $data['slug']) {
            $data['slug'] = $this->uniqueSlug($data['slug'], $tenant->id);
        }

        $tenant->update($data);

        $membership = $request->user()->tenants()->where('tenants.id', $tenant->id)->first();

        return new TenantResource($membership);
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'tenant';
        $slug = $base;
        $i = 1;

        while (
            Tenant::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
