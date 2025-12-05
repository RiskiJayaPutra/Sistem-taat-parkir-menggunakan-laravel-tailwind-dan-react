<?php

namespace App\Http\Controllers;

use App\Models\Mahasiswa;
use App\Models\User;
use App\Models\StatusParkir;
use App\Models\Kendaraan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB; // Impor DB facade

class MahasiswaController extends Controller
{
    /**
     * Mengambil semua data mahasiswa (untuk tabel Admin).
     */
    public function index()
    {
        // Ambil semua mahasiswa, urutkan dari yang terbaru,
        // dan ambil data relasinya (prodi, kendaraan)
        $mahasiswa = Mahasiswa::with(['prodi', 'kendaraan'])
                            ->orderBy('created_at', 'desc')
                            ->get();
        return response()->json($mahasiswa);
    }

    /**
     * Mengambil data satu mahasiswa (untuk form edit).
     */
    public function show($id)
    {
        $mahasiswa = Mahasiswa::with(['prodi', 'kendaraan', 'user'])->find($id);
        if (!$mahasiswa) {
            return response()->json(['message' => 'Mahasiswa tidak ditemukan'], 404);
        }
        return response()->json($mahasiswa);
    }

    /**
     * Menyimpan mahasiswa baru.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'nama' => 'required|string|max:255',
            'npm' => 'required|string|max:15|unique:mahasiswa',
            'prodi_id' => 'required|integer|exists:prodi,id',
            'angkatan' => 'required|string|max:4',
            'foto_ktm' => 'nullable|file|mimes:pdf|max:2048', // <-- DIUBAH
            'password' => 'nullable|string|min:6', // Tambahkan validasi password

            // Data Kendaraan (minimal 1)
            'kendaraan' => 'required|array|min:1',
            'kendaraan.*.plat_nomor' => 'required|string|max:10|unique:kendaraan,plat_nomor',
            'kendaraan.*.foto_stnk' => 'nullable|file|mimes:pdf|max:2048',
        ]);

        // Gunakan DB Transaction agar aman (jika satu gagal, semua dibatalkan)
        return DB::transaction(function () use ($request, $validatedData) {
            // 1. Buat Akun User dulu
            // Gunakan password dari request jika ada, jika tidak gunakan password default
            $password = $request->filled('password') ? $validatedData['password'] : 'password123';
            
            $user = User::create([
                'username' => $validatedData['npm'], // Gunakan NPM sebagai username
                'password' => Hash::make($password), // Hash password dari input atau default
                'role' => 'Mahasiswa',
            ]);

            // 2. Handle Upload Foto KTM (jika ada)
            $ktmPath = null;
            if ($request->hasFile('foto_ktm')) {
                $ktmPath = $request->file('foto_ktm')->store('ktm', 'public');
            }

            // 3. Buat Mahasiswa
            $mahasiswa = Mahasiswa::create([
                'user_id' => $user->id,
                'nama' => $validatedData['nama'],
                'npm' => $validatedData['npm'],
                'prodi_id' => $validatedData['prodi_id'],
                'angkatan' => $validatedData['angkatan'],
                'foto_ktm' => $ktmPath,
            ]);

            // 4. Buat Status Parkir
            StatusParkir::create([
                'mahasiswa_id' => $mahasiswa->id,
            ]);

            // 5. Buat Kendaraan
            foreach ($request->file('kendaraan', []) as $index => $fileData) {
                $stnkPath = null;
                if (isset($fileData['foto_stnk'])) {
                    $stnkPath = $fileData['foto_stnk']->store('stnk', 'public');
                }

                Kendaraan::create([
                    'mahasiswa_id' => $mahasiswa->id,
                    'plat_nomor' => $validatedData['kendaraan'][$index]['plat_nomor'],
                    'foto_stnk' => $stnkPath,
                ]);
            }

            return response()->json(['message' => 'Mahasiswa berhasil dibuat'], 201);
        });
    }

    /**
     * Update data mahasiswa (Logika UPDATE lebih rumit)
     * Kita akan gunakan POST dan custom field _method
     */
    public function update(Request $request, $id)
    {
        $mahasiswa = Mahasiswa::find($id);
        if (!$mahasiswa) {
            return response()->json(['message' => 'Mahasiswa tidak ditemukan'], 404);
        }

        $validatedData = $request->validate([
            'nama' => 'required|string|max:255',
            'npm' => ['required', 'string', 'max:15', Rule::unique('mahasiswa')->ignore($id)],
            'prodi_id' => 'required|integer|exists:prodi,id',
            'angkatan' => 'required|string|max:4',
            'foto_ktm' => 'nullable|file|mimes:pdf|max:2048', // <-- DIUBAH
            'password' => 'nullable|string|min:6', // Tambahkan validasi password
        ]);

        // Handle Update Foto KTM
        $ktmPath = $mahasiswa->foto_ktm;
        if ($request->hasFile('foto_ktm')) {
            // Hapus foto lama jika ada
            if ($mahasiswa->foto_ktm) {
                Storage::disk('public')->delete($mahasiswa->foto_ktm);
            }
            // Upload foto baru
            $ktmPath = $request->file('foto_ktm')->store('ktm', 'public');
        }

        // Update data mahasiswa
        $mahasiswa->update([
            'nama' => $validatedData['nama'],
            'npm' => $validatedData['npm'],
            'prodi_id' => $validatedData['prodi_id'],
            'angkatan' => $validatedData['angkatan'],
            'foto_ktm' => $ktmPath,
        ]);

        // Update username dan password di tabel User
        if ($mahasiswa->user) {
            $userUpdateData = [];
            
            // Update username jika NPM berubah
            if ($mahasiswa->user->username !== $validatedData['npm']) {
                $userUpdateData['username'] = $validatedData['npm'];
            }
            
            // Update password jika diisi
            if ($request->filled('password')) {
                $userUpdateData['password'] = Hash::make($validatedData['password']);
            }
            
            // Lakukan update jika ada perubahan
            if (!empty($userUpdateData)) {
                $mahasiswa->user->update($userUpdateData);
            }
        }

        // (Logika update/tambah/hapus kendaraan kita buat terpisah nanti agar lebih simpel)

        return response()->json(['message' => 'Mahasiswa berhasil diupdate']);
    }

