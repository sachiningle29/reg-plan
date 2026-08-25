<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\PlanImportController;

use App\Http\Controllers\Api\RigDetailController;

use App\Http\Controllers\Api\UserController;

use App\Http\Controllers\Api\WellMaterialController;


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

    //added routes for user on 14-Aug-26
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    //end


    Route::post('/plans/import', [PlanImportController::class, 'import']);
    Route::get('/plans', [PlanImportController::class, 'plans']);

    Route::get('/rig-details', [RigDetailController::class, 'index']);

    Route::get('/rig-details/gantt', [RigDetailController::class, 'forGantt']);

//add route for unique rig and proper finish date
    Route::get('/rig-details/summary', [RigDetailController::class, 'summary']);
    //end

    //added route for update actual records
    Route::put('/rig-details/{rigDetail}', [RigDetailController::class, 'update']);
    //end


    // Route::get('/well-materials/assets', [WellMaterialController::class, 'assets']);
    // Route::get('/well-materials/wells', [WellMaterialController::class, 'wells']);
    // Route::get('/well-materials/date-range', [WellMaterialController::class, 'dateRange']);
    // Route::get('/well-materials/consumables', [WellMaterialController::class, 'consumables']);

    Route::get('/well-materials/assets', [WellMaterialController::class, 'assets']);
    Route::get('/well-materials/wells', [WellMaterialController::class, 'wells']);
    Route::get('/well-materials/report', [WellMaterialController::class, 'report']);



    Route::post('/well-materials/import', [WellMaterialController::class, 'import']);

});

});
