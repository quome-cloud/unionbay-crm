<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\ActionStream\ActionStreamController;
use Webkul\Admin\Http\Controllers\ActionStream\TeamStreamController;

Route::controller(ActionStreamController::class)->prefix('action-stream')->group(function () {
    Route::get('', 'index')->name('admin.action-stream.index');
    Route::get('stream', 'stream')->name('admin.action-stream.stream');
    Route::get('overdue-count', 'overdueCount')->name('admin.action-stream.overdue-count');
    Route::get('list', 'list')->name('admin.action-stream.list');
    Route::post('', 'store')->name('admin.action-stream.store');
    Route::put('{id}', 'update')->name('admin.action-stream.update');
    Route::post('{id}/complete', 'complete')->name('admin.action-stream.complete');
    Route::post('{id}/snooze', 'snooze')->name('admin.action-stream.snooze');
});

Route::controller(TeamStreamController::class)->prefix('team-stream')->group(function () {
    Route::get('', 'index')->name('admin.team-stream.index');
    Route::get('members', 'members')->name('admin.team-stream.members');
    Route::get('stream', 'stream')->name('admin.team-stream.stream');
});
