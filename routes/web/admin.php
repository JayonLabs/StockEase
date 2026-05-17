<?php

use App\Http\Controllers\Permission\PermissionController;
use App\Http\Controllers\Permission\RolePermissionController;
use App\Http\Controllers\Permission\UserPermissionController;
use App\Http\Controllers\Product\CategoryController;
use App\Http\Controllers\Product\PromotionController;
use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::put('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

    Route::resource('category', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('promotions', PromotionController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('permissions', PermissionController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('role-permissions', [RolePermissionController::class, 'index'])->name('role-permissions.index');
    Route::get('role-permissions/{role}/edit', [RolePermissionController::class, 'edit'])->name('role-permissions.edit');
    Route::put('role-permissions/{role}', [RolePermissionController::class, 'update'])->name('role-permissions.update');
    Route::get('user-permissions', [UserPermissionController::class, 'index'])->name('user-permissions.index');
    Route::get('user-permissions/{user}/edit', [UserPermissionController::class, 'edit'])->name('user-permissions.edit');
    Route::put('user-permissions/{user}', [UserPermissionController::class, 'update'])->name('user-permissions.update');
});