    /**
     * Menghapus data mahasiswa.
     */
    public function destroy($id)
    {
        $mahasiswa = Mahasiswa::find($id);
        if (!$mahasiswa) {
            return response()->json(['message' => 'Mahasiswa tidak ditemukan'], 404);
        }

        // Hapus foto-foto terkait dari storage
        if ($mahasiswa->foto_ktm) {
            Storage::disk('public')->delete($mahasiswa->foto_ktm);
        }
        foreach ($mahasiswa->kendaraan as $kendaraan) {
            if ($kendaraan->foto_stnk) {
                Storage::disk('public')->delete($kendaraan->foto_stnk);
            }
        }

        // Hapus data mahasiswa.
        // Data terkait (user, status_parkir, kendaraan) akan
        // otomatis terhapus karena kita sudah setting 'onDelete('cascade')'
        // di migrasi (kecuali user).

        // Kita harus hapus user secara manual
        if ($mahasiswa->user) {
            $mahasiswa->user->delete();
        }
        // Sisanya (mahasiswa, status_parkir, kendaraan, laporan) akan ter-cascade
        // $mahasiswa->delete(); // Ini tidak perlu jika user-nya di-delete

        return response()->json(['message' => 'Mahasiswa berhasil dihapus']);
    }

    /**
     * Mengambil data dashboard mahasiswa yang sedang login.
     */
    public function getMyDashboard(Request $request)
    {
        $user = $request->user();
        
        // Jika Satpam, kembalikan semua laporan untuk divalidasi
        if ($user->role === 'Satpam') {
            $semuaLaporan = \App\Models\LaporanPelanggaran::with([
                    'pelapor:id,nama,username',
                    'validator:id,nama,username'
                ])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($laporan) {
                    return [
                        'id' => $laporan->id,
                        'plat_nomor_terlapor' => $laporan->plat_nomor_terlapor,
                        'deskripsi' => $laporan->deskripsi,
                        'url_foto_bukti' => $laporan->url_foto_bukti,
                        'foto_pelanggaran' => $laporan->foto_pelanggaran,
                        'status' => $laporan->status,
                        'alasan_penolakan' => $laporan->alasan_penolakan,
                        'created_at' => $laporan->created_at,
                        'pelapor' => $laporan->pelapor ? [
                            'nama' => $laporan->pelapor->nama ?? $laporan->pelapor->username,
                            'username' => $laporan->pelapor->username,
                        ] : null,
                        'validator' => $laporan->validator ? [
                            'nama' => $laporan->validator->nama ?? $laporan->validator->username,
                            'username' => $laporan->validator->username,
                        ] : null,
                    ];
                });
            
            return response()->json([
                'nama' => $user->nama ?? $user->username,
                'role' => 'Satpam',
                'laporan' => $semuaLaporan,
            ]);
        }
        
