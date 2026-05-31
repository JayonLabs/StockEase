<?php

namespace Tests;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

/**
 * @property User $admin
 * @property User $cashier
 * @property User $warehouse
 * @property User $superAdmin
 * @property User $warehouseUser
 * @property User $user1
 * @property User $user2
 * @property Product $product
 * @property Supplier $supplier
 * @property Category $category
 * @property Warehouse $warehouseModel
 * @property mixed $service
 * @property string $logPath
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // FAIL-SAFE: Memastikan database yang digunakan adalah 'testing'
        if (app()->environment() !== 'testing') {
            // Cobalah untuk memaksa environment ke testing jika belum
        }

        // Jika terdeteksi 'stockease', hentikan test seketika.
        if (DB::connection()->getDatabaseName() === 'stockease') {
            throw new \Exception('BAHAYA: Testing mencoba mengakses database utama (stockease)! Koneksi dihentikan untuk melindungi data Anda.');
        }

        $this->seed(RoleAndPermissionSeeder::class);
    }
}
