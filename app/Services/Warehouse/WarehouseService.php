<?php

namespace App\Services\Warehouse;

use App\Models\Warehouse;
use Cviebrock\EloquentSluggable\Services\SlugService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WarehouseService
{
    /**
     * Get paginated warehouses with searching.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPaginatedWarehouses(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return Warehouse::query()
            ->when($filters['search'] ?? null, function ($query, $search) {
                return $query->where('name', 'like', '%'.$search.'%');
            })
            ->orderBy('name', 'asc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Store a new warehouse.
     *
     * @param  array{name: string, address: string|null, phone: string|null, is_active: bool}  $data
     */
    public function storeWarehouse(array $data): Warehouse
    {
        $slug = SlugService::createSlug(Warehouse::class, 'slug', $data['name']);

        return Warehouse::create([
            'slug' => $slug,
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? $data['is_active'] : true,
        ]);
    }

    /**
     * Update an existing warehouse.
     *
     * @param  array{name: string, address: string|null, phone: string|null, is_active: bool}  $data
     */
    public function updateWarehouse(Warehouse $warehouse, array $data): bool
    {
        $payload = [
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? $data['is_active'] : true,
        ];

        if ($warehouse->name !== $data['name']) {
            $payload['slug'] = SlugService::createSlug(Warehouse::class, 'slug', $data['name']);
        }

        return $warehouse->update($payload);
    }

    /**
     * Delete a warehouse.
     */
    public function deleteWarehouse(Warehouse $warehouse): ?bool
    {
        return $warehouse->delete();
    }
}
