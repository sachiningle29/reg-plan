<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\PlanImportController;

use App\Http\Controllers\Api\RigDetailController;

Route::post('/admin/login', [AuthController::class, 'adminLogin']);
Route::post('/user/login', [AuthController::class, 'userLogin']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::middleware('role:admin')->get('/admin/dashboard-data', function () {
        return response()->json(['message' => 'Welcome Admin']);
    });

    Route::middleware('role:user')->get('/user/dashboard-data', function () {
        return response()->json(['message' => 'Welcome User']);
    });


    Route::middleware('role:admin')->group(function () {
    Route::post('/plans/import', [PlanImportController::class, 'import']);
    Route::get('/plans', [PlanImportController::class, 'plans']);

    Route::get('/rig-details', [RigDetailController::class, 'index']);

    Route::get('/rig-details/gantt', [RigDetailController::class, 'forGantt']);
});

});
