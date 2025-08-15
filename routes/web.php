<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;

// ---------- Public pages ----------
Route::view('/', 'home')->name('home');          // serves resources/views/home.blade.php
Route::view('/about', 'about')->name('about');   // serves resources/views/about.blade.php

// ---------- Dashboard (Breeze redirect after registration) ----------
// Use Route::view(...)->middleware([...])  (correct chaining)
Route::view('/dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// ---------- Private pages (login + verified email required) ----------
Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('/scan', 'create')->name('scan.create');        // serves resources/views/create.blade.php
    Route::view('/history', 'history')->name('scan.history');   // serves resources/views/history.blade.php
    Route::view('/stats', 'stats')->name('stats');              // serves resources/views/stats.blade.php
});

// ---------- Breeze profile pages (login required) ----------
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ---------- Breeze authentication routes ----------
require __DIR__ . '/auth.php';
