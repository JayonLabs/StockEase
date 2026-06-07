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
use Illuminate\Support\Facades\DB;

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
     * Get all trashed items across tracked models, paginated at the database level.
     *
     * Uses UNION ALL subqueries so that LIMIT/OFFSET is applied before data reaches
     * PHP memory. Total count is derived from a single COUNT(*) wrapper.
     */
    public function getPaginatedTrashedItems(int $perPage = 15): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage();

        $unionSql = $this->buildUnionQuery();
        $total = (int) DB::selectOne("SELECT COUNT(*) as aggregate FROM ({$unionSql}) as trash_union")->aggregate;

        $offset = ($page - 1) * $perPage;
        $items = DB::select("SELECT * FROM ({$unionSql}) as trash_items ORDER BY deleted_at DESC LIMIT {$perPage} OFFSET {$offset}");

        $mapped = collect($items)->map(fn ($row) => $this->unifiedItemFromRow($row));

        return new PaginationLengthAwarePaginator($mapped, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
        ]);
    }

    /**
     * Search trashed items across all tracked models, paginated at the database level.
     */
    public function searchTrashedItems(string $search, int $perPage = 15): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage();

        $unionSql = $this->buildUnionQuery($search);
        $total = (int) DB::selectOne("SELECT COUNT(*) as aggregate FROM ({$unionSql}) as trash_union")->aggregate;

        $offset = ($page - 1) * $perPage;
        $items = DB::select("SELECT * FROM ({$unionSql}) as trash_items ORDER BY deleted_at DESC LIMIT {$perPage} OFFSET {$offset}");

        $mapped = collect($items)->map(fn ($row) => $this->unifiedItemFromRow($row));

        return new PaginationLengthAwarePaginator($mapped, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
        ]);
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
     * In-memory cache of resolved FK values. Keyed by "class:column:id".
     *
     * @var array<string, mixed>
     */
    protected array $resolvedCache = [];

    /**
     * Resolve a model attribute value. Translates foreign key IDs to their
     * related model's display name (e.g. supplier_id → "PT Makmur").
     *
     * Results are cached in-memory for the lifetime of the service instance
     * to avoid duplicate queries for the same FK reference.
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

        $cacheKey = "{$relatedClass}:{$column}:{$value}";

        if (array_key_exists($cacheKey, $this->resolvedCache)) {
            return $this->resolvedCache[$cacheKey];
        }

        $related = $relatedClass::query()->find($value) ?? $relatedClass::withTrashed()->find($value);

        return $this->resolvedCache[$cacheKey] = $related ? $related->{$column} : $value;
    }

    /**
     * Get the total count of trashed items across all tracked models.
     *
     * Uses a single COUNT(*) wrapper around the UNION ALL query instead of
     * iterating over each model and issuing a separate COUNT query per model.
     */
    public function getTotalTrashedCount(): int
    {
        $unionSql = $this->buildUnionQuery();

        return (int) DB::selectOne("SELECT COUNT(*) as aggregate FROM ({$unionSql}) as trash_union")->aggregate;
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
     * Build the UNION ALL query from all tracked models.
     *
     * Each subquery selects a consistent set of columns: id, model_class, type,
     * type_label, computed name, and deleted_at. When $search is provided, each
     * subquery also includes model-specific LIKE conditions.
     */
    protected function buildUnionQuery(?string $search = null): string
    {
        $subQueries = [];

        foreach ($this->trackedModels as $entry) {
            $class = $entry['class'];
            $model = new $class;
            $table = $model->getTable();
            $deletedCol = $model->getDeletedAtColumn();
            $label = $entry['label'];
            $type = class_basename($class);
            $nameExpr = $this->getModelNameExpression($class, $table);

            $where = "{$table}.{$deletedCol} IS NOT NULL";

            if ($search !== null) {
                $searchCondition = $this->buildSearchCondition($class, $table, $search);
                if ($searchCondition !== null) {
                    $where .= " AND ({$searchCondition})";
                }
            }

            $subQueries[] = "SELECT {$table}.id, '{$class}' as model_class, '{$type}' as type, '{$label}' as type_label, {$nameExpr} as name, {$table}.{$deletedCol} as deleted_at FROM {$table} WHERE {$where}";
        }

        return implode(' UNION ALL ', $subQueries);
    }

    /**
     * Map a raw UNION result row to the unified trash item array.
     *
     * @param  object  $row  stdClass from DB::select
     * @return array<string, mixed>
     */
    protected function unifiedItemFromRow(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'class' => $row->model_class,
            'type' => $row->type,
            'type_label' => $row->type_label,
            'name' => $row->name,
            'deleted_at' => $row->deleted_at,
        ];
    }

    /**
     * Return a SQL expression that computes the human-readable name for a model.
     *
     * Mirrors resolveName() but operates at the database level so the name is
     * available directly from the UNION subquery without fetching the full row.
     */
    protected function getModelNameExpression(string $class, string $table): string
    {
        return match ($class) {
            User::class => "CONCAT({$table}.name, ' (', {$table}.email, ')')",
            Sale::class => "COALESCE({$table}.customer_name, CONCAT('Penjualan #', {$table}.id))",
            Purchase::class => "CONCAT('Pembelian #', {$table}.id)",
            PurchaseItem::class => "CONCAT('Item Pembelian #', {$table}.id)",
            SaleItem::class => "CONCAT('Item Penjualan #', {$table}.id)",
            SaleReturn::class => "CONCAT('Retur Penjualan #', {$table}.id)",
            SaleReturnItem::class => "CONCAT('Item Retur #', {$table}.id)",
            StockLog::class => "CONCAT('Log Stok #', {$table}.id)",
            StockAdjustment::class => "CONCAT('Penyesuaian Stok #', {$table}.id)",
            StockTransfer::class => "CONCAT('Transfer Stok #', {$table}.id)",
            Shift::class => "CONCAT('Shift #', {$table}.id, ' (', {$table}.status, ')')",
            PaymentTransaction::class => "CONCAT('Pembayaran #', {$table}.id)",
            PriceHistory::class => "CONCAT('Riwayat Harga #', {$table}.id)",
            SaleEmail::class => "COALESCE({$table}.email, CONCAT('Email Penjualan #', {$table}.id))",
            default => "COALESCE({$table}.name, CAST({$table}.id AS CHAR))",
        };
    }

    /**
     * Build a model-specific search condition for a single tracked model.
     *
     * Returns a raw SQL fragment (e.g. "name LIKE '%foo%' OR email LIKE '%foo%'")
     * or null when the model has no searchable columns. The search string is
     * safely escaped via PDO::quote.
     */
    protected function buildSearchCondition(string $class, string $table, string $search): ?string
    {
        $escaped = DB::connection()->getPdo()->quote('%'.$search.'%');

        return match ($class) {
            User::class => "{$table}.name LIKE {$escaped} OR {$table}.email LIKE {$escaped}",
            Sale::class => "{$table}.customer_name LIKE {$escaped} OR {$table}.id LIKE {$escaped}",
            Purchase::class, PurchaseItem::class => "{$table}.id LIKE {$escaped}",
            SaleEmail::class => "{$table}.email LIKE {$escaped}",
            SaleReturn::class, StockAdjustment::class => "{$table}.reason LIKE {$escaped} OR {$table}.id LIKE {$escaped}",
            PaymentTransaction::class => "{$table}.external_id LIKE {$escaped} OR {$table}.id LIKE {$escaped}",
            SaleItem::class, SaleReturnItem::class, StockLog::class, StockTransfer::class, Shift::class, PriceHistory::class => "{$table}.id LIKE {$escaped}",
            default => "{$table}.name LIKE {$escaped}",
        };
    }
}
