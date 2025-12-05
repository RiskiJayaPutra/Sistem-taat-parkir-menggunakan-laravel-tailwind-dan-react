<?php

namespace App\Http\Controllers;

use App\Models\StatusParkir;
use App\Models\User;
use App\Models\Mahasiswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AdminController extends Controller
{
    public function getBannedUsers(Request $request)
    {
        if ($request->user()->role !== 'Admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $bannedUsers = StatusParkir::where('is_banned_permanently', true)
            ->orWhere('ban_expires_at', '>', Carbon::now())
            ->with('mahasiswa:id,nama,npm')
            ->get();

        return response()->json($bannedUsers, 200);
    }

    public function unbanUser(Request $request, $mahasiswa_id)
    {
        if ($request->user()->role !== 'Admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $statusParkir = StatusParkir::find($mahasiswa_id);

        if (!$statusParkir) {
            return response()->json(['message' => 'Data mahasiswa tidak ditemukan'], 404);
        }

        $statusParkir->is_banned_permanently = false;
        $statusParkir->ban_expires_at = null;
        $statusParkir->save();

        return response()->json([
            'message' => 'Status ban untuk mahasiswa telah berhasil dilepas.'
        ], 200);
    }

    public function createSatpam(Request $request)
    {
        if ($request->user()->role !== 'Admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validatedData = $request->validate([
            'username' => 'required|string|max:255|unique:users,username',
            'nama' => 'required|string|max:255',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'username' => $validatedData['username'],
            'nama' => $validatedData['nama'],
            'password' => Hash::make($validatedData['password']),
            'role' => 'Satpam',
        ]);

        return response()->json([
            'message' => 'Akun satpam berhasil dibuat',
            'user' => $user
        ], 201);
    }

    public function getSatpam(Request $request)
    {
        if ($request->user()->role !== 'Admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $satpam = User::where('role', 'Satpam')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($satpam, 200);
    }

    public function getSatpamById(Request $request, $id)
    {
        if ($request->user()->role !== 'Admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $satpam = User::where('id', $id)->where('role', 'Satpam')->first();

        if (!$satpam) {
            return response()->json(['message' => 'Satpam tidak ditemukan'], 404);
        }

        return response()->json($satpam, 200);
    }

    public function updateSatpam(Request $request, $id)
    {
        if ($request->user()->role !== 'Admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = User::where('id', $id)->where('role', 'Satpam')->first();

        if (!$user) {
            return response()->json(['message' => 'Satpam tidak ditemukan'], 404);
        }

        $validatedData = $request->validate([
            'username' => 'required|string|max:255|unique:users,username,' . $id,
            'nama' => 'required|string|max:255',
            'password' => 'nullable|string|min:6',
        ]);

        $user->username = $validatedData['username'];
        $user->nama = $validatedData['nama'];
        
        if ($request->filled('password')) {
            $user->password = Hash::make($validatedData['password']);
        }
        
        $user->save();

        return response()->json([
            'message' => 'Akun satpam berhasil diperbarui',
            'user' => $user
        ], 200);
    }

    public function deleteSatpam(Request $request, $id)
    {
        if ($request->user()->role !== 'Admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = User::where('id', $id)->where('role', 'Satpam')->first();

        if (!$user) {
            return response()->json(['message' => 'Satpam tidak ditemukan'], 404);
        }

        $user->delete();

        return response()->json([
            'message' => 'Akun satpam berhasil dihapus'
        ], 200);
    }

    public function getDashboardStats(Request $request)
    {
        if ($request->user()->role !== 'Admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $totalMahasiswa = Mahasiswa::count();
        $totalSatpam = User::where('role', 'Satpam')->count();
        $totalBanned = StatusParkir::where('is_banned_permanently', true)
            ->orWhere('ban_expires_at', '>', Carbon::now())
            ->count();

        return response()->json([
            'total_mahasiswa' => $totalMahasiswa,
            'total_satpam' => $totalSatpam,
            'total_banned' => $totalBanned,
        ], 200);
    }
}