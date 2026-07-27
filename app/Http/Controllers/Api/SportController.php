<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SportResource;
use App\Models\Sport;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SportController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $sports = Sport::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return SportResource::collection($sports);
    }
}
