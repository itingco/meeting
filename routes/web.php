<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
Route::redirect('/', '/meetings');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
});
Route::middleware(['auth', 'account.active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/profile/password', [UserController::class, 'passwordForm'])->name('profile.password');
    Route::post('/profile/password', [UserController::class, 'updatePassword'])->middleware('throttle:6,1')->name('profile.password.save');
    Route::get('/meetings', [MeetingController::class, 'index'])->name('meetings.index');
    Route::post('/meetings', [MeetingController::class, 'store'])->name('meetings.store');
    Route::get('/meetings/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
    Route::get('/meetings/{meeting}/status', [MeetingController::class, 'status'])->name('meetings.status');
    Route::post('/meetings/{meeting}/chunks', [MeetingController::class, 'uploadChunk'])->name('meetings.chunks');
    Route::post('/meetings/{meeting}/finish', [MeetingController::class, 'finish'])->name('meetings.finish');
    Route::post('/meetings/{meeting}/repair', [MeetingController::class, 'repair'])->middleware('throttle:8,1')->name('meetings.repair');
    Route::post('/meetings/{meeting}/generate', [MeetingController::class, 'generate'])->middleware('throttle:8,1')->name('meetings.generate');
    Route::post('/meetings/{meeting}/sync-drive', [MeetingController::class, 'syncDrive'])->middleware('throttle:8,1')->name('meetings.sync-drive');
    Route::post('/meetings/{meeting}/retry', [MeetingController::class, 'generate'])->name('meetings.retry');
    Route::get('/meetings/{meeting}/pdf', [MeetingController::class, 'pdf'])->name('meetings.pdf');
    Route::get('/meetings/{meeting}/word', [MeetingController::class, 'pdf'])->name('meetings.word'); // kompatibilitas tautan lama
    Route::get('/meetings/{meeting}/audio', [MeetingController::class, 'audio'])->name('meetings.audio');
    Route::middleware('can:manage-settings')->group(function () {
        Route::get('/settings/recipients', [SettingController::class, 'recipients'])->name('settings.recipients');
        Route::post('/settings/recipients', [SettingController::class, 'saveRecipients'])->name('settings.recipients.save');
        Route::get('/settings/google/connect', [SettingController::class, 'googleConnect'])->name('settings.google.connect');
        Route::get('/settings/google/callback', [SettingController::class, 'googleCallback'])->name('settings.google.callback');
        Route::post('/settings/google/disconnect', [SettingController::class, 'googleDisconnect'])->name('settings.google.disconnect');
    });
    Route::middleware('can:manage-users')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });
});
