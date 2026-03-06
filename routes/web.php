<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OcrAiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\JournalController;

// ===================== HALAMAN UTAMA =====================
Route::get('/', function () {
    return view('welcome');
});

// ===================== AUTENTIKASI =====================
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ===================== OCR + AI JOURNALING =====================
// Halaman upload dokumen
Route::get('/journal', [OcrAiController::class, 'index'])->name('journal.index');

// Proses OCR dokumen
Route::post('/journal/process', [OcrAiController::class, 'process'])->name('journal.process');

// Generate jurnal dengan AI
Route::post('/journal/generate', [OcrAiController::class, 'generate'])->name('journal.generate');

// Preview hasil generate
Route::get('/journal/table', [OcrAiController::class, 'showTable'])->name('journal.table');

// API: Get units dan accounts
Route::get('/api/units', [OcrAiController::class, 'getUnits'])->name('api.units');
Route::get('/api/units/{unitId}/accounts', [OcrAiController::class, 'getAccountsByUnit'])->name('api.accounts');

// ===================== JURNAL UMUM =====================
// Daftar jurnal
Route::get('/journals', [JournalController::class, 'index'])->name('journals.list');

// Detail jurnal
Route::get('/journals/{journal}', [JournalController::class, 'show'])->name('journals.show');

// Simpan jurnal baru
Route::post('/journals', [JournalController::class, 'store'])->name('journals.store');

// Update status jurnal
Route::put('/journals/{journal}/status', [JournalController::class, 'updateStatus'])->name('journals.status');

// Hapus jurnal
Route::delete('/journals/{journal}', [JournalController::class, 'destroy'])->name('journals.destroy');
