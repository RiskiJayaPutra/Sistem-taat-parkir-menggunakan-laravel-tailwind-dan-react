<?php

namespace App\Http\Controllers;

use App\Models\Kendaraan;
use App\Models\LaporanPelanggaran;
use App\Models\Mahasiswa;
use App\Models\KartuParkir;
use App\Models\StatusParkir;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LaporanController extends Controller
{
   public function store(Request $request)
    {
        $validatedData = $request->validate([
            'plat_nomor_terlapor' => 'required|string|max:10',
            'deskripsi' => 'required|string|min:10',
            'fotos' => 'required|array|min:1|max:5', // Support multiple foto (1-5)
            'fotos.*' => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $kendaraan = Kendaraan::where('plat_nomor', $validatedData['plat_nomor_terlapor'])->first();
        if (!$kendaraan) {
            return response()->json([
                'message' => 'Plat nomor tidak terdaftar di sistem.'
            ], 404);
        }

        // Upload multiple foto
        $fotoPaths = [];
        foreach ($request->file('fotos') as $foto) {
            $path = $foto->store('laporan', 'public');
            $fotoPaths[] = $path;
        }

        $laporan = LaporanPelanggaran::create([
            'pelapor_id' => Auth::id(),
            'plat_nomor_terlapor' => $validatedData['plat_nomor_terlapor'],
            'deskripsi' => $validatedData['deskripsi'],
            'url_foto_bukti' => $fotoPaths[0], // Foto utama = foto pertama
            'foto_pelanggaran' => $fotoPaths,  // Simpan semua foto
            'status' => 'Pending',
        ]);

        return response()->json([
            'message' => 'Laporan berhasil dikirim dengan ' . count($fotoPaths) . ' foto. Satu laporan hanya untuk 1 kendaraan.',
            'data' => $laporan
        ], 201);
    }

    /**
     * Mengambil semua laporan yang statusnya Pending.
     */
    public function getPendingReports(Request $request)
    {
        if ($request->user()->role !== 'Satpam') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $laporanPending = LaporanPelanggaran::where('status', 'Pending')
                            ->with(['pelapor:id,username'])
                            ->orderBy('created_at', 'desc')
                            ->get();

        return response()->json($laporanPending, 200);
    }

    /**
     * Memvalidasi laporan oleh Satpam dan menerapkan sanksi.
     */
    public function validasiLaporan(Request $request, $id)
    {
        if ($request->user()->role !== 'Satpam') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $laporan = LaporanPelanggaran::find($id);
        if (!$laporan) {
            return response()->json(['message' => 'Laporan tidak ditemukan'], 404);
        }
        if ($laporan->status !== 'Pending') {
            return response()->json(['message' => 'Laporan ini sudah diproses'], 400);
        }

        $kendaraan = Kendaraan::where('plat_nomor', $laporan->plat_nomor_terlapor)->first();
        if (!$kendaraan) {
            return response()->json(['message' => 'Kendaraan pelat ini tidak terdaftar'], 404);
        }

        $mahasiswa = $kendaraan->mahasiswa;

        $statusParkir = StatusParkir::firstOrCreate(
            ['mahasiswa_id' => $mahasiswa->id],
            [
                'ban_expires_at' => null,
                'is_banned_permanently' => false
            ]
        );

        $daftarPlatMilikMahasiswa = $mahasiswa->kendaraan()->pluck('plat_nomor');
        
        $satuBulanLalu = Carbon::now()->subMonth();
        
        $jumlahPelanggaran = LaporanPelanggaran::whereIn('plat_nomor_terlapor', $daftarPlatMilikMahasiswa)
                                ->where('status', 'Valid')
                                ->where('created_at', '>=', $satuBulanLalu)
                                ->count();

        if ($jumlahPelanggaran == 0) {
            $statusParkir->ban_expires_at = Carbon::now()->addDays(3);
            $pesanSanksi = 'Pelanggaran ke-1: Akun diblokir selama 3 hari.';
        } elseif ($jumlahPelanggaran == 1) {
            $statusParkir->ban_expires_at = Carbon::now()->addDays(5);
            $pesanSanksi = 'Pelanggaran ke-2: Akun diblokir selama 5 hari.';
        } else {
            $statusParkir->is_banned_permanently = true;
            $statusParkir->ban_expires_at = null;
            $pesanSanksi = 'Pelanggaran ke-3: Akun diblokir permanen. Hubungi dekanat.';
        }

        $statusParkir->save();

        $laporan->status = 'Valid';
        $laporan->validated_by = $request->user()->id;
        $laporan->validated_at = Carbon::now();
        $laporan->save();

        return response()->json([
            'message' => 'Laporan berhasil divalidasi. Sanksi diterapkan.',
            'sanksi' => $pesanSanksi
        ], 200);
    }

    /**
     * Menolak laporan oleh Satpam.
     */
    public function tolakLaporan(Request $request, $id)
    {
        if ($request->user()->role !== 'Satpam') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $laporan = LaporanPelanggaran::find($id);
        if (!$laporan) {
            return response()->json(['message' => 'Laporan tidak ditemukan'], 404);
        }
        if ($laporan->status !== 'Pending') {
            return response()->json(['message' => 'Laporan ini sudah diproses'], 400);
        }

        $laporan->status = 'Ditolak';
        $laporan->validated_by = $request->user()->id;
        $laporan->validated_at = Carbon::now();
        $laporan->save();

        return response()->json([
            'message' => 'Laporan berhasil ditolak.'
        ], 200);
    }

    /**
     * Mengambil statistik laporan untuk dashboard satpam.
     */
    public function getSatpamStats(Request $request)
    {
        if ($request->user()->role !== 'Satpam') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $totalPending = LaporanPelanggaran::where('status', 'Pending')->count();
        $totalValid = LaporanPelanggaran::where('status', 'Valid')->count();
        $totalDitolak = LaporanPelanggaran::where('status', 'Ditolak')->count();

        return response()->json([
            'pending' => $totalPending,
            'valid' => $totalValid,
            'ditolak' => $totalDitolak,
        ], 200);
    }

    /**
     * Hapus laporan oleh mahasiswa (hanya jika status masih Pending).
     */
    public function deleteLaporan(Request $request, $id)
    {
        $laporan = LaporanPelanggaran::find($id);
        
        if (!$laporan) {
            return response()->json(['message' => 'Laporan tidak ditemukan'], 404);
        }

        // Cek apakah yang hapus adalah pelapor
        if ($laporan->pelapor_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Hanya bisa hapus jika status masih Pending
        if ($laporan->status !== 'Pending') {
            return response()->json(['message' => 'Laporan sudah diproses, tidak bisa dihapus'], 400);
        }

        // Set cancelled_at timestamp instead of hard delete
        $laporan->cancelled_at = Carbon::now();
        $laporan->status = 'Dibatalkan';
        $laporan->save();

        return response()->json([
            'message' => 'Laporan berhasil dibatalkan'
        ], 200);
    }

    /**
     * Update laporan oleh mahasiswa (hanya jika status Pending)
     */
    public function updateLaporan(Request $request, $id)
    {
        $laporan = LaporanPelanggaran::find($id);
        
        if (!$laporan) {
            return response()->json(['message' => 'Laporan tidak ditemukan'], 404);
        }

        if ($laporan->pelapor_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($laporan->status !== 'Pending') {
            return response()->json(['message' => 'Laporan sudah diproses, tidak bisa diedit'], 400);
        }

        $validatedData = $request->validate([
            'plat_nomor_terlapor' => 'sometimes|required|string|max:10',
            'deskripsi' => 'sometimes|required|string|min:10',
            'fotos' => 'sometimes|array|max:5',
            'fotos.*' => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if (isset($validatedData['plat_nomor_terlapor'])) {
            $kendaraan = Kendaraan::where('plat_nomor', $validatedData['plat_nomor_terlapor'])->first();
            if (!$kendaraan) {
                return response()->json(['message' => 'Plat nomor tidak terdaftar'], 404);
            }
            $laporan->plat_nomor_terlapor = $validatedData['plat_nomor_terlapor'];
        }

        if (isset($validatedData['deskripsi'])) {
            $laporan->deskripsi = $validatedData['deskripsi'];
        }

        if ($request->hasFile('fotos')) {
            $existingPhotos = $laporan->foto_pelanggaran ?? [];
            foreach ($request->file('fotos') as $foto) {
                $path = $foto->store('laporan', 'public');
                $existingPhotos[] = $path;
            }
            $laporan->foto_pelanggaran = $existingPhotos;
            if (!empty($existingPhotos)) {
                $laporan->url_foto_bukti = $existingPhotos[0];
            }
        }

        $laporan->save();
        return response()->json(['message' => 'Laporan berhasil diupdate', 'data' => $laporan], 200);
    }

    public function unvalidasiLaporan(Request $request, $id)
    {
        if ($request->user()->role !== 'Satpam') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $laporan = LaporanPelanggaran::find($id);
        if (!$laporan || $laporan->status !== 'Valid') {
            return response()->json(['message' => 'Tidak dapat di-unvalidasi'], 400);
        }

        $laporan->status = 'Pending';
        $laporan->unvalidated_at = Carbon::now();
        $laporan->unvalidated_by = $request->user()->id;
        $laporan->save();

        return response()->json(['message' => 'Laporan berhasil di-unvalidasi'], 200);
    }
}
