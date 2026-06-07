<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\UpdateProductPriceRequest;
use App\Models\Product;
use App\Services\Product\ProductService;
use Inertia\Inertia;

class PriceController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ProductService $productService
    ) {}

    /**
     * Show the form for updating product price.
     */
    public function edit(Product $product)
    {
        return Inertia::render('Product/Price/Update', [
            'product' => $product,
            'history' => $product->priceHistories()
                ->with('user:id,name')
                ->latest()
                ->paginate(10),
        ]);
    }

    /**
     * Update product price.
     */
    public function update(UpdateProductPriceRequest $request, Product $product)
    {
        $this->productService->updatePrice($product, $request->validated());

        return redirect()->route('product.index')->with('success', 'Harga produk berhasil diubah');
    }
}
