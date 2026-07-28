<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => 'Matchday API',
        'panel' => 'http://localhost:5173',
        'login' => [
            'email' => 'org@torneos.test',
            'password' => 'password123',
        ],
        'hint' => 'El panel web corre en Vite (puerto 5173), no en este puerto.',
    ]);
});
