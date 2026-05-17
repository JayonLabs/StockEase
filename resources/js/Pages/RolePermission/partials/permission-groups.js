import { h } from 'vue';

/**
 * Group permissions by module/category based on permission name.
 *
 * @param {Array} permissions
 * @returns {Object}
 */
export function groupPermissions(permissions) {
    const groups = {};

    const moduleMap = {
        dashboard: 'Dashboard',
        users: 'Manajemen User',
        products: 'Manajemen Produk',
        categories: 'Manajemen Kategori',
        units: 'Manajemen Satuan',
        suppliers: 'Manajemen Supplier',
        promotions: 'Promo & Diskon',
        purchases: 'Pembelian',
        pos: 'POS (Kasir)',
        sales: 'Penjualan',
        sale: 'Penjualan',
        sale_detail: 'Detail Penjualan',
        sale_returns: 'Retur Penjualan',
        return_detail: 'Detail Retur',
        shifts: 'Manajemen Shift',
        shift: 'Manajemen Shift',
        midtrans: 'Transaksi Midtrans',
        sale_reports: 'Laporan Penjualan',
        purchase_reports: 'Laporan Pembelian',
        stock_reports: 'Laporan Stock',
        expiry_reports: 'Laporan Kedaluwarsa',
        profit_loss_reports: 'Laporan Laba / Rugi',
        product_movement_reports: 'Analisis Produk',
        stock_logs: 'Log Stock',
        stock_adjustments: 'Stock Opname',
        trash: 'Sampah',
        file_manager: 'File Manager',
        files: 'File Manager',
        permissions: 'Permission',
        role_permissions: 'Role Permission',
        user_permissions: 'User Permission',
        notifications: 'Notifikasi',
        stock_alerts: 'Alert Stock',
        queue_worker_logs: 'Queue Worker Logs',
        activity_logs: 'Activity Log',
        profile: 'Profil',
        password: 'Profil',
        photo_profile: 'Profil',
    };

    permissions.forEach((perm) => {
        const parts = perm.name.split('_');
        let module = 'Lainnya';

        // Try to match multi-word modules first
        if (perm.name.includes('sale_reports')) {
            module = 'Laporan Penjualan';
        } else if (perm.name.includes('purchase_reports')) {
            module = 'Laporan Pembelian';
        } else if (perm.name.includes('stock_reports')) {
            module = 'Laporan Stock';
        } else if (perm.name.includes('expiry_reports')) {
            module = 'Laporan Kedaluwarsa';
        } else if (perm.name.includes('profit_loss_reports')) {
            module = 'Laporan Laba / Rugi';
        } else if (perm.name.includes('product_movement_reports')) {
            module = 'Analisis Produk';
        } else if (perm.name.includes('stock_adjustments')) {
            module = 'Stock Opname';
        } else if (perm.name.includes('stock_logs')) {
            module = 'Log Stock';
        } else if (perm.name.includes('stock_alerts')) {
            module = 'Alert Stock';
        } else if (perm.name.includes('role_permissions')) {
            module = 'Role Permission';
        } else if (perm.name.includes('user_permissions')) {
            module = 'User Permission';
        } else if (perm.name.includes('queue_worker_logs')) {
            module = 'Queue Worker Logs';
        } else if (perm.name.includes('photo_profile')) {
            module = 'Profil';
        } else if (
            perm.name.includes('file_manager') ||
            perm.name.includes('_files')
        ) {
            module = 'File Manager';
        } else if (perm.name.includes('sale_returns')) {
            module = 'Retur Penjualan';
        } else if (perm.name.includes('return_detail')) {
            module = 'Detail Retur';
        } else if (perm.name.includes('sale_detail')) {
            module = 'Detail Penjualan';
        } else if (perm.name.includes('midtrans')) {
            module = 'Transaksi Midtrans';
        } else if (perm.name.includes('qris_payment')) {
            module = 'POS (Kasir)';
        } else if (perm.name.includes('send_invoice')) {
            module = 'POS (Kasir)';
        } else if (perm.name.includes('pos_cart')) {
            module = 'POS (Kasir)';
        } else if (perm.name.includes('checkout_pos')) {
            module = 'POS (Kasir)';
        } else if (perm.name.includes('access_pos')) {
            module = 'POS (Kasir)';
        } else if (parts.length >= 2) {
            const key = parts.slice(1).join('_');
            if (moduleMap[key]) {
                module = moduleMap[key];
            } else if (moduleMap[parts[1]]) {
                module = moduleMap[parts[1]];
            }
        }

        if (!groups[module]) {
            groups[module] = [];
        }
        groups[module].push(perm);
    });

    // Sort groups by key
    const sortedGroups = {};
    Object.keys(groups)
        .sort()
        .forEach((key) => {
            sortedGroups[key] = groups[key];
        });

    return sortedGroups;
}

/**
 * Format permission name for display.
 *
 * @param {string} name
 * @returns {string}
 */
export function formatPermissionName(name) {
    return name.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
}
