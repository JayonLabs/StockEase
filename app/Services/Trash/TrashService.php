<?php

namespace App\Services\Trash;

use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
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
        ['class' => Product::class, 'label' => 'Produk'],
        ['class' => Supplier::class, 'label' => 'Supplier'],
        ['class' => Unit::class, 'label' => 'Satuan'],
        ['class' => User::class, 'label' => 'User'],
        ['class' => Promotion::class, 'label' => 'Promosi'],
        ['class' => Purchase::class, 'label' => 'Pembelian'],
        ['class' => Sale::class, 'label' => 'Penjualan'],
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
            } elseif ($class === Purchase::class) {
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
    ];

    /**
     * Columns that should be excluded from the attributes display.
     */
    protected array $attributeExclude = [
        'password',
        'remember_token',
        'email_verified_at',
        'image_path',
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
