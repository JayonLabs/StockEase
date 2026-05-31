<?php

namespace App\Services\Sale;

use App\Actions\Product\ReduceProductStock;
use App\Actions\Sale\RecalculateSaleTotal;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\SaleStatus;
use App\Models\Category;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PosService
{
    /**
     * Session key for the active warehouse ID.
     */
    private const SESSION_KEY = 'pos_active_warehouse_id';

    public function __construct(
        private readonly RecalculateSaleTotal $recalculateSaleTotal,
        private readonly ReduceProductStock $reduceProductStock,
    ) {}

    /**
     * Get paginated products with category filter and search.
     *
     * Loads warehouse-level stock when an active warehouse is set.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPaginatedProducts(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $warehouseId = $this->getActiveWarehouseId();

        $paginator = Product::query()
            ->when($filters['category'] ?? null, function ($query, $categoryFilter) {
                $query->whereHas('category', function ($queryCategory) use ($categoryFilter) {
                    $queryCategory->where('slug', $categoryFilter);
                });
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        if ($warehouseId) {
            $warehouseStocks = DB::table('warehouse_product')
                ->where('warehouse_id', $warehouseId)
                ->whereIn('product_id', $paginator->getCollection()->pluck('id'))
                ->pluck('stock', 'product_id');

            $paginator->getCollection()->transform(function ($product) use ($warehouseStocks) {
                $product->warehouse_stock = (int) ($warehouseStocks[$product->id] ?? 0);

                return $product;
            });
        }

        return $paginator;
    }

    /**
     * Get categories for POS filtering.
     */
    public function getCategories()
    {
        return Category::select('slug', 'name')->get()
            ->map(fn ($category) => [
                'value' => $category->slug,
                'label' => $category->name,
            ]);
    }

    /**
     * Get the active shift for the current user.
     *
     * Memoized via once() to avoid redundant DB queries during a single checkout flow.
     */
    private function getActiveShiftId(): ?int
    {
        return once(fn () => Shift::open()->where('user_id', Auth::id())
            ->latest()
            ->first()?->id);
    }

    /**
     * Get the active warehouse ID from session.
     */
    public function getActiveWarehouseId(): ?int
    {
        return session(self::SESSION_KEY);
    }

    /**
     * Get the active warehouse from session or throw an exception.
     *
     * @throws \Exception
     */
    public function requireWarehouseId(): int
    {
        $warehouseId = $this->getActiveWarehouseId();

        if (! $warehouseId) {
            throw new \Exception('Silakan pilih gudang terlebih dahulu.');
        }

        return $warehouseId;
    }

    /**
     * Set the active warehouse for the current POS session.
     */
    public function setActiveWarehouse(int $warehouseId): Warehouse
    {
        $warehouse = Warehouse::where('is_active', true)->findOrFail($warehouseId);
        session([self::SESSION_KEY => $warehouse->id]);

        return $warehouse;
    }

    /**
     * Get available warehouses for selection.
     */
    public function getWarehouses()
    {
        return Warehouse::where('is_active', true)
            ->select('id', 'name', 'slug')
            ->get();
    }

    /**
     * Get the current active cart (draft sale) for the authenticated user.
     *
     * Uses an atomic find-or-create within a transaction and a database-level
     * unique constraint on draft sales per user to prevent race conditions.
     */
    public function getOrCreateCart(): Sale
    {
        try {
            return DB::transaction(function () {
                $cart = Sale::with('saleItems.product')
                    ->where('user_id', Auth::id())
                    ->where('status', SaleStatus::Draft->value)
                    ->lockForUpdate()
                    ->first();

                if (! $cart) {
                    $warehouseId = $this->getActiveWarehouseId();

                    $cart = Sale::create([
                        'user_id' => Auth::id(),
                        'shift_id' => $this->getActiveShiftId(),
                        'warehouse_id' => $warehouseId,
                        'total' => 0,
                        'payment_method' => PaymentMethod::Pending->value,
                        'paid' => 0,
                        'change' => 0,
                        'date' => now(),
                        'status' => SaleStatus::Draft->value,
                    ]);
                    $cart->setRelation('saleItems', collect());
                }

                return $cart;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Another request won the race and created the draft sale.
            // Re-fetch the existing draft and return it.
            return Sale::with('saleItems.product')
                ->where('user_id', Auth::id())
                ->where('status', SaleStatus::Draft->value)
                ->firstOrFail();
        }
    }

    /**
     * Get available stock for a product (warehouse stock if warehouse set, else global stock).
     */
    private function getAvailableStock(Product $product): int
    {
        $warehouseId = $this->getActiveWarehouseId();

        if ($warehouseId) {
            return $product->stockInWarehouse($warehouseId);
        }

        return $product->stock;
    }

    /**
     * Verify that both shift is open and warehouse is selected before any mutation.
     *
     * @throws \Exception
     */
    private function ensureReady(): void
    {
        if (! $this->getActiveShiftId()) {
            throw new \Exception('Silakan buka shift terlebih dahulu.');
        }

        $this->requireWarehouseId();
    }

    /**
     * Add product to cart by barcode.
     */
    public function addToCartByBarcode(string $barcode, int $qty = 1): array
    {
        $this->ensureReady();

        $product = Product::where('barcode', $barcode)->first();

        if (! $product) {
            throw new \Exception('Produk dengan barcode tersebut tidak ditemukan');
        }

        return $this->addToCart($product->id, $qty);
    }

    /**
     * Add or update product in cart.
     */
    public function addToCart(int $productId, int $qty = 1): array
    {
        $this->ensureReady();

        $product = Product::findOrFail($productId);
        $cart = $this->getOrCreateCart();
        $warehouseId = $this->getActiveWarehouseId();

        $availableStock = $this->getAvailableStock($product);

        if ($availableStock <= 0) {
            throw new \Exception('Stok produk habis');
        }

        $existItem = $cart->saleItems->firstWhere('product_id', $product->id);
        $resultingQty = ($existItem?->qty ?? 0) + $qty;

        if ($availableStock < $resultingQty) {
            throw new \Exception('Stok produk tidak mencukupi');
        }

        if (! $existItem) {
            $newItem = SaleItem::create([
                'sale_id' => $cart->id,
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'qty' => $qty,
                'price' => $product->selling_price,
            ]);
            $newItem->setRelation('product', $product);
            $cart->saleItems->push($newItem);
        } else {
            $existItem->update([
                'qty' => $existItem->qty + $qty,
            ]);
        }

        $this->recalculateSaleTotal->execute($cart);

        return ['cart' => $cart, 'total' => $cart->total];
    }

    /**
     * Update item quantity in cart.
     */
    public function updateCartItemQty(int $productId, int $qty): array
    {
        $this->ensureReady();

        $cart = $this->getOrCreateCart();
        $product = Product::findOrFail($productId);
        $availableStock = $this->getAvailableStock($product);

        if ($qty > 0 && $qty > $availableStock) {
            throw new \Exception('Stok produk tidak mencukupi');
        }

        if ($qty <= 0) {
            $cart->saleItems()->where('product_id', $productId)->forceDelete();
            $cart->setRelation('saleItems', $cart->saleItems->reject(fn ($item) => $item->product_id === $productId)->values());
        } else {
            $saleItem = $cart->saleItems->firstWhere('product_id', $productId);
            if ($saleItem) {
                $saleItem->update(['qty' => $qty]);
            }
        }

        $this->recalculateSaleTotal->execute($cart);

        return ['cart' => $cart, 'total' => $cart->total];
    }

    /**
     * Remove item from cart.
     */
    public function removeFromCart(int $productId): array
    {
        $cart = $this->getOrCreateCart();
        $cart->saleItems()->where('product_id', $productId)->forceDelete();
        $cart->setRelation('saleItems', $cart->saleItems->reject(fn ($item) => $item->product_id === $productId)->values());

        $this->recalculateSaleTotal->execute($cart);

        return ['cart' => $cart, 'total' => $cart->total];
    }

    /**
     * Empty the cart.
     */
    public function emptyCart(): array
    {
        $cart = $this->getOrCreateCart();
        $cart->saleItems()->forceDelete();
        $cart->setRelation('saleItems', collect());

        $this->recalculateSaleTotal->execute($cart);

        return ['cart' => $cart, 'total' => $cart->total];
    }

    /**
     * Process checkout.
     */
    public function checkout(array $data): array
    {
        $this->ensureReady();
        $warehouseId = $this->getActiveWarehouseId();

        return DB::transaction(function () use ($data, $warehouseId) {
            $sale = Sale::with('saleItems.product')
                ->where('user_id', Auth::id())
                ->where('status', SaleStatus::Draft->value)
                ->firstOrFail();

            if ($sale->saleItems->isEmpty()) {
                throw new \Exception('Keranjang kosong, tidak bisa checkout');
            }

            foreach ($sale->saleItems as $item) {
                $availableStock = $item->product->stockInWarehouse($warehouseId);

                if ($availableStock < $item->qty) {
                    throw new \Exception("Stok produk {$item->product->name} tidak mencukupi untuk checkout.");
                }
            }

            if ($data['payment_method'] === PaymentMethod::Qris->value) {
                $sale->update([
                    'warehouse_id' => $warehouseId,
                    'payment_method' => PaymentMethod::Qris->value,
                    'shift_id' => $this->getActiveShiftId(),
                    'customer_name' => $data['customer_name'] ?? null,
                    'paid' => $sale->total,
                    'status' => SaleStatus::Pending->value,
                    'date' => now(),
                ]);

                $sale->saleItems()->update(['warehouse_id' => $warehouseId]);

                PaymentTransaction::create([
                    'sale_id' => $sale->id,
                    'gateway' => PaymentGateway::Midtrans->value,
                    'external_id' => $data['order_id'] ?? null,
                    'status' => 'pending',
                    'amount' => $sale->total,
                    'payment_type' => PaymentType::Qris->value,
                ]);
            } elseif ($data['payment_method'] === PaymentMethod::Cash->value) {
                if ($data['paid'] < $sale->total) {
                    throw new \Exception('Jumlah uang pembayaran kurang dari total belanja.');
                }

                $change = $data['paid'] - $sale->total;

                $sale->update([
                    'warehouse_id' => $warehouseId,
                    'payment_method' => PaymentMethod::Cash->value,
                    'shift_id' => $this->getActiveShiftId(),
                    'customer_name' => $data['customer_name'] ?? null,
                    'paid' => $data['paid'],
                    'change' => $change,
                    'status' => SaleStatus::Completed->value,
                    'date' => now(),
                ]);

                $sale->saleItems()->update(['warehouse_id' => $warehouseId]);

                $this->reduceProductStock->execute($sale->saleItems, $warehouseId);
            }

            // After checkout, we get/create a new empty cart for the next transaction
            $newCart = $this->getOrCreateCart();

            return ['cart' => $newCart, 'total' => $newCart->total, 'sale' => $sale];
        });
    }
}
