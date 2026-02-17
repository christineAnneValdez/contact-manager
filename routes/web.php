<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ContactCrudController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SevDeskController;
use App\Http\Middleware\CheckIfAdmin;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('admin/dashboard', [DashboardController::class, 'index'])
    ->name('backpack.custom.dashboard');

Route::get('admin/sevdesk/test', [SevDeskController::class, 'testConnection'])
    ->middleware(CheckIfAdmin::class)
    ->name('admin.sevdesk.test');

Route::get('admin/contact/sync-sevdesk', [ContactCrudController::class, 'syncSevDesk'])
    ->middleware(CheckIfAdmin::class)
    ->name('admin.contact.sync_sevdesk');

Route::get('admin/contact/push-sevdesk', [ContactCrudController::class, 'pushSevDesk'])
    ->middleware(CheckIfAdmin::class)
    ->name('admin.contact.push_sevdesk');
