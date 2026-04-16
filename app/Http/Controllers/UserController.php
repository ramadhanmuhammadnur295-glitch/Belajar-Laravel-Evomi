<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage; // Pastikan ini di-import di atas
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * READ: Menampilkan semua data user
     */
    public function index()
    {
        // Mengambil semua user (bisa diganti dengan paginate(10) jika data sudah banyak)
        $users = User::all();

        return response()->json([
            'success' => true,
            'message' => 'Data semua user berhasil diambil',
            'data' => $users
        ], 200);
    }

    /**
     * READ: Menampilkan detail satu user berdasarkan ID
     */
    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ], 200);
    }

    /**
     * Store a newly created user (Register / Admin Create).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'username' => 'required|string|unique:users,username',
            'email'    => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:8',
            'image'    => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // Max 2MB
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $imagePath = 'default-avatar.png'; // Default jika tidak ada upload

        // Logika Upload Image
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            // Simpan ke storage/app/public/profiles
            $image->storeAs('public/profiles', $imageName);
            $imagePath = $imageName;
        }

        $user = User::create([
            'name'     => $request->name,
            'username' => $request->username,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'image'    => $imagePath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data'    => $user
        ], 201);
    }

    /**
     * Update the specified user (Profile/Identity Update).
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // 1. Validasi
        $request->validate([
            'name'     => 'required|string|max:255',
            'username' => 'required|string|unique:users,username,' . $id,
            'image'    => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // max 2MB
        ]);

        // 2. Handle Upload Gambar
        if ($request->hasFile('image')) {
            // Hapus foto lama jika ada (opsional tapi disarankan agar tidak menumpuk)
            if ($user->image) {
                Storage::disk('public')->delete('profiles/' . $user->image);
            }

            // Ambil file
            $file = $request->file('image');

            // Buat nama unik: misal 1_1681543200.jpg
            $fileName = $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();

            // SIMPAN KE: storage/app/public/profiles
            $file->storeAs('profiles', $fileName, 'public');

            // Simpan nama file ke database
            $user->image = $fileName;
        }

        $user->name = $request->name;
        $user->username = $request->username;

        // Update password jika ada...
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json([
            'message' => 'Identity successfully refined.',
            'data' => $user
        ]);
    }

    /**
     * DELETE: Menghapus user
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dihapus'
        ], 200);
    }
}
