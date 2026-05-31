<?php

namespace App\Services\Product;

use App\Models\Product;
use Cviebrock\EloquentSluggable\Services\SlugService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductService
{
    /**
     * Get paginated products with searching and category.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPaginatedProducts(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return Product::with(['category', 'unit'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('alert_stock', 'like', "%{$search}%")
                    ->orWhereHas('unit', function ($queryUnit) use ($search) {
                        $queryUnit->where('name', 'like', "%{$search}%");
                    })
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhereHas('category', function ($queryCategory) use ($search) {
                        $queryCategory->where('name', 'like', "%{$search}%");
                    });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Store a new product.
     *
     * @param  array<string, mixed>  $data
     */
    public function storeProduct(array $data, ?UploadedFile $image = null): Product
    {
        $data['slug'] = SlugService::createSlug(Product::class, 'slug', $data['name']);

        if ($image) {
            $data['image_path'] = $this->uploadImage($image);
        }

        return Product::create($data);
    }

    /**
     * Update product price and log history.
     *
     * @param  array<string, mixed>  $data
     */
    public function updatePrice(Product $product, array $data): bool
    {
        return DB::transaction(function () use ($product, $data) {
            $product->priceHistories()->create([
                'user_id' => Auth::id(),
                'old_purchase_price' => $product->purchase_price,
                'new_purchase_price' => $data['purchase_price'],
                'old_selling_price' => $product->selling_price,
                'new_selling_price' => $data['selling_price'],
                'reason' => $data['reason'],
            ]);

            return $product->update([
                'purchase_price' => $data['purchase_price'],
                'selling_price' => $data['selling_price'],
            ]);
        });
    }

    /**
     * Update an existing product.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProduct(Product $product, array $data, ?UploadedFile $image = null): bool
    {
        if ($data['name'] !== $product->name) {
            $data['slug'] = SlugService::createSlug(Product::class, 'slug', $data['name']);
        }

        if ($image) {
            $this->deleteImage($product->image_path);
            $data['image_path'] = $this->uploadImage($image);
        }

        return $product->update($data);
    }

    /**
     * Delete a product and its image.
     */
    public function deleteProduct(Product $product): bool
    {
        $this->deleteImage($product->image_path);

        return $product->delete();
    }

    /**
     * Upload product image.
     */
    private function uploadImage(UploadedFile $image): string
    {
        $imageName = Str::uuid().'.'.$image->getClientOriginalExtension();
        $path = Storage::disk('public')->putFileAs('product', $image, $imageName);

        return "storage/{$path}";
    }

    /**
     * Delete product image.
     */
    private function deleteImage(?string $imagePath): void
    {
        if ($imagePath) {
            $filePath = Str::chopStart($imagePath, 'storage/');
            Storage::disk('public')->delete($filePath);
        }
    }
}
