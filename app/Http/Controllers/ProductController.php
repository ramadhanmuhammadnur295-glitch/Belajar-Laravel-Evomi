<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Product::with(['notes', 'characters'])->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->all();

        if ($request->hasFile('image')) {
            $file = $request->file('image');

            // Membuat nama file: nama-produk-timestamp.ekstensi
            // Timestamp ditambahkan agar tidak bentrok jika ada nama produk yang sama
            $extension = $file->getClientOriginalExtension();
            $fileName = Str::slug($request->nama).'-'.time().'.'.$extension;

            // Simpan dengan nama file kustom menggunakan storeAs
            $path = $file->storeAs('products', $fileName, 'public');

            $data['image_url'] = asset('storage/'.$path);
        }

        $product = Product::create($data);

        return response()->json([
            'message' => 'Product created successfully',
            'data' => $product,
        ], 201);
    }

    public function show($id)
    {
        $product = Product::with(['notes', 'characters'])->find($id);

        return $product ? response()->json($product) : response()->json(['message' => 'Not Found'], 404);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->all();

        if ($request->hasFile('image')) {
            // Hapus gambar lama
            if ($product->image_url) {
                $oldPath = str_replace(asset('storage/'), '', $product->image_url);
                Storage::disk('public')->delete($oldPath);
            }

            $file = $request->file('image');

            // Gunakan nama produk baru (atau yang lama jika tidak diubah) untuk nama file
            $nameForFile = $request->nama ?: $product->nama;
            $extension = $file->getClientOriginalExtension();
            $fileName = Str::slug($nameForFile).'-'.time().'.'.$extension;

            $path = $file->storeAs('products', $fileName, 'public');

            $data['image_url'] = asset('storage/'.$path);
        }

        $product->update($data);

        return response()->json([
            'message' => 'Product updated successfully',
            'data' => $product,
        ]);
    }

    public function destroy($id)
    {
        Product::destroy($id);

        return response()->json(['message' => 'Deleted']);
    }
}
