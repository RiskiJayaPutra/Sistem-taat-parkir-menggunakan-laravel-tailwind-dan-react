<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\MahasiswaController;
use App\Http\Controllers\ProdiController;
use App\Http\Controllers\GerbangController;
use App\Http\Controllers\ProfileController;

// === Rute Publik (Tidak perlu login) ===
Route::post('/login', [AuthController::class, 'login']);

// --- PINDAHKAN RUTE INI KE SINI (DI LUAR GRUP) ---
Route::get('/cek-status/{npm}', [GerbangController::class, 'cekStatusByNpm']);
// -------------------------------------------------

// === Rute Privat (Wajib login / pakai token) ===
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/laporan', [LaporanController::class, 'store']);
    Route::get('/laporan/pending', [LaporanController::class, 'getPendingReports']);
    Route::post('/laporan/{id}/validasi', [LaporanController::class, 'validasiLaporan']);
    Route::post('/laporan/{id}/tolak', [LaporanController::class, 'tolakLaporan']);
    Route::post('/laporan/{id}/unvalidasi', [LaporanController::class, 'unvalidasiLaporan']); // Baru
    Route::put('/laporan/{id}', [LaporanController::class, 'updateLaporan']); // Baru
    Route::delete('/laporan/{id}', [LaporanController::class, 'deleteLaporan']);
    Route::get('/laporan/satpam-stats', [LaporanController::class, 'getSatpamStats']);
    Route::get('/admin/banned-users', [AdminController::class, 'getBannedUsers']);
    Route::post('/admin/unban/{mahasiswa_id}', [AdminController::class, 'unbanUser']);
    Route::post('/admin/satpam', [AdminController::class, 'createSatpam']);
    Route::get('/admin/satpam', [AdminController::class, 'getSatpam']);
    Route::get('/admin/satpam/{id}', [AdminController::class, 'getSatpamById']);
    Route::put('/admin/satpam/{id}', [AdminController::class, 'updateSatpam']);
    Route::delete('/admin/satpam/{id}', [AdminController::class, 'deleteSatpam']);
    Route::get('/admin/dashboard-stats', [AdminController::class, 'getDashboardStats']);
    Route::get('/report/top-pelanggar', [ReportController::class, 'getTopPelanggar']);
    Route::get('/report/per-prodi', [ReportController::class, 'getPelanggaranPerProdi']);
    Route::get('/my-dashboard', [MahasiswaController::class, 'getMyDashboard']);
    Route::apiResource('mahasiswa', MahasiswaController::class);
    Route::post('/mahasiswa/{id}', [MahasiswaController::class, 'update']);
    Route::get('/prodi', [ProdiController::class, 'index']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'update']);
    // (Hapus baris cek-status dari sini)
});