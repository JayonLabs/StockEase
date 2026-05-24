<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Performance note: Using Permission::make()->saveOrFail() is more
     * performant than Permission::create() for bulk inserts.
     */
    public function run(): void
    {
        $now = now();
        $rows = array_map(fn (string $name) => [
            'name' => $name,
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ], $this->getPermissions());

        // Bulk insert — bypasses model events so Spatie cache is NOT flushed per row.
        DB::table(config('permission.table_names.permissions', 'permissions'))
            ->insertOrIgnore($rows);
    }

    /**
     * Get all permissions grouped by module.
     *
     * @return array<string>
     */
    public function getPermissions(): array
    {
        return [
            // ==================== DASHBOARD ====================
            'view_dashboard',

            // ==================== USER MANAGEMENT ====================
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            'reset_user_password',

            // ==================== PRODUCT MANAGEMENT ====================
            'view_products',
            'create_products',
            'edit_products',
            'delete_products',
            'edit_product_price',

            // ==================== CATEGORY MANAGEMENT ====================
            'view_categories',
            'create_categories',
            'edit_categories',
            'delete_categories',

            // ==================== UNIT MANAGEMENT ====================
            'view_units',
            'create_units',
            'edit_units',
            'delete_units',

            // ==================== SUPPLIER MANAGEMENT ====================
            'view_suppliers',
            'create_suppliers',
            'edit_suppliers',
            'delete_suppliers',

            // ==================== PROMOTION MANAGEMENT ====================
            'view_promotions',
            'create_promotions',
            'edit_promotions',
            'delete_promotions',

            // ==================== PURCHASE MANAGEMENT ====================
            'view_purchases',
            'create_purchases',
            'edit_purchases',
            'delete_purchases',

            // ==================== POS (POINT OF SALE) ====================
            'access_pos',
            'manage_pos_cart',
            'checkout_pos',
            'send_invoice',
            'create_qris_payment',

            // ==================== SALE HISTORY & RETURNS ====================
            'view_sales',
            'view_sale_detail',
            'export_sale_pdf',
            'view_sale_returns',
            'create_sale_returns',
            'view_return_detail',

            // ==================== SHIFT MANAGEMENT ====================
            'view_shifts',
            'open_shift',
            'close_shift',
            'view_shift_detail',

            // ==================== PAYMENT & MIDTRANS ====================
            'view_midtrans_transactions',
            'manage_midtrans_webhook',

            // ==================== REPORTS - SALES ====================
            'view_sale_reports',
            'export_sale_reports_pdf',
            'export_sale_reports_excel',

            // ==================== REPORTS - PURCHASES ====================
            'view_purchase_reports',
            'export_purchase_reports_pdf',
            'export_purchase_reports_excel',

            // ==================== REPORTS - STOCK ====================
            'view_stock_reports',
            'export_stock_reports_pdf',
            'export_stock_reports_excel',

            // ==================== REPORTS - EXPIRY ====================
            'view_expiry_reports',

            // ==================== REPORTS - PROFIT & LOSS ====================
            'view_profit_loss_reports',

            // ==================== REPORTS - PRODUCT MOVEMENT ====================
            'view_product_movement_reports',

            // ==================== STOCK MANAGEMENT ====================
            'view_stock_logs',
            'view_stock_adjustments',
            'create_stock_adjustments',

            // ==================== TRASH / RECYCLE BIN ====================
            'view_trash',
            'restore_trash',
            'force_delete_trash',

            // ==================== FILE MANAGER ====================
            'view_file_manager',
            'upload_files',
            'download_files',
            'delete_files',

            // ==================== PERMISSION & ACCESS CONTROL ====================
            'view_permissions',
            'create_permissions',
            'edit_permissions',
            'delete_permissions',
            'view_role_permissions',
            'edit_role_permissions',
            'view_user_permissions',
            'edit_user_permissions',

            // ==================== NOTIFICATIONS ====================
            'view_notifications',
            'manage_notifications',

            // ==================== STOCK ALERTS ====================
            'view_stock_alerts',

            // ==================== SYSTEM TOOLS ====================
            'view_queue_worker_logs',
            'view_activity_logs',

            // ==================== WAREHOUSE MANAGEMENT ====================
            'view_warehouses',
            'create_warehouses',
            'edit_warehouses',
            'delete_warehouses',

            // ==================== STOCK TRANSFER ====================
            'view_stock_transfers',
            'create_stock_transfers',

            // ==================== PROFILE ====================
            'view_profile',
            'edit_profile',
            'update_password',
            'manage_photo_profile',
        ];
    }

    /**
     * Get permission assignments for each role.
     *
     * NOTE: super_admin does NOT need explicit permissions because
     * Gate::before in AppServiceProvider grants all permissions automatically.
     *
     * @return array<string, array<string>>
     */
    public function getRolePermissions(): array
    {
        return [
            // super_admin intentionally omitted — handled by Gate::before

            'admin' => [
                // Dashboard
                'view_dashboard',

                // User Management
                'view_users',
                'create_users',
                'edit_users',
                'delete_users',
                'reset_user_password',

                // Product Management
                'view_products',
                'create_products',
                'edit_products',
                'delete_products',
                'edit_product_price',

                // Category Management
                'view_categories',
                'create_categories',
                'edit_categories',
                'delete_categories',

                // Unit Management
                'view_units',
                'create_units',
                'edit_units',
                'delete_units',

                // Supplier Management
                'view_suppliers',
                'create_suppliers',
                'edit_suppliers',
                'delete_suppliers',

                // Promotion Management
                'view_promotions',
                'create_promotions',
                'edit_promotions',
                'delete_promotions',

                // Purchase Management
                'view_purchases',
                'create_purchases',
                'edit_purchases',
                'delete_purchases',

                // POS
                'access_pos',
                'manage_pos_cart',
                'checkout_pos',
                'send_invoice',
                'create_qris_payment',

                // Sale History & Returns
                'view_sales',
                'view_sale_detail',
                'export_sale_pdf',
                'view_sale_returns',
                'create_sale_returns',
                'view_return_detail',

                // Shift Management
                'view_shifts',
                'open_shift',
                'close_shift',
                'view_shift_detail',

                // Payment & Midtrans
                'view_midtrans_transactions',

                // Reports - Sales
                'view_sale_reports',
                'export_sale_reports_pdf',
                'export_sale_reports_excel',

                // Reports - Purchases
                'view_purchase_reports',
                'export_purchase_reports_pdf',
                'export_purchase_reports_excel',

                // Reports - Stock
                'view_stock_reports',
                'export_stock_reports_pdf',
                'export_stock_reports_excel',

                // Reports - Expiry
                'view_expiry_reports',

                // Reports - Profit & Loss
                'view_profit_loss_reports',

                // Reports - Product Movement
                'view_product_movement_reports',

                // Stock Management
                'view_stock_logs',
                'view_stock_adjustments',
                'create_stock_adjustments',

                // Trash
                'view_trash',
                'restore_trash',
                'force_delete_trash',

                // File Manager
                'view_file_manager',
                'upload_files',
                'download_files',
                'delete_files',

                // Permission & Access Control
                'view_permissions',
                'create_permissions',
                'edit_permissions',
                'delete_permissions',
                'view_role_permissions',
                'edit_role_permissions',
                'view_user_permissions',
                'edit_user_permissions',

                // Notifications
                'view_notifications',
                'manage_notifications',

                // Stock Alerts
                'view_stock_alerts',

                // System Tools
                'view_queue_worker_logs',

                // Warehouse Management
                'view_warehouses',
                'create_warehouses',
                'edit_warehouses',
                'delete_warehouses',

                // Stock Transfer
                'view_stock_transfers',
                'create_stock_transfers',

                // Profile
                'view_profile',
                'edit_profile',
                'update_password',
                'manage_photo_profile',
            ],

            'cashier' => [
                // Dashboard
                'view_dashboard',

                // POS
                'access_pos',
                'manage_pos_cart',
                'checkout_pos',
                'send_invoice',
                'create_qris_payment',

                // Sale History & Returns
                'view_sales',
                'view_sale_detail',
                'view_sale_returns',
                'create_sale_returns',
                'view_return_detail',

                // Shift Management
                'view_shifts',
                'open_shift',
                'close_shift',
                'view_shift_detail',

                // Payment & Midtrans
                'view_midtrans_transactions',

                // Reports - Sales
                'view_sale_reports',
                'export_sale_reports_pdf',
                'export_sale_reports_excel',

                // File Manager
                'view_file_manager',
                'upload_files',
                'download_files',
                'delete_files',

                // Notifications
                'view_notifications',
                'manage_notifications',

                // Stock Alerts
                'view_stock_alerts',

                // Profile
                'view_profile',
                'edit_profile',
                'update_password',
                'manage_photo_profile',
            ],

            'warehouse' => [
                // Dashboard
                'view_dashboard',

                // Product Management
                'view_products',
                'create_products',
                'edit_products',
                'delete_products',

                // Category Management
                'view_categories',

                // Unit Management
                'view_units',
                'create_units',
                'edit_units',
                'delete_units',

                // Supplier Management
                'view_suppliers',
                'create_suppliers',
                'edit_suppliers',
                'delete_suppliers',

                // Purchase Management
                'view_purchases',
                'create_purchases',
                'edit_purchases',
                'delete_purchases',

                // Reports - Purchases
                'view_purchase_reports',
                'export_purchase_reports_pdf',
                'export_purchase_reports_excel',

                // Reports - Stock
                'view_stock_reports',
                'export_stock_reports_pdf',
                'export_stock_reports_excel',

                // Reports - Expiry
                'view_expiry_reports',

                // Stock Management
                'view_stock_logs',
                'view_stock_adjustments',
                'create_stock_adjustments',

                // File Manager
                'view_file_manager',
                'upload_files',
                'download_files',
                'delete_files',

                // Notifications
                'view_notifications',
                'manage_notifications',

                // Stock Alerts
                'view_stock_alerts',

                // Warehouse Management
                'view_warehouses',
                'create_warehouses',
                'edit_warehouses',
                'delete_warehouses',

                // Stock Transfer
                'view_stock_transfers',
                'create_stock_transfers',

                // Profile
                'view_profile',
                'edit_profile',
                'update_password',
                'manage_photo_profile',
            ],
        ];
    }
}
