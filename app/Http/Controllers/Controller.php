<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function tenant(Request $request): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        return $tenant;
    }

    protected function tenantId(Request $request): int
    {
        return $this->tenant($request)->id;
    }
}
