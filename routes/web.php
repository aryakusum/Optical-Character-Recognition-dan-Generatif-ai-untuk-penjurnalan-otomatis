<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OcrAiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\JournalController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,1');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/journal', [OcrAiController::class, 'index'])->name('journal.index');
    Route::post('/journal/process', [OcrAiController::class, 'process'])->middleware('throttle:10,1')->name('journal.process');
    Route::post('/journal/generate', [OcrAiController::class, 'generate'])->middleware('throttle:10,1')->name('journal.generate');
    Route::get('/journal/table', [OcrAiController::class, 'showTable'])->name('journal.table');

    Route::get('/api/units', [OcrAiController::class, 'getUnits'])->name('api.units');
    Route::get('/api/units/{unitId}/accounts', [OcrAiController::class, 'getAccountsByUnit'])->name('api.accounts');

    Route::get('/journals', [JournalController::class, 'index'])->name('journals.list');
    Route::get('/journals/{journal}', [JournalController::class, 'show'])->name('journals.show');
    Route::post('/journals', [JournalController::class, 'store'])->name('journals.store');

    Route::put('/journals/{journal}/status', [JournalController::class, 'updateStatus'])->name('journals.status');
    Route::put('/journals/{journal}/verify-unit', [JournalController::class, 'verifyUnit'])->name('journals.verify-unit');

    Route::put('/journals/{journal}/verify-finance', [JournalController::class, 'verifyFinance'])
        ->middleware('role:verifikator,admin')
        ->name('journals.verify-finance');

    Route::put('/journals/{journal}/reject', [JournalController::class, 'reject'])->name('journals.reject');

    Route::get('/journals/{journal}/document', [JournalController::class, 'viewDocument'])->name('journals.document');
    Route::get('/journals/{journal}/document/download', [JournalController::class, 'downloadDocument'])->name('journals.document.download');

    Route::delete('/journals/{journal}', [JournalController::class, 'destroy'])->name('journals.destroy');
});
