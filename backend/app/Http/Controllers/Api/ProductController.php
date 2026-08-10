<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // GET /api/products?search=xxx&low_stock=1
    public function index(Request $request)
    {
        $query = Product::with(['category', 'supplier']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('low_stock')) {
            $query->whereColumn('quantity', '<=', 'low_stock_threshold');
        }

        return response()->json(
            $query->orderBy('name')->paginate(20)
        );
    }

    // POST /api/products
    public function store(Request $request)
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:0'], // 初始库存,建议通过 stock-movements 接口入库
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'barcode' => ['nullable', 'string'],
        ]);

        $product = Product::create($data);

        return response()->json($product, 201);
    }

    // GET /api/products/{product}
    public function show(Product $product)
    {
        return response()->json($product->load(['category', 'supplier', 'stockMovements']));
    }

    // PUT /api/products/{product}
    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'sku' => ['sometimes', 'string', 'unique:products,sku,' . $product->id],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'cost_price' => ['sometimes', 'numeric', 'min:0'],
            'sell_price' => ['sometimes', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'barcode' => ['nullable', 'string'],
            // 注意:quantity 不允许在这里直接改,必须通过 stock-movements 走流水
        ]);

        $product->update($data);

        return response()->json($product);
    }

    // DELETE /api/products/{product}
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(['message' => '商品已删除'], 204);
    }
}
