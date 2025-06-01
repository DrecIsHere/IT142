<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LiquorController; // Ensure this use statement is present
use App\Http\Controllers\GoogleAuthController;
// ... other routes ...


Route::get('/', function () {
    if (Auth::check()) { // Check if user is authenticated
        return redirect()->route('liquor.index');
    }
    // If not authenticated, show a simple view with a login button
    // We can create a simple blade view for this, or return HTML directly for now
    return view('welcome'); // Assuming you have a welcome.blade.php
})->name('home');


// Routes for Liquor Management
Route::get('/liquor', [LiquorController::class, 'index'])->name('liquor.index');
Route::get('/liquor/create', [LiquorController::class, 'create'])->name('liquor.create');
Route::post('/liquor', [LiquorController::class, 'store'])->name('liquor.store');

// Ensure these routes for Edit and Update are present and correct:
Route::get('/liquor/{id}/edit', [LiquorController::class, 'edit'])->name('liquor.edit'); // <<< THIS IS THE ONE FOR THE ERROR
Route::put('/liquor/{id}', [LiquorController::class, 'update'])->name('liquor.update');
    
Route::delete('/liquor/{id}', [LiquorController::class, 'destroy'])->name('liquor.destroy');
// ... potentially other routes ...


Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
Route::post('/logout', [GoogleAuthController::class, 'logout'])->name('logout');

// Routes for Liquor Management
Route::get('/liquor', [LiquorController::class, 'index'])->name('liquor.index');
Route::get('/liquor/create', [LiquorController::class, 'create'])->name('liquor.create');
Route::post('/liquor', [LiquorController::class, 'store'])->name('liquor.store');
Route::get('/liquor/{id}/edit', [LiquorController::class, 'edit'])->name('liquor.edit');
Route::put('/liquor/{id}', [LiquorController::class, 'update'])->name('liquor.update');
Route::delete('/liquor/{id}', [LiquorController::class, 'destroy'])->name('liquor.destroy');
Auth::routes();

