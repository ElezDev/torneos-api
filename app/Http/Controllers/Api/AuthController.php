<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'tenant_name' => ['required', 'string', 'max:150'],
        ]);

        $result = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $tenant = Tenant::create([
                'name' => $data['tenant_name'],
                'slug' => $this->uniqueTenantSlug($data['tenant_name']),
                'is_active' => true,
            ]);

            $tenant->users()->attach($user->id, ['is_owner' => true]);
            $user->assignRole('organizador');

            $token = JWTAuth::fromUser($user);

            return compact('user', 'tenant', 'token');
        });

        $result['user']->load(['roles', 'permissions', 'tenants']);

        return response()->json([
            'token' => $result['token'],
            'tokenType' => 'Bearer',
            'expiresIn' => (int) config('jwt.ttl') * 60,
            'user' => new UserResource($result['user']),
            'tenant' => new TenantResource($result['tenant']),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! $token = auth('api')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        /** @var User $user */
        $user = auth('api')->user();
        $user->load(['roles', 'permissions', 'tenants']);

        if (! $user->isSuperAdmin()) {
            $hasActiveTenant = $user->tenants()->where('tenants.is_active', true)->exists();
            abort_unless($hasActiveTenant, 403, 'Tu organización está inactiva o no tenés acceso.');
        }

        return $this->respondWithToken($token, $user);
    }

    public function me(Request $request): UserResource
    {
        /** @var User $user */
        $user = auth('api')->user();
        $user->load(['roles', 'permissions', 'tenants']);

        return new UserResource($user);
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json([
            'message' => 'Logged out',
        ]);
    }

    public function refresh(): JsonResponse
    {
        $token = auth('api')->refresh();

        /** @var User $user */
        $user = auth('api')->user();
        $user->load(['roles', 'permissions', 'tenants']);

        return $this->respondWithToken($token, $user);
    }

    private function respondWithToken(string $token, User $user): JsonResponse
    {
        return response()->json([
            'token' => $token,
            'tokenType' => 'Bearer',
            'expiresIn' => (int) config('jwt.ttl') * 60,
            'user' => new UserResource($user),
        ]);
    }

    private function uniqueTenantSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tenant';
        $slug = $base;
        $i = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
