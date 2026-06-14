<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'price_monthly' => fake()->randomElement([0, 50000, 149000, 299000]),
            'price_annual' => fn (array $attrs) => $attrs['price_monthly'] > 0
                ? (int) round($attrs['price_monthly'] * 10)
                : 0,
            'max_products' => fake()->optional()->numberBetween(50, 9999),
            'max_users' => fake()->optional()->numberBetween(1, 50),
            'max_warehouses' => fake()->optional()->numberBetween(1, 10),
            'max_shifts' => fake()->optional()->numberBetween(1, 5),
            'features' => $this->defaultFeatures(),
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => [
            'price_monthly' => 0,
            'price_annual' => 0,
            'trial_days' => 0,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'price_monthly' => 149000,
            'price_annual' => 1490000,
            'trial_days' => 14,
        ]);
    }

    public function pemula(): static
    {
        return $this->state(fn () => [
            'name' => 'Pemula',
            'slug' => 'pemula',
            'description' => 'Untuk UMKM yang baru memulai digitalisasi usaha.',
            'price_monthly' => 50000,
            'price_annual' => 500000,
            'max_products' => 100,
            'max_users' => 1,
            'max_warehouses' => 1,
            'max_shifts' => null,
            'features' => [
                ['key' => 'products', 'label' => 'Produk, Kategori & Satuan', 'included' => true, 'card_order' => 1],
                ['key' => 'pos', 'label' => 'Point of Sale (POS)', 'included' => true, 'card_order' => 2],
                ['key' => 'barcode', 'label' => 'Barcode Scanner', 'included' => true, 'card_order' => 3],
                ['key' => 'qris', 'label' => 'Pembayaran QRIS Midtrans', 'included' => false],
                ['key' => 'invoice', 'label' => 'Kirim Invoice (Email/WA)', 'included' => false],
                ['key' => 'sales_report', 'label' => 'Laporan Penjualan & Ekspor', 'included' => true, 'card_order' => 4],
                ['key' => 'purchase_report', 'label' => 'Laporan Pembelian & Ekspor', 'included' => false],
                ['key' => 'stock_report', 'label' => 'Laporan Stok & Ekspor', 'included' => false],
                ['key' => 'profit_loss', 'label' => 'Laporan Laba Rugi', 'included' => false],
                ['key' => 'purchasing', 'label' => 'Manajemen Pembelian & Supplier', 'included' => false],
                ['key' => 'multi_warehouse', 'label' => 'Multi-Gudang & Transfer Stok', 'included' => false],
                ['key' => 'low_stock', 'label' => 'Notifikasi Stok Rendah', 'included' => false],
                ['key' => 'cashier_shift', 'label' => 'Shift Kasir', 'included' => false],
                ['key' => 'user_roles', 'label' => 'Manajemen Pengguna & Hak Akses', 'included' => false],
                ['key' => 'activity_log', 'label' => 'Log Aktivitas', 'included' => false],
                ['key' => 'file_manager', 'label' => 'File Manager', 'included' => false],
            ],
            'trial_days' => 0,
            'sort_order' => 1,
        ]);
    }

    public function profesional(): static
    {
        return $this->state(fn () => [
            'name' => 'Profesional',
            'slug' => 'profesional',
            'description' => 'Untuk usaha menengah yang berkembang pesat.',
            'price_monthly' => 149000,
            'price_annual' => 1490000,
            'max_products' => null,
            'max_users' => 10,
            'max_warehouses' => 3,
            'max_shifts' => null,
            'features' => [
                ['key' => 'products', 'label' => 'Produk, Kategori & Satuan', 'included' => true, 'card_order' => 1],
                ['key' => 'pos', 'label' => 'Point of Sale (POS)', 'included' => true, 'card_order' => 2],
                ['key' => 'barcode', 'label' => 'Barcode Scanner', 'included' => true],
                ['key' => 'qris', 'label' => 'Pembayaran QRIS Midtrans', 'included' => true, 'card_order' => 3],
                ['key' => 'invoice', 'label' => 'Kirim Invoice (Email/WA)', 'included' => true],
                ['key' => 'sales_report', 'label' => 'Laporan Penjualan & Ekspor', 'included' => true, 'card_order' => 4],
                ['key' => 'purchase_report', 'label' => 'Laporan Pembelian & Ekspor', 'included' => true],
                ['key' => 'stock_report', 'label' => 'Laporan Stok & Ekspor', 'included' => true],
                ['key' => 'profit_loss', 'label' => 'Laporan Laba Rugi', 'included' => false],
                ['key' => 'purchasing', 'label' => 'Manajemen Pembelian & Supplier', 'included' => true, 'card_order' => 5],
                ['key' => 'multi_warehouse', 'label' => 'Multi-Gudang & Transfer Stok', 'included' => true, 'card_order' => 6],
                ['key' => 'low_stock', 'label' => 'Notifikasi Stok Rendah', 'included' => true],
                ['key' => 'cashier_shift', 'label' => 'Shift Kasir', 'included' => true, 'card_order' => 7],
                ['key' => 'user_roles', 'label' => 'Manajemen Pengguna & Hak Akses', 'included' => false],
                ['key' => 'activity_log', 'label' => 'Log Aktivitas', 'included' => true, 'card_order' => 8],
                ['key' => 'file_manager', 'label' => 'File Manager', 'included' => false],
            ],
            'trial_days' => 14,
            'sort_order' => 2,
        ]);
    }

    public function enterprise(): static
    {
        return $this->state(fn () => [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'description' => 'Untuk bisnis besar dengan kebutuhan lengkap.',
            'price_monthly' => 299000,
            'price_annual' => 2990000,
            'max_products' => null,
            'max_users' => null,
            'max_warehouses' => null,
            'max_shifts' => null,
            'features' => [
                ['key' => 'products', 'label' => 'Produk, Kategori & Satuan', 'included' => true, 'card_order' => 1],
                ['key' => 'pos', 'label' => 'Point of Sale (POS)', 'included' => true, 'card_order' => 2],
                ['key' => 'barcode', 'label' => 'Barcode Scanner', 'included' => true, 'card_order' => 3],
                ['key' => 'qris', 'label' => 'Pembayaran QRIS Midtrans', 'included' => true, 'card_order' => 4],
                ['key' => 'invoice', 'label' => 'Kirim Invoice (Email/WA)', 'included' => true, 'card_order' => 5],
                ['key' => 'sales_report', 'label' => 'Laporan Penjualan & Ekspor', 'included' => true, 'card_order' => 6],
                ['key' => 'purchase_report', 'label' => 'Laporan Pembelian & Ekspor', 'included' => true, 'card_order' => 7],
                ['key' => 'stock_report', 'label' => 'Laporan Stok & Ekspor', 'included' => true],
                ['key' => 'profit_loss', 'label' => 'Laporan Laba Rugi', 'included' => true, 'card_order' => 8],
                ['key' => 'purchasing', 'label' => 'Manajemen Pembelian & Supplier', 'included' => true, 'card_order' => 9],
                ['key' => 'multi_warehouse', 'label' => 'Multi-Gudang & Transfer Stok', 'included' => true, 'card_order' => 10],
                ['key' => 'low_stock', 'label' => 'Notifikasi Stok Rendah', 'included' => true],
                ['key' => 'cashier_shift', 'label' => 'Shift Kasir', 'included' => true],
                ['key' => 'user_roles', 'label' => 'Manajemen Pengguna & Hak Akses', 'included' => true, 'card_order' => 11],
                ['key' => 'activity_log', 'label' => 'Log Aktivitas', 'included' => true, 'card_order' => 12],
                ['key' => 'file_manager', 'label' => 'File Manager', 'included' => true, 'card_order' => 13],
            ],
            'trial_days' => 14,
            'sort_order' => 3,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    private function defaultFeatures(): array
    {
        return [
            ['key' => 'products', 'label' => 'Produk, Kategori & Satuan', 'included' => true, 'card_order' => 1],
            ['key' => 'pos', 'label' => 'Point of Sale (POS)', 'included' => true, 'card_order' => 2],
            ['key' => 'barcode', 'label' => 'Barcode Scanner', 'included' => true],
            ['key' => 'qris', 'label' => 'Pembayaran QRIS Midtrans', 'included' => false],
            ['key' => 'invoice', 'label' => 'Kirim Invoice (Email/WA)', 'included' => false],
            ['key' => 'sales_report', 'label' => 'Laporan Penjualan & Ekspor', 'included' => true, 'card_order' => 3],
            ['key' => 'purchase_report', 'label' => 'Laporan Pembelian & Ekspor', 'included' => false],
            ['key' => 'stock_report', 'label' => 'Laporan Stok & Ekspor', 'included' => false],
            ['key' => 'profit_loss', 'label' => 'Laporan Laba Rugi', 'included' => false],
            ['key' => 'purchasing', 'label' => 'Manajemen Pembelian & Supplier', 'included' => false],
            ['key' => 'multi_warehouse', 'label' => 'Multi-Gudang & Transfer Stok', 'included' => false],
            ['key' => 'low_stock', 'label' => 'Notifikasi Stok Rendah', 'included' => false],
            ['key' => 'cashier_shift', 'label' => 'Shift Kasir', 'included' => false],
            ['key' => 'user_roles', 'label' => 'Manajemen Pengguna & Hak Akses', 'included' => false],
            ['key' => 'activity_log', 'label' => 'Log Aktivitas', 'included' => false],
            ['key' => 'file_manager', 'label' => 'File Manager', 'included' => false],
        ];
    }
}
