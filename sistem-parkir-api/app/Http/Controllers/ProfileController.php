<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\Mahasiswa;

class ProfileController extends Controller
{
    /**
     * Get current user profile with detailed info
     */
    public function show(Request $request)
    {
        $user = $request->user();
        
        $profile = [
            'id' => $user->id,
            'username' => $user->username,
            'nama' => $user->nama ?? $user->username, // Fallback ke username jika nama kosong
            'photo' => $user->photo,
            'telepon' => $user->telepon,
            'role' => $user->role,
        ];

        // If mahasiswa, get additional info
        if ($user->role === 'Mahasiswa') {
            $mahasiswa = Mahasiswa::with(['prodi.jurusan'])
                ->where('user_id', $user->id)
                ->first();
            
            if ($mahasiswa) {
                $profile['npm'] = $mahasiswa->npm;
                $profile['angkatan'] = $mahasiswa->angkatan;
                $profile['prodi'] = $mahasiswa->prodi ? $mahasiswa->prodi->nama_prodi : null;
                $profile['jurusan'] = $mahasiswa->prodi && $mahasiswa->prodi->jurusan 
                    ? $mahasiswa->prodi->jurusan->nama_jurusan 
                    : null;
            }
        }

        return response()->json($profile, 200);
    }

    /**
     * Update user profile
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $rules = [
            'nama' => 'sometimes|string|max:255',
            'telepon' => 'sometimes|string|max:20',
            'current_password' => 'required_with:password|string',
            'password' => 'sometimes|string|min:6|confirmed',
            'photo' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
        ];

        $validatedData = $request->validate($rules);

        // Verify current password if changing password
        if ($request->filled('password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'message' => 'Password lama tidak sesuai',
                    'errors' => ['current_password' => ['Password lama tidak sesuai']]
                ], 422);
            }
        }

        // Handle photo upload
        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($user->photo) {
                Storage::disk('public')->delete($user->photo);
            }
            
            $path = $request->file('photo')->store('profiles', 'public');
            $validatedData['photo'] = $path;
        }

        // Handle password update
        if (isset($validatedData['password'])) {
            $validatedData['password'] = Hash::make($validatedData['password']);
        }

        $user->update($validatedData);

        return response()->json([
            'message' => 'Profile berhasil diperbarui',
            'user' => $user
        ], 200);
    }
}
