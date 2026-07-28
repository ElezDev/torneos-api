<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AdminTenantController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $tenants = Tenant::query()
            ->with(['users' => fn ($query) => $query->wherePivot('is_owner', true)])
            ->withCount(['tournaments', 'users'])
            ->orderBy('name')
            ->get();

        return TenantResource::collection($tenants);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150'],
            'owner_name' => ['required', 'string', 'max:150'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_password' => ['required', 'string', Password::defaults()],
        ]);

        $tenant = DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['slug'] ?? $data['name']),
                'is_active' => true,
            ]);

            $owner = User::create([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => $data['owner_password'],
            ]);

            $owner->assignRole('organizador');
            $tenant->users()->attach($owner->id, ['is_owner' => true]);

            return $tenant->load(['users' => fn ($query) => $query->wherePivot('is_owner', true)])
                ->loadCount(['tournaments', 'users']);
        });

        return (new TenantResource($tenant))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Tenant $tenant): TenantResource
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'slug' => ['sometimes', 'string', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $tenant->update($data);

        return new TenantResource(
            $tenant->fresh()
                ->load(['users' => fn ($query) => $query->wherePivot('is_owner', true)])
                ->loadCount(['tournaments', 'users'])
        );
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
