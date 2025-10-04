<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TodoController;
use App\Http\Controllers\RequestItemController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\VisitorController;
use App\Http\Controllers\ActivityController;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ---------------- AUTH ----------------
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

// ---------------- USERS (managed by admin) ----------------
Route::middleware(['auth:sanctum', 'role:admin_ga,admin_ga_manager,super_admin'])->prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);          // list all users
    Route::get('/{id}', [UserController::class, 'show']);       // detail user
    Route::post('/', [UserController::class, 'store']);         // create user
    Route::patch('/{id}', [UserController::class, 'update']);   // update user
    Route::delete('/{id}', [UserController::class, 'destroy']); // delete user
    Route::get('/stats/global', [UserController::class, 'stats']); // new users per month/year
    Route::get('/stats', [UserController::class, 'stats']);        // alias for frontend simplicity
});

// ---------------- USER PROFILE (self access) ----------------
Route::middleware('auth:sanctum')->get('/me', [UserController::class, 'me']);

// ---------------- ACTIVITY LOGS ----------------
// User: view own activities
Route::middleware(['auth:sanctum', 'role:user'])->prefix('activities')->group(function () {
    Route::get('/mine', [ActivityController::class, 'mine']);
    Route::get('/stats', [ActivityController::class, 'stats']);
});

// Admin: view all activities
Route::middleware(['auth:sanctum', 'role:admin_ga,admin_ga_manager,super_admin'])->prefix('activities')->group(function () {
    Route::get('/', [ActivityController::class, 'index']);
    Route::get('/stats', [ActivityController::class, 'stats']);
    Route::get('/user/{userId}', [ActivityController::class, 'userSummary']);
    Route::delete('/clear-old', [ActivityController::class, 'clearOld']);
});

// ---------------- TODOS ----------------
// User: manage own todos
Route::middleware(['auth:sanctum', 'role:user'])->prefix('todos')->group(function () {
    Route::get('/', [TodoController::class, 'index']);                    // list own todos
    Route::get('/stats', [TodoController::class, 'statsUser']);           // personal todo stats
    Route::post('/', [TodoController::class, 'store']);                   // create todo
    Route::put('/{id}', [TodoController::class, 'update']);               // update todo (full)
    Route::patch('/{id}', [TodoController::class, 'update']);             // update todo (partial)
    Route::post('/{id}', [TodoController::class, 'update']);              // update todo (form-data POST support)
    Route::patch('/{id}/start', [TodoController::class, 'start']);        // start todo (not_started -> in_progress)
    Route::patch('/{id}/hold', [TodoController::class, 'hold']);          // hold todo (in_progress -> hold)
    Route::patch('/{id}/complete', [TodoController::class, 'complete']);  // complete todo (in_progress -> completed)

    // FIXED: Use POST for file uploads - more reliable than PATCH/PUT
    Route::post('/{id}/submit', [TodoController::class, 'submitForChecking']); // submit for checking
    Route::post('/{id}/improve', [TodoController::class, 'submitImprovement']); // submit improvements during evaluating

    Route::delete('/{id}', [TodoController::class, 'destroy']);           // delete todo
});

// Admin: create todos for users (kept for compatibility)
Route::middleware(['auth:sanctum', 'role:admin_ga,admin_ga_manager,super_admin'])->prefix('todos')->group(function () {
    Route::post('/', [TodoController::class, 'store']);                   // create todo (admin only)
});

