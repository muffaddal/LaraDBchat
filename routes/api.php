<?php

use Illuminate\Support\Facades\Route;
use LaraDBChat\Http\Controllers\LaraDBChatController;

$prefix = config('laradbchat.api.prefix', 'api/laradbchat');
$middleware = config('laradbchat.api.middleware', ['api']);

// Remove 'api/' prefix since we're already in API routes
$prefix = preg_replace('/^api\//', '', $prefix);

Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () {
        // Main query endpoint
        Route::post('/ask', [LaraDBChatController::class, 'ask'])
            ->name('laradbchat.ask');

        // Training endpoint
        Route::post('/train', [LaraDBChatController::class, 'train'])
            ->name('laradbchat.train');

        // Query history
        Route::get('/history', [LaraDBChatController::class, 'history'])
            ->name('laradbchat.history');

        // Status and info
        Route::get('/status', [LaraDBChatController::class, 'status'])
            ->name('laradbchat.status');

        // Schema info
        Route::get('/schema', [LaraDBChatController::class, 'schema'])
            ->name('laradbchat.schema');

        // Validate SQL
        Route::post('/validate', [LaraDBChatController::class, 'validate'])
            ->name('laradbchat.validate');

        // Add sample query
        Route::post('/samples', [LaraDBChatController::class, 'addSample'])
            ->name('laradbchat.samples.add');
    });
