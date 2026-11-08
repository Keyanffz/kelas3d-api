<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AppRuleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\CoordinatorController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\HelpRequestController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NudgeController;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureDeviceApproved;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// users/me/... merujuk ke user yang sedang login.
Route::bind('user', fn (string $value) => $value === 'me' ? request()->user() : User::findOrFail($value));

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('login/link', [AuthController::class, 'loginWithLink'])->middleware('throttle:10,1');

// Agent: login dengan kode install pribadi, lalu token Sanctum milik Device.
Route::prefix('agent')->group(function () {
    Route::post('login', [AgentController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', EnsureDeviceApproved::class])->group(function () {
        Route::get('config', [AgentController::class, 'config']);
        Route::post('heartbeat', [AgentController::class, 'heartbeat']);
        Route::post('web-link', [AgentController::class, 'webLink'])->middleware('throttle:10,1');
    });
});

// Web: sesi akun lewat cookie Sanctum SPA. Token device tidak berlaku di sini.
Route::middleware('auth:web')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    Route::patch('users/{user}/avatar', [UserController::class, 'updateAvatar'])->can('updateAvatar', 'user');
    Route::get('users/{user}/activity', [UserController::class, 'activity'])->can('viewActivity', 'user');

    Route::patch('devices/{device}', [DeviceController::class, 'update'])->can('update', 'device');

    Route::post('coordinator/visit', [CoordinatorController::class, 'visit'])->can('koordinator');
    Route::post('announcements', [AnnouncementController::class, 'store'])->can('koordinator');

    Route::post('help-requests', [HelpRequestController::class, 'store']);
    Route::patch('help-requests/{helpRequest}', [HelpRequestController::class, 'update']);

    Route::middleware('can:koordinator')->group(function () {
        Route::get('app-rules', [AppRuleController::class, 'index']);
        Route::post('app-rules', [AppRuleController::class, 'store']);
        Route::patch('app-rules/{appRule}', [AppRuleController::class, 'update']);
        Route::delete('app-rules/{appRule}', [AppRuleController::class, 'destroy']);
    });

    Route::get('nudges', [NudgeController::class, 'index']);
    Route::post('nudges', [NudgeController::class, 'store'])->can('koordinator');
    Route::patch('nudges/{nudge}', [NudgeController::class, 'update'])->can('update', 'nudge');

    Route::get('classrooms', [ClassroomController::class, 'index']);
    Route::post('classrooms', [ClassroomController::class, 'store'])->can('create', Classroom::class);

    Route::prefix('classrooms/{classroom}')->scopeBindings()->group(function () {
        Route::get('/', [ClassroomController::class, 'show'])->can('view', 'classroom');
        Route::put('/', [ClassroomController::class, 'update'])->can('update', 'classroom');

        Route::get('schedules', [ScheduleController::class, 'index'])->can('view', 'classroom');
        Route::post('schedules', [ScheduleController::class, 'store'])->can('update', 'classroom');
        Route::put('schedules/{schedule}', [ScheduleController::class, 'update'])->can('update', 'classroom');
        Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy'])->can('update', 'classroom');

        Route::get('presences', [PresenceController::class, 'index'])->can('view', 'classroom');
        Route::get('devices', [DeviceController::class, 'index'])->can('manageMembers', 'classroom');

        Route::get('members', [MemberController::class, 'index'])->can('manageMembers', 'classroom');
        Route::post('members', [MemberController::class, 'store'])->can('manageMembers', 'classroom');
        Route::patch('members/{member}', [MemberController::class, 'update'])->can('manageMembers', 'classroom');
        Route::delete('members/{member}', [MemberController::class, 'destroy'])->can('manageMembers', 'classroom');
        Route::post('members/{member}/install-code', [MemberController::class, 'installCode'])->can('manageMembers', 'classroom');
    });
});