// Admin/GA: manage all todos - FIXED role permission
Route::middleware(['auth:sanctum'])->prefix('todos')->group(function () {
    // Allow both admin and GA to access these routes
    Route::get('/all', [TodoController::class, 'indexAll'])->middleware('role:admin_ga,admin_ga_manager,super_admin'); // ?user_id=ID optional
    Route::get('/stats/global', [TodoController::class, 'statsGlobal'])->middleware('role:admin_ga,admin_ga_manager,super_admin');
    Route::get('/user/{userId}', [TodoController::class, 'indexByUser'])->middleware('role:admin_ga,admin_ga_manager,super_admin');
    // Admin/GA update any todo (needed by AdminTodos edit)
    Route::patch('/{id}', [TodoController::class, 'updateAny'])->middleware('role:admin_ga,admin_ga_manager,super_admin');
    Route::patch('/{id}/evaluate', [TodoController::class, 'evaluate'])->middleware('role:admin_ga,admin_ga_manager,super_admin');
    // Allow form-data POST for evaluate to avoid multipart PATCH issues
    Route::post('/{id}/evaluate', [TodoController::class, 'evaluate'])->middleware('role:admin_ga,admin_ga_manager,super_admin');
    Route::patch('/{id}/approve-improvement', [TodoController::class, 'approveImprovement'])->middleware('role:admin_ga,admin_ga_manager,super_admin');
    Route::get('/evaluate/{userId}', [TodoController::class, 'evaluateOverall'])->middleware('role:admin_ga,admin_ga_manager,super_admin');
    Route::get('/warnings/leaderboard', [TodoController::class, 'warningsLeaderboard'])->middleware('role:admin_ga,admin_ga_manager,super_admin');
    // Delete all todos in a routine group (by title + recurrence)
    Route::post('/routine-group/delete', [TodoController::class, 'destroyRoutineGroup'])->middleware('role:admin_ga,admin_ga_manager,super_admin');
    Route::delete('/{id}', [TodoController::class, 'destroyAny'])->middleware('role:admin_ga,admin_ga_manager,super_admin');

    // Legacy routes for backward compatibility (deprecated)
    Route::patch('/{id}/check', [TodoController::class, 'check'])->middleware('role:admin_ga,admin_ga_manager,super_admin');
    Route::patch('/{id}/note', [TodoController::class, 'addNote'])->middleware('role:admin_ga,admin_ga_manager,super_admin');
});

// ---------------- REQUESTS ----------------
// User: manage own requests
Route::middleware(['auth:sanctum', 'role:user'])->prefix('requests')->group(function () {
    Route::get('/mine', [RequestItemController::class, 'mine']);
    Route::get('/stats', [RequestItemController::class, 'statsUser']);
    Route::post('/', [RequestItemController::class, 'store']);
    Route::patch('/{id}', [RequestItemController::class, 'update']);
    Route::delete('/{id}', [RequestItemController::class, 'destroy']);
});
// Admin GA: manage requests (first approval)
Route::middleware(['auth:sanctum', 'role:admin_ga,admin_ga_manager,super_admin'])->prefix('requests')->group(function () {
    Route::get('/', [RequestItemController::class, 'index']);
    Route::get('/stats/global', [RequestItemController::class, 'statsGlobal']);
    Route::patch('/{id}/approve', [RequestItemController::class, 'approve']);
    Route::patch('/{id}/reject', [RequestItemController::class, 'reject']);
    // Admin edit/delete
    Route::patch('/{id}', [RequestItemController::class, 'updateAdmin']);
    Route::delete('/{id}', [RequestItemController::class, 'destroyAdmin']);
});

// GA Manager: final approval (second approval)
Route::middleware(['auth:sanctum', 'role:admin_ga_manager,super_admin'])->prefix('requests')->group(function () {
    Route::patch('/{id}/final-approve', [RequestItemController::class, 'finalApprove']);
    Route::patch('/{id}/final-reject', [RequestItemController::class, 'finalReject']);
});

Route::middleware(['auth:sanctum', 'role:user,admin_ga,admin_ga_manager,super_admin'])->prefix('requests')->group(function () {
    Route::post('/{id}/maintenance', [RequestItemController::class, 'requestMaintenance']);
});

Route::middleware(['auth:sanctum', 'role:admin_ga,admin_ga_manager,super_admin'])->prefix('requests')->group(function () {
    Route::post('/{id}/maintenance/start', [RequestItemController::class, 'startMaintenance']);
    Route::post('/{id}/maintenance/complete', [RequestItemController::class, 'completeMaintenance']);
});

// Procurement: read and edit requests (no approve/reject)
Route::middleware(['auth:sanctum', 'role:admin_ga,admin_ga_manager,super_admin'])->prefix('requests')->group(function () {
    Route::get('/', [RequestItemController::class, 'index']);
    Route::patch('/{id}', [RequestItemController::class, 'updateAdmin']);
});

// ---------------- PROCUREMENT ----------------
Route::middleware(['auth:sanctum', 'role:admin_ga,admin_ga_manager,super_admin'])->prefix('procurements')->group(function () {
    Route::get('/', [ProcurementController::class, 'index']);
    Route::post('/', [ProcurementController::class, 'store']);
    Route::get('/stats', [ProcurementController::class, 'stats']);
    // Approved requests for procurement to process
    Route::get('/approved-requests', [RequestItemController::class, 'index'])->middleware('role:admin_ga,admin_ga_manager,super_admin');
});

