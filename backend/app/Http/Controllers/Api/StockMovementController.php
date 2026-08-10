<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    // GET /api/products/{product}/stock-movements
    public function index(Product $product)
    {
        return response()->json(
            $product->stockMovements()->latest()->paginate(20)
        );
    }

    // POST /api/products/{product}/stock-movements
    // body: { type: in|out|adjustment, quantity: 数量(正数), reason, reference_no }
    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'type' => ['required', 'in:in,out,adjustment'],
            'quantity' => ['required', 'integer', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:255'],
        ]);

        // 统一约定:前端传入的 quantity 始终是正数,
        // 这里根据 type 决定实际增减方向
        $signedQuantity = match ($data['type']) {
            'in' => abs($data['quantity']),
            'out' => -abs($data['quantity']),
            'adjustment' => $data['quantity'], // 调整允许正负,直接传有符号的数
        };

        if ($data['type'] === 'out' && $product->quantity + $signedQuantity < 0) {
            return response()->json([
                'message' => '库存不足,无法出库',
                'current_quantity' => $product->quantity,
            ], 422);
        }

        $movement = StockMovement::create([
            'product_id' => $product->id,
            'user_id' => $request->user()?->id,
            'type' => $data['type'],
            'quantity' => $signedQuantity,
            'reason' => $data['reason'] ?? null,
            'reference_no' => $data['reference_no'] ?? null,
        ]);

        return response()->json([
            'movement' => $movement,
            'product' => $product->fresh(),
        ], 201);
    }
}
