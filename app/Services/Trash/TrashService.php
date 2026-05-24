<?php

namespace App\Services\Trash;

use App\Models\Category;
use App\Models\PaymentTransaction;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleEmail;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Shift;
use App\Models\StockAdjustment;
use App\Models\StockLog;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator as PaginationLengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class TrashService
{
    /**
     * Models that appear in the trash bin.
     *
     * @var array<int, array{class: class-string<Model&SoftDeletes>, label: string}>
     */
    protected array $trackedModels = [
        ['class' => Category::class, 'label' => 'Kategori'],
        ['class' => PaymentTransaction::class, 'label' => 'Transaksi Pembayaran'],
        ['class' => PriceHistory::class, 'label' => 'Riwayat Harga'],
        ['class' => Product::class, 'label' => 'Produk'],
        ['class' => Promotion::class, 'label' => 'Promosi'],
        ['class' => Purchase::class, 'label' => 'Pembelian'],
        ['class' => PurchaseItem::class, 'label' => 'Item Pembelian'],
        ['class' => Sale::class, 'label' => 'Penjualan'],
        ['class' => SaleEmail::class, 'label' => 'Email Penjualan'],
        ['class' => SaleItem::class, 'label' => 'Item Penjualan'],
        ['class' => SaleReturn::class, 'label' => 'Retur Penjualan'],
        ['class' => SaleReturnItem::class, 'label' => 'Item Retur'],
        ['class' => Shift::class, 'label' => 'Shift'],
        ['class' => StockAdjustment::class, 'label' => 'Penyesuaian Stok'],
        ['class' => StockLog::class, 'label' => 'Log Stok'],
        ['class' => StockTransfer::class, 'label' => 'Transfer Stok'],
        ['class' => Supplier::class, 'label' => 'Supplier'],
        ['class' => Unit::class, 'label' => 'Satuan'],
        ['class' => User::class, 'label' => 'User'],
        ['class' => Warehouse::class, 'label' => 'Gudang'],
    ];

    /**
     * Get all trashed items across tracked models, paginated.
     */
    public function getPaginatedTrashedItems(int $perPage = 15): LengthAwarePaginator
    {
        $allItems = collect();

        foreach ($this->trackedModels as $entry) {
            /** @var class-string<Model&SoftDeletes> $class */
            $class = $entry['class'];

            $trashed = $class::onlyTrashed()->latest('deleted_at')->get()->map(
                fn (Model $model) => $this->toUnifiedItem($model, $class, $entry['label'])
            );

            $allItems = $allItems->concat($trashed);
        }

        return $this->paginate($allItems->sortByDesc('deleted_at')->values(), $perPage);
    }

    /**
     * Search trashed items across all tracked models.
     */
    public function searchTrashedItems(string $search, int $perPage = 15): LengthAwarePaginator
    {
        $allItems = collect();

        foreach ($this->trackedModels as $entry) {
            /** @var class-string<Model&SoftDeletes> $class */
            $class = $entry['class'];

            $query = $class::onlyTrashed();

            if ($class === User::class) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            } elseif ($class === Sale::class) {
                $query->where(function ($q) use ($search) {
                    $q->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%");
                });
            } elseif ($class === Purchase::class || $class === PurchaseItem::class) {
                $query->where('id', 'like', "%{$search}%");
            } elseif ($class === SaleEmail::class) {
                $query->where('email', 'like', "%{$search}%");
            } elseif ($class === SaleReturn::class) {
                $query->where(function ($q) use ($search) {
                    $q->where('reason', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%");
                });
            } elseif ($class === StockAdjustment::class) {
                $query->where(function ($q) use ($search) {
                    $q->where('reason', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%");
                });
            } elseif ($class === PaymentTransaction::class) {
                $query->where(function ($q) use ($search) {
                    $q->where('external_id', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%");
                });
            } elseif (in_array($class, [SaleItem::class, SaleReturnItem::class, StockLog::class, StockTransfer::class, Shift::class, PriceHistory::class], true)) {
                $query->where('id', 'like', "%{$search}%");
            } else {
                $query->where('name', 'like', "%{$search}%");
            }

            $trashed = $query->latest('deleted_at')->get()->map(
                fn (Model $model) => $this->toUnifiedItem($model, $class, $entry['label'])
            );

            $allItems = $allItems->concat($trashed);
        }

        return $this->paginate($allItems->sortByDesc('deleted_at')->values(), $perPage);
    }

    /**
     * Restore a trashed model by its class and ID.
     *
     * @param  class-string<Model&SoftDeletes>  $class
     */
    public function restore(string $class, int $id): Model
    {
        /** @var Model&SoftDeletes $model */
        $model = $class::onlyTrashed()->findOrFail($id);
        $model->restore();

        return $model;
    }

    /**
     * Permanently delete a trashed model by its class and ID.
     *
     * @param  class-string<Model&SoftDeletes>  $class
     */
    public function forceDelete(string $class, int $id): void
    {
        /** @var Model&SoftDeletes $model */
        $model = $class::onlyTrashed()->findOrFail($id);
        $model->forceDelete();
    }

    /**
     * Mapping of foreign key suffixes to their related model class and display column.
     *
     * @var array<string, array{class: class-string<Model>, column: string}>
     */
    protected array $fkResolvers = [
        'category_id' => ['class' => Category::class, 'column' => 'name'],
        'unit_id' => ['class' => Unit::class, 'column' => 'name'],
        'supplier_id' => ['class' => Supplier::class, 'column' => 'name'],
        'user_id' => ['class' => User::class, 'column' => 'name'],
        'product_id' => ['class' => Product::class, 'column' => 'name'],
        'shift_id' => ['class' => Shift::class, 'column' => 'id'],
        'warehouse_id' => ['class' => Warehouse::class, 'column' => 'name'],
        'from_warehouse_id' => ['class' => Warehouse::class, 'column' => 'name'],
        'to_warehouse_id' => ['class' => Warehouse::class, 'column' => 'name'],
    ];

    /**
     * Columns that should be excluded from the attributes display.
     */
    protected array $attributeExclude = [
        'password',
        'remember_token',
        'email_verified_at',
        'image_path',
        'raw_response',
    ];

    /**
     * Get a single trashed item by its class and ID, with full attributes for display.
     *
     * @param  class-string<Model&SoftDeletes>  $class
     * @return array<string, mixed>
     */
    public function getTrashedItem(string $class, int $id): array
    {
        /** @var Model&SoftDeletes $model */
        $model = $class::onlyTrashed()->findOrFail($id);

        $label = collect($this->trackedModels)->firstWhere('class', $class)['label'] ?? class_basename($class);

        $item = $this->toUnifiedItem($model, $class, $label);

        $item['attributes'] = collect($model->getAttributes())
            ->except(['id', 'deleted_at', 'created_at', 'updated_at'])
            ->reject(fn ($value, $key) => in_array($key, $this->attributeExclude, true))
            ->map(fn ($value, $key) => [
                'key' => str_replace('_', ' ', ucfirst($key)),
                'value' => $this->resolveAttributeValue($key, $value),
            ])
            ->values()
            ->toArray();

        return $item;
    }

    /**
     * Resolve a model attribute value. Translates foreign key IDs to their
     * related model's display name (e.g. supplier_id → "PT Makmur").
     */
    protected function resolveAttributeValue(string $key, mixed $value): mixed
    {
        if (! array_key_exists($key, $this->fkResolvers)) {
            return $value;
        }

        if (blank($value)) {
            return null;
        }

        $resolver = $this->fkResolvers[$key];
        /** @var class-string<Model> $relatedClass */
        $relatedClass = $resolver['class'];
        $column = $resolver['column'];

        $related = $relatedClass::query()->find($value) ?? $relatedClass::withTrashed()->find($value);

        return $related ? $related->{$column} : $value;
    }

    /**
     * Get the total count of trashed items across all tracked models.
     */
    public function getTotalTrashedCount(): int
    {
        $total = 0;

        foreach ($this->trackedModels as $entry) {
            /** @var class-string<Model&SoftDeletes> $class */
            $class = $entry['class'];
            $total += $class::onlyTrashed()->count();
        }

        return $total;
    }

    /**
     * Map a model to a unified trash item array.
     *
     * @param  class-string<Model&SoftDeletes>  $class
     * @return array<string, mixed>
     */
    protected function toUnifiedItem(Model $model, string $class, string $label): array
    {
        return [
            'id' => $model->getKey(),
            'class' => $class,
            'type' => class_basename($class),
            'type_label' => $label,
            'name' => $this->resolveName($model, $class),
            'deleted_at' => $model->deleted_at->toDateTimeString(),
        ];
    }

    /**
     * Resolve a human-readable name for the model.
     *
     * @param  class-string<Model&SoftDeletes>  $class
     */
    protected function resolveName(Model $model, string $class): string
    {
        return match ($class) {
            Sale::class => $model->customer_name ?: 'Penjualan #'.$model->getKey(),
            Purchase::class => 'Pembelian #'.$model->getKey(),
            PurchaseItem::class => 'Item Pembelian #'.$model->getKey(),
            SaleItem::class => 'Item Penjualan #'.$model->getKey(),
            SaleReturn::class => 'Retur Penjualan #'.$model->getKey(),
            SaleReturnItem::class => 'Item Retur #'.$model->getKey(),
            StockLog::class => 'Log Stok #'.$model->getKey(),
            StockAdjustment::class => 'Penyesuaian Stok #'.$model->getKey(),
            StockTransfer::class => 'Transfer Stok #'.$model->getKey(),
            Shift::class => 'Shift #'.$model->getKey().' ('.$model->status.')',
            PaymentTransaction::class => 'Pembayaran #'.$model->getKey(),
            PriceHistory::class => 'Riwayat Harga #'.$model->getKey(),
            SaleEmail::class => $model->email ?: 'Email Penjualan #'.$model->getKey(),
            User::class => $model->name.' ('.$model->email.')',
            default => $model->name ?? (string) $model->getKey(),
        };
    }

    /**
     * Paginate a collection manually.
     *
     * @return LengthAwarePaginator<array<string, mixed>>
     */
    protected function paginate(Collection $items, int $perPage): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage();
        $total = $items->count();

        return new PaginationLengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );
    }
}