// ---------------- ASSETS ----------------
// Admin: full CRUD access to assets
Route::middleware(['auth:sanctum', 'role:admin_ga,admin_ga_manager,super_admin'])->prefix('assets')->group(function () {
    Route::get('/', [AssetController::class, 'index']);
    Route::get('/stats', [AssetController::class, 'stats']);
    Route::get('/next-code', [AssetController::class, 'nextCode']);
    Route::post('/', [AssetController::class, 'store']);
    Route::get('/{id}', [AssetController::class, 'show']);
    Route::put('/{id}', [AssetController::class, 'update']);
    Route::patch('/{id}', [AssetController::class, 'update']);
    Route::patch('/{id}/status', [AssetController::class, 'updateStatus']);
    Route::delete('/{id}', [AssetController::class, 'destroy']);
});

// Procurement: CRUD-minus-create (can read, update, delete) on assets
Route::middleware(['auth:sanctum', 'role:admin_ga,admin_ga_manager,super_admin'])->prefix('assets')->group(function () {
    Route::get('/', [AssetController::class, 'index']);
    Route::get('/stats', [AssetController::class, 'stats']);
    Route::get('/{id}', [AssetController::class, 'show']);
    Route::put('/{id}', [AssetController::class, 'update']);
    Route::patch('/{id}', [AssetController::class, 'update']);
    // Allow procurement to update status-only (avoid full validation requirements)
    Route::patch('/{id}/status', [AssetController::class, 'updateStatus']);
    Route::delete('/{id}', [AssetController::class, 'destroy']);
});

// User: manage own assets
Route::middleware(['auth:sanctum', 'role:user'])->prefix('assets')->group(function () {
    Route::get('/mine', [AssetController::class, 'mine']);
});

Route::middleware(['auth:sanctum', 'role:user,admin_ga,admin_ga_manager,super_admin'])->prefix('assets')->group(function () {
    Route::post('/{id}/maintenance', [AssetController::class, 'requestMaintenance']);
});

Route::middleware(['auth:sanctum', 'role:admin_ga,admin_ga_manager,super_admin'])->prefix('assets')->group(function () {
    Route::post('/{id}/maintenance/start', [AssetController::class, 'startMaintenance']);
    Route::post('/{id}/maintenance/complete', [AssetController::class, 'completeMaintenance']);
});

Route::middleware(['auth:sanctum', 'role:admin_ga,admin_ga_manager,super_admin'])->prefix('assets')->group(function () {
    Route::post('/{id}/maintenance/approve', [AssetController::class, 'approveMaintenance']);
    Route::post('/{id}/maintenance/reject', [AssetController::class, 'rejectMaintenance']);
});


// User and Admin: update asset status
Route::middleware(['auth:sanctum', 'role:user,admin_ga,admin_ga_manager,super_admin'])->prefix('assets')->group(function () {
    Route::patch('/{id}/user-status', [AssetController::class, 'updateUserStatus']);
    // Allow form-data POST for user status update to avoid multipart PATCH issues
    Route::post('/{id}/user-status', [AssetController::class, 'updateUserStatus']);
});

// ---------------- MEETINGS ----------------
// All roles can access booking
Route::middleware('auth:sanctum')->prefix('meetings')->group(function () {
    Route::get('/', [MeetingController::class, 'index']);
    Route::get('/stats', [MeetingController::class, 'stats']);
    Route::get('/{id}', [MeetingController::class, 'show']);
    Route::post('/', [MeetingController::class, 'store']);
    Route::patch('/{id}', [MeetingController::class, 'update']);
    Route::patch('/{id}/start', [MeetingController::class, 'start']);
    Route::patch('/{id}/end', [MeetingController::class, 'end']);
    Route::patch('/{id}/force-end', [MeetingController::class, 'forceEnd'])->middleware('role:admin_ga,admin_ga_manager,super_admin');
    Route::delete('/{meeting}', [MeetingController::class, 'destroy']);
});

// ---------------- VISITORS ----------------
Route::middleware(['auth:sanctum', 'role:admin_ga,admin_ga_manager,super_admin'])->prefix('visitors')->group(function () {
    Route::get('/', [VisitorController::class, 'index']);
    Route::get('/stats', [VisitorController::class, 'stats']);
    Route::post('/', [VisitorController::class, 'store']);
    Route::get('/{id}', [VisitorController::class, 'show']);
    Route::put('/{id}', [VisitorController::class, 'update']);
    Route::patch('/{id}', [VisitorController::class, 'update']);
    // Allow form-data POST for update to avoid multipart PUT/PATCH issues
    Route::post('/{id}', [VisitorController::class, 'update']);
    Route::delete('/{id}', [VisitorController::class, 'destroy']);
    Route::post('/{id}/check-in', [VisitorController::class, 'checkIn']);
    Route::post('/{id}/check-out', [VisitorController::class, 'checkOut']);
});

// (Debug routes removed)
