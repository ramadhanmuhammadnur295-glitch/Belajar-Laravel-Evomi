<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse; // Lebih aman untuk testing
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // Laravel Controller
    public function index(): JsonResponse
    {
        try {
            $stats = [
                'total_products' => DB::table('products')->count(),
                'total_orders' => DB::table('orders')->count(),
                'total_revenue' => DB::table('orders')->sum('total_harga') ?? 0,
                // Hapus ->take(5) agar pagination di frontend berfungsi untuk semua data
                'recent_orders' => DB::table('orders')->latest()->get(),
            ];

            return response()->json($stats, 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
