<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InitialAdminController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\OwnerRegistrationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\WbsController;
use App\Http\Middleware\EnsureSystemIsInitialized;
use Illuminate\Support\Facades\Route;

Route::get('/setup/admin', [InitialAdminController::class, 'create'])->name('setup.admin.create');
Route::post('/setup/admin', [InitialAdminController::class, 'store'])->name('setup.admin.store')->middleware('throttle:4,1');

Route::middleware(EnsureSystemIsInitialized::class)->group(function () {
    // 初期セットアップ判定後、既存HOMEルートへ接続する。Bladeはここから直接返さない。
    Route::get('/', [HomeController::class, 'redirectToHome'])->name('top');

    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'create'])->name('login');
        Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:6,1');
        Route::get('/register-owner', [OwnerRegistrationController::class, 'create'])->name('owner.register');
        Route::post('/register-owner', [OwnerRegistrationController::class, 'store'])->middleware('throttle:4,1');
    });

    Route::get('/invitations/{token}/password', [InvitationController::class, 'password'])->name('invitations.password');
    Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.accept')->middleware('throttle:10,1');

    Route::middleware(['auth', 'active'])->scopeBindings()->group(function () {
        Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
        Route::get('/home', HomeController::class)->name('home');
        Route::get('/wbs', WbsController::class)->name('wbs');
        Route::resource('projects', ProjectController::class)->except('index');
        Route::post('/projects/{project}/invitations', [InvitationController::class, 'store'])->name('projects.invitations.store')->middleware('throttle:10,1');
        Route::patch('/projects/{project}/invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('projects.invitations.resend')->middleware('throttle:10,1');
        Route::delete('/projects/{project}/invitations/{invitation}', [InvitationController::class, 'revoke'])->name('projects.invitations.revoke');
        Route::post('/projects/{project}/tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::get('/projects/{project}/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
        Route::patch('/projects/{project}/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::delete('/projects/{project}/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
        Route::post('/projects/{project}/tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('comments.store')->middleware('throttle:20,1');
        Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
        Route::patch('/admin/organizations/{organization}/status', [AdminController::class, 'organizationStatus'])->name('admin.organizations.status');
        Route::patch('/admin/users/{user}/status', [AdminController::class, 'userStatus'])->name('admin.users.status');
        Route::patch('/admin/trash/{type}/{id}', [AdminController::class, 'restore'])->name('admin.trash.restore');
        Route::delete('/admin/trash/{type}/{id}', [AdminController::class, 'forceDelete'])->name('admin.trash.force-delete');
    });
});
