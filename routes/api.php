<?php

use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DebtController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\LegacyAccessRequestController;
use App\Http\Controllers\Api\V1\LegacyContactController;
use App\Http\Controllers\Api\V1\ReminderController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Version 1
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1 automatically via RouteServiceProvider.
| Public routes (no auth) are defined first, followed by protected routes.
|
*/

// ──────────────────────────────────────────────
// Public Routes (No Authentication Required)
// ──────────────────────────────────────────────

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])
            ->middleware('throttle:10,1');
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:10,1');
        Route::post('google', [AuthController::class, 'google'])
            ->middleware('throttle:10,1');
    });

    // ──────────────────────────────────────────────
    // Public Legacy Access Routes
    // ──────────────────────────────────────────────

    Route::prefix('legacy-contacts')->group(function () {
        Route::get('verify-email', [LegacyContactController::class, 'verifyEmail'])
            ->middleware('throttle:10,1');
    });

    Route::post('legacy-access-requests', [LegacyAccessRequestController::class, 'store'])
        ->middleware('throttle:5,1');

    Route::get('legacy-access-requests/{id}/estate-report', [LegacyAccessRequestController::class, 'estateReport'])
        ->middleware('throttle:10,1');

    // ──────────────────────────────────────────────
    // Protected Routes (Authentication Required)
    // ──────────────────────────────────────────────

    Route::middleware('auth:sanctum')->group(function () {

        // Auth & Profile
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('user', [AuthController::class, 'user']);
            Route::put('user', [AuthController::class, 'updateUser']);
            Route::post('avatar', [AuthController::class, 'uploadAvatar']);
            Route::delete('avatar', [AuthController::class, 'deleteAvatar']);
            Route::put('pin/login', [AuthController::class, 'setLoginPin']);
            Route::put('pin/transaction', [AuthController::class, 'setTransactionPin']);
            Route::post('pin/transaction/verify', [AuthController::class, 'verifyTransactionPin']);
        });

        // Contacts
        Route::prefix('contacts')->group(function () {
            Route::get('/', [ContactController::class, 'index']);
            Route::post('/', [ContactController::class, 'store']);
            Route::get('favorites', [ContactController::class, 'favorites']);
            Route::get('frequent', [ContactController::class, 'frequent']);
            Route::get('{id}', [ContactController::class, 'show']);
            Route::put('{id}', [ContactController::class, 'update']);
            Route::delete('{id}', [ContactController::class, 'destroy']);
        });

        // Debts
        Route::prefix('debts')->group(function () {
            Route::get('/', [DebtController::class, 'index']);
            Route::post('/', [DebtController::class, 'store']);
            Route::get('{id}', [DebtController::class, 'show']);
            Route::put('{id}', [DebtController::class, 'update']);
            Route::delete('{id}', [DebtController::class, 'destroy']);

            // Transactions nested under debts
            Route::get('{id}/transactions', [TransactionController::class, 'index']);
            Route::post('{id}/transactions', [TransactionController::class, 'store']);
        });

        // Recent transactions (across all debts)
        Route::get('transactions/recent', [TransactionController::class, 'recent']);

        // Reminders
        Route::prefix('reminders')->group(function () {
            Route::get('/', [ReminderController::class, 'index']);
            Route::post('/', [ReminderController::class, 'store']);
            Route::get('upcoming', [ReminderController::class, 'upcoming']);
            Route::put('{id}', [ReminderController::class, 'update']);
            Route::delete('{id}', [ReminderController::class, 'destroy']);
        });

        // Analytics
        Route::prefix('analytics')->group(function () {
            Route::get('dashboard', [AnalyticsController::class, 'dashboard']);
            Route::get('insights', [AnalyticsController::class, 'insights']);
        });

        // Export
        Route::prefix('export')->group(function () {
            Route::get('csv', [ExportController::class, 'csv']);
            Route::get('pdf', [ExportController::class, 'pdf']);
            Route::get('csv/contact/{contactId}', [ExportController::class, 'contactCsv']);
            Route::get('pdf/contact/{contactId}', [ExportController::class, 'contactPdf']);
        });

        // Sync Engine
        Route::prefix('sync')->group(function () {
            Route::post('push', [SyncController::class, 'push']);
            Route::get('pull', [SyncController::class, 'pull']);
            Route::get('status', [SyncController::class, 'status']);
            Route::post('resolve', [SyncController::class, 'resolve']);
        });

        // ──────────────────────────────────────────────
        // Legacy Access (Authenticated)
        // ──────────────────────────────────────────────

        // Legacy Settings
        Route::prefix('legacy')->group(function () {
            Route::get('settings', [LegacyContactController::class, 'settings']);
            Route::post('enable', [LegacyContactController::class, 'enable']);
            Route::post('disable', [LegacyContactController::class, 'disable']);
            Route::post('recovery-code/regenerate', [LegacyContactController::class, 'regenerateRecoveryCode']);
            Route::put('waiting-period', [LegacyContactController::class, 'updateWaitingPeriod']);
            Route::get('audit-logs', [LegacyContactController::class, 'auditLogs']);
        });

        // Legacy Contacts CRUD
        Route::prefix('legacy-contacts')->group(function () {
            Route::get('/', [LegacyContactController::class, 'index']);
            Route::post('/', [LegacyContactController::class, 'store']);
            Route::get('{id}', [LegacyContactController::class, 'show']);
            Route::patch('{id}', [LegacyContactController::class, 'update']);
            Route::delete('{id}', [LegacyContactController::class, 'destroy']);
            Route::post('{id}/resend-verification', [LegacyContactController::class, 'resendVerification']);
            Route::post('{id}/revoke', [LegacyContactController::class, 'revoke']);
        });

        // Legacy Access Requests (owner management)
        Route::prefix('legacy-access-requests')->group(function () {
            Route::get('/', [LegacyAccessRequestController::class, 'index']);
            Route::get('{id}', [LegacyAccessRequestController::class, 'show']);
            Route::post('{id}/approve', [LegacyAccessRequestController::class, 'approve']);
            Route::post('{id}/deny', [LegacyAccessRequestController::class, 'deny']);
        });
    });
});

