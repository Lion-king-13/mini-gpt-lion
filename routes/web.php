<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AskController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\InstructionsController;


Route::inertia('/', 'Welcome')->name('home');


Route::middleware(['auth'])->group(function () {

    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat/model', [ChatController::class, 'updateModel'])->name('chat.model');
    Route::get('/chat/{conversation}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat', [ChatController::class, 'store'])->name('chat.store');

    Route::get('/instructions', [InstructionsController::class, 'edit'])->name('instructions.edit');
    Route::post('/instructions', [InstructionsController::class, 'update'])->name('instructions.update');

    Route::get('/ask', [AskController::class, 'index'])->name('ask.index');
    Route::post('/ask', [AskController::class, 'ask'])->name('ask.post');
});


require __DIR__.'/settings.php';
