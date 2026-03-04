<?php

use Illuminate\Support\Facades\Route;
use Webkul\WhiteLabel\Http\Controllers\WhiteLabelController;

Route::group(['prefix' => 'admin/api', 'middleware' => ['web', 'admin_locale', 'user']], function () {
    Route::get('white-label', [WhiteLabelController::class, 'index'])
        ->name('admin.white_label.index');

    Route::post('white-label', [WhiteLabelController::class, 'update'])
        ->name('admin.white_label.update');
});

Route::get('white-label/css', [WhiteLabelController::class, 'css'])
    ->name('white_label.css');
