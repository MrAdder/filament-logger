<?php

use Illuminate\Support\Facades\Route;
use MrAdder\FilamentLogger\Http\Controllers\DownloadActivityExportController;

if (config('filament-logger.exports.queue.routes', true)) {
    Route::middleware(config('filament-logger.exports.queue.route_middleware', ['web', 'signed']))
        ->prefix(config('filament-logger.exports.queue.route_prefix', 'filament-logger'))
        ->group(function (): void {
            Route::get('exports/{owner}/{path}', DownloadActivityExportController::class)
                ->name('filament-logger.exports.download');
        });
}
