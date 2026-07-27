<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MatchPlanillaController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\PublicTournamentController;
use App\Http\Controllers\Api\SportController;
use App\Http\Controllers\Api\StandingController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\TournamentController;
use App\Http\Controllers\Api\TournamentGroupController;
use App\Http\Controllers\Api\TournamentOpsController;
use App\Http\Controllers\Api\VenueController;
use App\Http\Middleware\ConvertCamelCaseInput;
use App\Http\Middleware\SetCurrentTenant;
use Illuminate\Support\Facades\Route;

Route::middleware([ConvertCamelCaseInput::class])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);

        Route::middleware('auth:api')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
        });
    });

    Route::get('sports', [SportController::class, 'index']);

    Route::prefix('public/tenants/{tenantSlug}/tournaments/{tournamentSlug}')->group(function () {
        Route::get('/', [PublicTournamentController::class, 'show']);
        Route::get('/matches', [PublicTournamentController::class, 'matches']);
        Route::get('/standings', [PublicTournamentController::class, 'standings']);
        Route::get('/scorers', [PublicTournamentController::class, 'scorers']);
    });

    Route::middleware('auth:api')->group(function () {
        Route::get('tenants', [TenantController::class, 'index'])
            ->middleware('permission:tenants.view');
        Route::post('tenants', [TenantController::class, 'store'])
            ->middleware('permission:tenants.create');
        Route::get('tenants/{tenant}', [TenantController::class, 'show'])
            ->middleware('permission:tenants.view');
        Route::put('tenants/{tenant}', [TenantController::class, 'update'])
            ->middleware('permission:tenants.update');
        Route::patch('tenants/{tenant}', [TenantController::class, 'update'])
            ->middleware('permission:tenants.update');

        Route::middleware([SetCurrentTenant::class])->group(function () {
            Route::get('venues', [VenueController::class, 'index'])->middleware('permission:venues.view');
            Route::post('venues', [VenueController::class, 'store'])->middleware('permission:venues.create');
            Route::get('venues/{venue}', [VenueController::class, 'show'])->middleware('permission:venues.view');
            Route::put('venues/{venue}', [VenueController::class, 'update'])->middleware('permission:venues.update');
            Route::patch('venues/{venue}', [VenueController::class, 'update'])->middleware('permission:venues.update');
            Route::delete('venues/{venue}', [VenueController::class, 'destroy'])->middleware('permission:venues.delete');

            Route::get('tournaments', [TournamentController::class, 'index'])->middleware('permission:tournaments.view');
            Route::post('tournaments', [TournamentController::class, 'store'])->middleware('permission:tournaments.create');
            Route::get('tournaments/{tournament}', [TournamentController::class, 'show'])->middleware('permission:tournaments.view');
            Route::get('tournaments/{tournament}/overview', [TournamentOpsController::class, 'overview'])->middleware('permission:tournaments.view');
            Route::post('tournaments/{tournament}/generate-fixture', [TournamentOpsController::class, 'generateFixture'])->middleware('permission:matches.create');
            Route::get('tournaments/{tournament}/groups', [TournamentGroupController::class, 'index'])->middleware('permission:tournaments.view');
            Route::post('tournaments/{tournament}/groups', [TournamentGroupController::class, 'store'])->middleware('permission:tournaments.update');
            Route::put('tournaments/{tournament}/groups/{group}', [TournamentGroupController::class, 'update'])->middleware('permission:tournaments.update');
            Route::patch('tournaments/{tournament}/groups/{group}', [TournamentGroupController::class, 'update'])->middleware('permission:tournaments.update');
            Route::delete('tournaments/{tournament}/groups/{group}', [TournamentGroupController::class, 'destroy'])->middleware('permission:tournaments.update');
            Route::put('tournaments/{tournament}', [TournamentController::class, 'update'])->middleware('permission:tournaments.update');
            Route::patch('tournaments/{tournament}', [TournamentController::class, 'update'])->middleware('permission:tournaments.update');
            Route::delete('tournaments/{tournament}', [TournamentController::class, 'destroy'])->middleware('permission:tournaments.delete');

            Route::get('teams', [TeamController::class, 'index'])->middleware('permission:teams.view');
            Route::post('teams', [TeamController::class, 'store'])->middleware('permission:teams.create');
            Route::get('teams/{team}', [TeamController::class, 'show'])->middleware('permission:teams.view');
            Route::put('teams/{team}', [TeamController::class, 'update'])->middleware('permission:teams.update');
            Route::patch('teams/{team}', [TeamController::class, 'update'])->middleware('permission:teams.update');
            Route::delete('teams/{team}', [TeamController::class, 'destroy'])->middleware('permission:teams.delete');

            Route::get('players', [PlayerController::class, 'index'])->middleware('permission:players.view');
            Route::post('players', [PlayerController::class, 'store'])->middleware('permission:players.create');
            Route::get('players/{player}', [PlayerController::class, 'show'])->middleware('permission:players.view');
            Route::put('players/{player}', [PlayerController::class, 'update'])->middleware('permission:players.update');
            Route::patch('players/{player}', [PlayerController::class, 'update'])->middleware('permission:players.update');
            Route::delete('players/{player}', [PlayerController::class, 'destroy'])->middleware('permission:players.delete');

            Route::get('matches', [MatchController::class, 'index'])->middleware('permission:matches.view');
            Route::post('matches', [MatchController::class, 'store'])->middleware('permission:matches.create');
            Route::get('matches/{match}', [MatchController::class, 'show'])->middleware('permission:matches.view');
            Route::put('matches/{match}', [MatchController::class, 'update'])->middleware('permission:matches.update');
            Route::patch('matches/{match}', [MatchController::class, 'update'])->middleware('permission:matches.update');
            Route::delete('matches/{match}', [MatchController::class, 'destroy'])->middleware('permission:matches.delete');

            Route::get('matches/{match}/planilla', [MatchPlanillaController::class, 'show'])->middleware('permission:match-sheets.manage');
            Route::put('matches/{match}/planilla', [MatchPlanillaController::class, 'updateMeta'])->middleware('permission:match-sheets.manage');
            Route::put('matches/{match}/planilla/teams/{team}/lineup', [MatchPlanillaController::class, 'syncLineup'])->middleware('permission:match-sheets.manage');
            Route::post('matches/{match}/planilla/events', [MatchPlanillaController::class, 'storeEvent'])->middleware('permission:match-sheets.manage');
            Route::delete('matches/{match}/planilla/events/{event}', [MatchPlanillaController::class, 'destroyEvent'])->middleware('permission:match-sheets.manage');
            Route::post('matches/{match}/planilla/close', [MatchPlanillaController::class, 'close'])->middleware('permission:match-sheets.manage');
            Route::get('matches/{match}/planilla/pdf', [MatchPlanillaController::class, 'pdf'])->middleware('permission:match-sheets.manage');

            Route::get('standings', [StandingController::class, 'index'])
                ->middleware('permission:standings.view');
        });
    });
});
