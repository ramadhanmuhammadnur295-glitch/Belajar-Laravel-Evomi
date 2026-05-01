<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    // Fungsi Register untuk User Baru

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            // Tambahkan :users pada rule unique
            'username' => 'required|string|max:255|unique:users', 
            // Tambahkan :users pada rule unique
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'user' => $user, // Pastikan $user sudah mengandung kolom 'username'
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string', // 'login' bisa berisi email atau username
            'password' => 'required',
        ]);

        // Logika: Cek apakah input adalah email, jika bukan maka anggap sebagai username
        $fieldType = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($fieldType, $request->login)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Kredensial salah'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'user' => $user,
        ]);
    }

    // Fungsi Logout
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Berhasil logout',
        ]);
    }
}
