<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OcrAiController;

Route::get('/', function () {
    return view('welcome');
});

// OCR + Generative AI journaling routes
Route::get('/journal', [OcrAiController::class, 'index'])->name('journal.index');
Route::post('/journal/process', [OcrAiController::class, 'process'])->name('journal.process');
Route::post('/journal/generate', [OcrAiController::class, 'generate'])->name('journal.generate');
Route::get('/journal/table', [OcrAiController::class, 'showTable'])->name('journal.table');
