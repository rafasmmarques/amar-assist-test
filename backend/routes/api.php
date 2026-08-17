<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContractController;
use App\Http\Resources\AuthenticatedUserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthenticatedSessionController::class, 'store']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', fn (Request $request) => AuthenticatedUserResource::make($request->user()));
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

    Route::get('/clients', [ClientController::class, 'index']);
    Route::post('/clients', [ClientController::class, 'store']);
    Route::get('/clients/{client}', [ClientController::class, 'show']);
    Route::match(['put', 'patch'], '/clients/{client}', [ClientController::class, 'update']);
    Route::patch('/clients/{client}/activate', [ClientController::class, 'activate']);
    Route::patch('/clients/{client}/deactivate', [ClientController::class, 'deactivate']);

    Route::get('/clients/{client}/contracts', [ContractController::class, 'index']);
    Route::post('/clients/{client}/contracts', [ContractController::class, 'store']);
    Route::get('/contracts/{contract}', [ContractController::class, 'show']);
});