        // Cari mahasiswa berdasarkan user_id
        $mahasiswa = Mahasiswa::where('user_id', $user->id)
                        ->with(['statusParkir', 'kendaraan', 'prodi', 'user'])
                        ->first();
        
        if (!$mahasiswa) {
            return response()->json(['message' => 'Data mahasiswa tidak ditemukan'], 404);
        }

        // Ambil daftar plat nomor mahasiswa
        $platNomor = $mahasiswa->kendaraan->pluck('plat_nomor');
        
        // Hitung pelanggaran bulan ini
        $satuBulanLalu = \Carbon\Carbon::now()->subMonth();
        $pelanggaranBulanIni = \App\Models\LaporanPelanggaran::whereIn('plat_nomor_terlapor', $platNomor)
            ->where('status', 'Valid')
            ->where('created_at', '>=', $satuBulanLalu)
            ->count();
        
        // Ambil laporan yang dibuat mahasiswa
        $laporanSaya = \App\Models\LaporanPelanggaran::where('pelapor_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Status parkir
        $statusParkir = $mahasiswa->statusParkir;
        $isBanned = false;
        $banInfo = null;
        $banExpires = null;
        
        if ($statusParkir) {
            if ($statusParkir->is_banned_permanently) {
                $isBanned = true;
                $banInfo = 'Permanen';
            } elseif ($statusParkir->ban_expires_at && \Carbon\Carbon::parse($statusParkir->ban_expires_at)->isFuture()) {
                $isBanned = true;
                $banExpires = $statusParkir->ban_expires_at;
                $banInfo = \Carbon\Carbon::parse($statusParkir->ban_expires_at)->format('d/m/Y H:i');
            }
        }
        
        // Ambil pelanggaran yang dilakukan mahasiswa
        $pelanggaranSaya = \App\Models\LaporanPelanggaran::whereIn('plat_nomor_terlapor', $platNomor)
            ->where('status', 'Valid')
            ->with(['validator:id,nama,username'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'plat_nomor_terlapor' => $item->plat_nomor_terlapor,
                    'url_foto_bukti' => $item->url_foto_bukti,
                    'created_at' => $item->created_at,
                    'validator' => $item->validator ? [
                        'nama' => $item->validator->nama ?? $item->validator->username,
                        'username' => $item->validator->username,
                    ] : null,
                ];
            });
        
        return response()->json([
            'nama' => $mahasiswa->user->nama ?? $mahasiswa->user->username,
            'npm' => $mahasiswa->npm,
            'prodi' => $mahasiswa->prodi->nama_prodi ?? '-',
            'status_parkir' => $isBanned ? 'Terblokir' : 'Aktif',
            'is_banned' => $isBanned,
            'ban_info' => $banInfo,
            'ban_expires_at' => $banExpires,
            'pelanggaran_bulan_ini' => $pelanggaranBulanIni,
            'total_laporan_saya' => $laporanSaya->count(),
            'laporan_saya' => $laporanSaya,
            'pelanggaran_saya' => $pelanggaranSaya,
        ]);
    }
}