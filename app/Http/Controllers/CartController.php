<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            // Mengambil data dari tabel carts dan join ke products
            $cartItems = \DB::table('carts')
                ->join('products', 'carts.product_id', '=', 'products.id')
                ->where('carts.user_id', $user->id)
                ->select(
                    'carts.product_id',
                    'products.nama',   // Pastikan di tabel products kolomnya 'nama'
                    'products.harga_retail',  // Pastikan di tabel products kolomnya 'harga'
                    'products.image_url',  // Pastikan di tabel products kolomnya 'image_url'
                    'carts.jumlah'     // Pastikan di tabel carts kolomnya 'jumlah'
                )
                ->get();

            // Transformasi data agar sesuai dengan variabel di Next.js (ShoppingBag.tsx)
            $formattedCart = $cartItems->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'name' => $item->nama,
                    'price' => (int) $item->harga_retail,
                    'quantity' => (int) $item->jumlah,
                    'image_url' => $item->image_url,
                    'image' => $item->product_id, // Kita asumsikan nama file gambar = ID Produk
                ];
            });

            return response()->json([
                'status' => 'success',
                'cart' => $formattedCart,
            ]);
        } catch (\Exception $e) {
            // Jika error, Laravel akan mengirimkan pesan apa yang salah ke Next.js
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menghapus 1 produk spesifik dari keranjang user
     */
    public function destroy($productId)
    {
        $userId = auth()->id();

        // Cari data keranjang berdasarkan user yang login dan ID produk yang diklik
        $deleted = Cart::where('user_id', $userId)
            ->where('product_id', $productId)
            ->delete();

        if ($deleted) {
            return response()->json([
                'status' => 'success',
                'message' => 'Produk berhasil dihapus dari keranjang',
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Produk tidak ditemukan di keranjang',
        ], 404);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // Pastikan kita bekerja dengan array, bukan query builder yang salah syntax
        $cart = $user->cart ?? [];
        $productId = $request->product_id;

        $found = false;

        // Kita looping array PHP-nya, bukan pakai SQL mentah untuk tambah jumlah
        foreach ($cart as &$item) {
            if ($item['product_id'] == $productId) {
                $item['quantity'] = (int) $item['quantity'] + 1; // Tambah di level PHP
                $found = true;
                break;
            }
        }

        if (! $found) {
            $cart[] = [
                'product_id' => $productId,
                'name' => $request->name,
                'price' => $request->price,
                'image' => $request->image,
                'quantity' => 1,
            ];
        }

        // Laravel akan mengubah array ini menjadi JSON secara otomatis karena 'casts' di model User
        $user->update(['cart' => $cart]);

        return response()->json([
            'message' => 'Cart updated successfully',
            'cart' => $cart,
        ]);
    }

    //
    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required',
            'jumlah' => 'required|integer|min:1',
        ]);

        // CEK APAKAH PRODUK ADA DI TABEL PRODUCTS
        $productExists = \DB::table('products')->where('id', $request->product_id)->exists();

        if (! $productExists) {
            return response()->json([
                'message' => "Produk dengan ID {$request->product_id} tidak ditemukan di database server.",
            ], 404);
        }

        // Jika ada, baru jalankan logic simpan ke Cart
        $userId = auth()->id();

        $userId = auth()->id();
        $productId = $request->product_id;
        $jumlahBaru = $request->jumlah;

        // 2. Cari apakah produk sudah ada di keranjang user tersebut
        $cart = Cart::where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();

        if ($cart) {
            // Jika ada, update jumlahnya menggunakan increment (lebih aman dari race condition)
            $cart->increment('jumlah', $jumlahBaru);
        } else {
            // Jika tidak ada, buat record baru
            $cart = Cart::create([
                'user_id' => $userId,
                'product_id' => $productId,
                'jumlah' => $jumlahBaru,
            ]);
        }

        return response()->json([
            'message' => 'Produk berhasil ditambah ke keranjang',
            'data' => $cart->fresh(), // .fresh() untuk mengambil data terbaru setelah increment
        ]);
    }
}
