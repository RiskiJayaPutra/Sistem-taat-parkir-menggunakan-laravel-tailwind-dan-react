<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User; // <-- IMPORT MODEL
use App\Models\Mahasiswa; // <-- IMPORT MODEL
use Illuminate\Support\Facades\Hash; // <-- IMPORT UNTUK HASH PASSWORD
use App\Models\StatusParkir; // <-- TAMBAHKAN INI

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Hapus data lama agar tidak duplikat

        // 1. BUAT AKUN ADMIN
        User::create([
            'username' => 'admin',
            'nama' => 'Administrator',
            'password' => Hash::make('password'), // passwordnya: 'password'
            'role' => 'Admin'
        ]);

        // 2. BUAT AKUN SATPAM
        User::create([
            'username' => 'satpam01',
            'nama' => 'Satpam Satu',
            'password' => Hash::make('password'), // passwordnya: 'password'
            'role' => 'Satpam'
        ]);

        // 3. BUAT AKUN MAHASISWA (CONTOH)
        // Saat buat akun mahasiswa, kita juga buat data profilnya
        $mahasiswaUser = User::create([
            'username' => '111222333', // Ini NPM-nya
            'nama' => 'Budi Santoso',
            'password' => Hash::make('password'), // passwordnya: 'password'
            'role' => 'Mahasiswa'
        ]);

        // Buat data profil di tabel 'mahasiswa' dan hubungkan
        // Buat data profil di tabel 'mahasiswa' dan hubungkan
        $mahasiswa = Mahasiswa::create([ // <-- TAMBAHKAN "$mahasiswa =" DI SINI
            'user_id' => $mahasiswaUser->id, 
            'prodi_id' => 1,
            'npm' => '111222333',
            'nama' => 'Budi Santoso',
            'angkatan' => '2021',
        ]);
        
        // --- TAMBAHKAN BLOK BARU INI ---
    // Buat status parkir default untuk mahasiswa ini
        StatusParkir::create([
            'mahasiswa_id' => $mahasiswa->id, // <-- INI BENAR (mengambil id = 1)
            'ban_expires_at' => null,
            'is_banned_permanently' => false,
        ]);
    }
}