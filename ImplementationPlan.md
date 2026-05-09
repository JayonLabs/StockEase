# StockEase Implementation Plan: Future Enhancements

Rencana ini merinci fitur-fitur tambahan yang direkomendasikan untuk meningkatkan kapabilitas sistem kasir dan gudang **StockEase**. Fokus utama adalah pada akurasi data, analisis keuangan, dan efisiensi operasional.

## 1. Modul Gudang & Inventaris (Warehouse)

### C. Multi-Gudang (Multi-Warehouse)

- **Masalah:** Pengelolaan stok di beberapa lokasi fisik (misal: Toko A, Toko B, Gudang Pusat).
- **Fitur:** Pemindahan stok antar gudang, filter laporan per gudang.
- **Teknis:** Tabel `warehouses` dan tabel pivot `warehouse_product`.

## 4. Pengalaman Pengguna & Integrasi (Technical)

### A. Progressive Web App (PWA)

- **Fitur:** Memungkinkan aplikasi diinstal di smartphone/desktop dan meningkatkan performa loading.
- **Teknis:** Integrasi `vite-plugin-pwa` dan Service Workers.

### B. Integrasi Printer Thermal

- **Fitur:** Cetak struk langsung ke printer thermal (Bluetooth/USB) dari browser.
- **Teknis:** Menggunakan Web Bluetooth API atau library seperti `print-js`.

## Prioritas Implementasi

| Fase                     | Fitur                          | Perkiraan Waktu |
| :----------------------- | :----------------------------- | :-------------- |
| **Fase 1 (Urgent)**      | Stock Opname & HPP (Laba/Rugi) | 2 Minggu        |
| **Fase 2 (Operasional)** | Shift Management & Retur       | 2 Minggu        |
| **Fase 3 (Growth)**      | Expiry Date & Promo            | 3 Minggu        |
| **Fase 4 (Scale)**       | Multi-Gudang & PWA             | 4 Minggu        |

---

_Dibuat oleh: Gemini CLI (Senior AI Engineer)_
