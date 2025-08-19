<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScanController; // controller for scan + history

// ---------- Public pages ----------
Route::view('/', 'home')->name('home');          // serves resources/views/home.blade.php
Route::view('/about', 'about')->name('about');   // serves resources/views/about.blade.php

// ---------- Dashboard (Breeze redirect after registration) ----------
Route::view('/dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// ---------- Private pages (login + verified email required) ----------
Route::middleware(['auth', 'verified'])->group(function () {
    // Scan page (form)
    Route::view('/scan', 'create')->name('scan.create'); // resources/views/create.blade.php

    // Upload handler (validation + parse + persist)
    Route::post('/scan', [ScanController::class, 'store'])->name('scan.store');

    // History (now uses controller action to fetch paginated scans)
    Route::get('/history', [ScanController::class, 'history'])->name('scan.history');

    // Stats (placeholder view for now)
    Route::view('/stats', 'stats')->name('stats'); // resources/views/stats.blade.php
});

// ---------- Breeze profile pages (login required) ----------
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ---------- Breeze authentication routes ----------
require __DIR__ . '/auth.php';