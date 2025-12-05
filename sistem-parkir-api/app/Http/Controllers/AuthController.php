<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Validasi input - pastikan hanya username dan password
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Cari user berdasarkan username
        $user = User::where('username', $validated['username'])->first();

        // Cek apakah user ada dan password cocok
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Username atau password salah.'],
            ]);
        }

        // Hapus token lama
        $user->tokens()->delete();
        
        // Buat token baru
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'user' => $user,
            'token' => $token,
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil'
        ], 200);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        
        if ($user->role === 'Mahasiswa') {
            $mahasiswa = \App\Models\Mahasiswa::where('user_id', $user->id)->first();
            if ($mahasiswa) {
                $user->nama = $mahasiswa->nama;
            }
        }
        
        return response()->json([
            'user' => $user,
        ], 200);
    }
}