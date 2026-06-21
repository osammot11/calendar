<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlannerController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/planner');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/planner', [PlannerController::class, 'app'])->name('planner');

    Route::prefix('planner-api')->group(function () {
        Route::get('/bootstrap', [PlannerController::class, 'bootstrap']);
        Route::post('/recalculate', [PlannerController::class, 'recalculate']);
        Route::post('/past-events/{scheduledBlock}/complete', [PlannerController::class, 'completePastEvent']);
        Route::post('/past-events/{scheduledBlock}/reschedule', [PlannerController::class, 'reschedulePastEvent']);

        Route::post('/projects', [PlannerController::class, 'storeProject']);
        Route::put('/projects/{project}', [PlannerController::class, 'updateProject']);
        Route::delete('/projects/{project}', [PlannerController::class, 'deleteProject']);

        Route::post('/tasks', [PlannerController::class, 'storeTask']);
        Route::put('/tasks/{task}', [PlannerController::class, 'updateTask']);
        Route::delete('/tasks/{task}', [PlannerController::class, 'deleteTask']);

        Route::post('/busy-blocks', [PlannerController::class, 'storeBusyBlock']);
        Route::put('/busy-blocks/{busyBlock}', [PlannerController::class, 'updateBusyBlock']);
        Route::delete('/busy-blocks/{busyBlock}', [PlannerController::class, 'deleteBusyBlock']);

        Route::post('/date-overrides', [PlannerController::class, 'storeDateOverride']);
        Route::post('/work-schedules', [PlannerController::class, 'storeWorkSchedules']);
    });
});
