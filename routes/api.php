<?php

declare(strict_types=1);

use App\Http\Controllers\AdScriptController;
use App\Http\Middleware\ValidateN8nSignature;
use Illuminate\Support\Facades\Route;

Route::prefix('ad-scripts')->group(function () {
    Route::post('/', [AdScriptController::class, 'store'])
        ->name('ad-scripts.store');

    Route::get('/{task}', [AdScriptController::class, 'show'])
        ->name('ad-scripts.show');

    Route::post('/{task}/result', [AdScriptController::class, 'result'])
        ->middleware(ValidateN8nSignature::class)
        ->name('ad-scripts.result');
});
