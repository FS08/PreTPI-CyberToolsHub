<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\UrlscanController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\DashboardController;

// ---------- Public pages ----------
Route::view('/', 'home')->name('home');
Route::view('/about', 'about')->name('about');

// ---------- Private pages (login + verified email required) ----------
Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard (main landing page after login/registration)
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Scan page (form)
    Route::view('/scan', 'create')->name('scan.create');

    // Upload handler (validation + parse + persist)
    Route::post('/scan', [ScanController::class, 'store'])->name('scan.store');

    // History list (pagination + filters)
    Route::get('/history', [ScanController::class, 'history'])->name('scan.history');
    // Ajax/partial endpoint that returns only the table block
    Route::get('/history/partial', [ScanController::class, 'historyPartial'])->name('scan.history.partial');

    // Scan details (owner-only)
    Route::get('/history/{scan}', [ScanController::class, 'show'])->name('scan.show');

    // Stats (page + data for charts)
    Route::get('/stats', [StatsController::class, 'index'])->name('stats');
    Route::get('/stats/data', [StatsController::class, 'data'])->name('stats.data');

    // ---------- Dev: temporary page for Urlscan.io testing ----------
    Route::get('/dev/urlscan', [UrlscanController::class, 'index'])->name('dev.urlscan.index');
    Route::get('/dev/urlscan/search', [UrlscanController::class, 'search'])->name('dev.urlscan.search');
    Route::post('/dev/urlscan/submit', [UrlscanController::class, 'submit'])->name('dev.urlscan.submit');
});

// ---------- Profile / Account management (built-in with Breeze) ----------
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ---------- Breeze authentication routes ----------
require __DIR__ . '/auth.php';
