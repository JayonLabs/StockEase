<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class StockAlertController extends Controller
{
    /**
     * Get all product stock alerts.
     */
    public function index(): JsonResponse
    {
        $alertProducts = Product::whereColumn('stock', '<=', 'alert_stock')
            ->select(['id', 'slug', 'name', 'sku', 'stock', 'alert_stock', 'unit_id'])
            ->get();

        return response()->json($alertProducts);
    }
}
