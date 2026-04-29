<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Menampilkan semua daftar pesanan milik user yang login
     */
    public function index()
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not authenticated',
            ], 401);
        }

        $userId = Auth::id();

        // Mengambil pesanan terbaru berdasarkan user_id
        $orders = Order::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $orders,
        ]);
    }

    /**
     * Menampilkan detail pesanan untuk Admin (Bisa melihat milik siapa saja)
     */
    public function adminShow($id)
    {
        // Eager loading 'details.product' dan 'user' untuk melihat siapa yang membeli
        $order = Order::with(['details.product', 'user'])
            ->find($id);

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pesanan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $order,
        ]);
    }

    /**
     * Menampilkan detail pesanan spesifik beserta item di dalamnya
     */
    public function show($id)
    {
        try {
            // Gunakan find() langsung pada ID
            // Pastikan relasi 'user' ada di model Order
            // Pastikan relasi 'details' ada di model Order dan 'product' ada di model OrderDetail
            $order = Order::with(['user', 'details.product'])->find($id);

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order dengan ID ' . $id . ' tidak ditemukan di database.',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $order,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan server: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function checkout(Request $request)
    {
        // 1. Validasi
        $request->validate([
            'alamat_pengiriman' => 'required|string',
            'total_harga' => 'required|numeric',
            'ongkos_kirim' => 'required|numeric',
        ]);

        // Mulai Transaksi Database
        DB::beginTransaction();

        try {
            // 1. Simpan Header Order
            $order = Order::create([
                'user_id' => auth()->id(),
                'total_harga' => $request->total_harga,
                'ongkos_kirim' => $request->ongkos_kirim,
                'alamat_pengiriman' => $request->alamat_pengiriman,
                'catatan_pengiriman' => $request->catatan_pengiriman,
                'kurir' => $request->kurir,
                'status' => 'pending', // Tambahkan status default
            ]);

            // 2. Ambil Item Keranjang
            $cartItems = Cart::where('user_id', auth()->id())->get();

            if ($cartItems->isEmpty()) {
                throw new \Exception('Keranjang belanja kosong.');
            }

            foreach ($cartItems as $item) {
                // --- LOGIKA PENGURANGAN DAN STATUS STOK ---
                // Gunakan lockForUpdate() agar aman dari bentrok transaksi ganda
                $product = Product::where('id', $item->product_id)->lockForUpdate()->first();

                // Cek apakah produk ada dan stok cukup
                if (!$product || $product->stok_tersedia < $item->jumlah) {
                    // Catatan: Pastikan nama field-nya 'nama_produk' atau 'nama' sesuai database kamu
                    $namaProduk = $product->nama_produk ?? 'Tidak diketahui';
                    throw new \Exception("Stok produk '{$namaProduk}' tidak mencukupi.");
                }

                // Hitung sisa stok
                $sisa_stok = $product->stok_tersedia - $item->jumlah;

                // Terapkan sisa stok ke model
                $product->stok_tersedia = $sisa_stok;

                // Tentukan status_stok berdasarkan sisa stok
                if ($sisa_stok == 0) {
                    $product->status_stok = 'Out of Stock';
                } elseif ($sisa_stok <= 19) {
                    $product->status_stok = 'Low Stock';
                } else {
                    // Kondisi ini mencakup >= 20
                    $product->status_stok = 'Available Stock';
                }

                // Simpan perubahan stok dan status_stok ke database
                $product->save();

                // Simpan Detail Order
                OrderDetail::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'jumlah' => $item->jumlah,
                    'harga_saat_beli' => $product->harga_retail, // Pastikan field ini benar
                ]);
            }

            // 3. Kosongkan Keranjang setelah sukses
            Cart::where('user_id', auth()->id())->delete();

            // Jika semua lancar, simpan permanen
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat dan stok telah diperbarui',
                'order_id' => $order->id,
            ], 201);
        } catch (\Exception $e) {
            // Jika ada error (stok kurang/db error), batalkan semua perubahan
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update data pesanan (Alamat, Kurir, atau Status)
     */
    public function update(Request $request, $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan',
            ], 404);
        }

        // Validasi input berdasarkan struktur SQL orders.sql
        $validator = Validator::make($request->all(), [
            'total_harga' => 'numeric',
            'ongkos_kirim' => 'numeric',
            'status_pembayaran' => 'in:pending,success,failed,expired', // Sesuai ENUM di database
            'alamat_pengiriman' => 'string|max:255',
            'kurir' => 'string|max:50',
            'catatan_pengiriman' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update data
        $order->update($request->only([
            'total_harga',
            'ongkos_kirim',
            'status_pembayaran',
            'alamat_pengiriman',
            'kurir',
            'catatan_pengiriman',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil diperbarui',
            'data' => $order,
        ]);
    }

    /**
     * Hapus pesanan
     */
    public function destroy($id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan',
            ], 404);
        }

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil dihapus',
        ]);
    }
}
