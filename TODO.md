<!-- TODO: 04/04/2026 -->

- buat unit test untuk controller yang ada di folder dashboard (Done)
- upgrade ke laravel 13 (Done)
- install laravel boost (Done)
- fix bug di cart page (Done)
- refactor controller pindahil logic ke service class (Done)

<!-- TODO: 05/04/2026 -->

- fix CI github (Done)
- buatin halaman custom error agar tidak pakai bawaan laravel (Done)
- untuk notifikasi itu jangan pakai pooling biar hemat request dan tidak membebankan server (Done)
- buatin CD ke cpanel (Done)

<!-- TODO: 12/04/2026 -->

- fix CI github (Done)
- fix CD ke cpanel (Done)
- fix query duplikat (Done)
- security di pos karena disana perhitungannya masih di frontend validasi di backend lagi (Done)
- cek di database untuk field currency itu jadikan aja desimal biar karena ini untuk harga (Done)
- refactor struktur folder controller dan service (Done)
- refactor struktur folder request (Done)
- fix dark mode (Done)
- ux di harga di pos page isikan titik (Done)
- bug ketika barang dimasukkan ke keranjang itu malah kehitung terjual di grafic penjualan mingguan dashboard (Done)
- fix error message di cart ketika memasukkan angka 9 banyak itu errornya database alangkah baiknya errornya bukan database message (Done)
- fix bug di pos bagian cart itu ketika menggunakan metode pembayaran qris kembaliannya masih ke list (Done)
- fitur satuan itu biarin bisa CRUD aja (Done)

<!-- TODO: 19/04/2026 -->

- scan barcode di pos sistem dan langsung masukkan ke keranjang (Done)
- bug Grafik Penjualan Mingguan di dashboard itu ga mau nampilin data di server tapi di local mau (Done)
- Stock Opname (Penyesuaian Stok) (Done)
- Pelacakan Tanggal Kedaluwarsa (Expiry Date) (Done)
- untuk mengubah harga beli dan jual itu buatkan page khusus untuk mempermudah audit (Done)
- penambahan grafik dll di halaman dashboard (Done)
- penambahan exired di tambah produk (Done)
- logika FEFO (First Expired, First Out) untuk penjualan product (Done)
- buat action class untuk reduceStockFromSaleItems dan updateExpiryDate supaya tidak di taruh di model product (Done)
- di update product experied itu di disable aja kan udah ada di purcase itu biar disana nambahin product baru (Done)

<!-- TODO: 26/04/2026 -->

- update depedency js (Done)
- Laporan Laba/Rugi (Profit & Loss) (Done)
- refactor app sidebar (Done)
- untuk calculateTotal di model sale dipindah buat action class (Done)
- di laporan laba rugi itu date filternya pakai date rage dari shadcn vue (Done)
- fix tabel biar rapi dan sejajar (Done)
- refactor isikan pagination di profit loss bagian Rincian Per Produk Analisis margin dan profitabilitas setiap produk (Done)
- penambahan test case di dashboard test (Done)
- penambahan test case di unit test (Done)
- penambahan test case di category controller test (Done)
- fix bug di profit loss filter datatable bentrok dengan filter date di page (Done)
- refactor SaleReportController buatin service class dan sempurnakan testnya (Done)
- refactor PurchaseReportController buatin service class dan sempurnakan testnya (Done)
- refactor StockReportController buatin service class dan sempurnakan testnya (Done)
- refactor ExpiryReportController buatin service class dan buatkan unit test (Done)
- refactor ProfitLossReportController buatin service class dan sempurnakan testnya (Done)
- refactor LogStockController buatin service class dan buatkan unit test (Done)
- fix bug di expiry page itu filternya bentrok dengan filter di datatabel (Done)

<!-- TODO: 03/05/2026 -->

- rapikan struktur controller (pindahkan Dashboard, Promotion, dan Unit ke subfolder) (Done)
- rapikan penempatan file test di folder tests/Feature agar lebih terorganisir (Done)
- fix n+1 query, query duplikat, dan jangan pakai datatable limit aja 5 atau 10 data di dashboard (Done)
- penambahan test case untuk PurchaseController (Done)
- penambahan test case untuk SupplierController (Done)
- penambahan test case untuk ProductController (Done)
- hapus file code auth yang sudah tidak terpakai (Done)
- improve ui/ux di halaman report (Done)
- fix ui di halaman POS (Done)
- fitur Analisis Produk (Fast & Slow Moving) (Done)
- fitur Manajemen Diskon & Promo (Done)
- fix fitur Manajemen Diskon & Promo beserta UI/UX nya dan tambahkan lagi testnya (Done)
- fix date form di diskon page (Done)
- perbaikan UI/UX di halaman tambah produk, edit dan show produk (Done)
- fix bug sidebar active (Done)
- database seeder untuk promo diskon (Done)
- fix filter pencarian datatable di diskon promo page (Done)
- fitur filter tanggal di halaman promo diskon (Done)
- fix bug di update form promo diskon itu ketika typenya persen nilai diskonnya malah kayak harga (Done)
- refactor tabel di halaman promo untuk type dan nilai itu dipisah (Done)
- unit test untuk file manager controller (Done)
- fix bug di sidebar active laporan laba rugi dan analisis produk (Done)
- fix Form edit pembelian produk di tanggal Kadaluwarsa itu pakai date shadcn vue (Done)
- refactor route web buatkan folder web dengan route sesuai fitur (Done)

<!-- TODO: 10/05/2026 -->

- fix ci github (Done)
- fitur Manajemen Shift & Kas (Shift Management) (Done)
- fix input number yang ada di modal tambah pembelian produk dan editnya (Done)
- fitur Retur Penjualan (Sales Returns) (Done)
- refactor form pengembalian penjualan untuk di dark mode tabelnya tidak terlihat dan input Qty Retur pakai number field shadcn vue (Done)
- buatkan enum class untuk tabel yang ada enumnya biar konsisten (Done)
- fix type enum database yang masih hardcode di logic (Done)
- refactor untuk implementasi soft delete karena yang sekarang semuanya hard delete backend (Done)
- fitur trash, restore dan remove permanent, buatkan page sendiri (Done)
- fitur show di trash page (Done)
- refactor ui/ux di halaman profile user (Done)
- refactor date filter di Pembelian / Purchase itu pakai component DateRangePicker dan isikan reset filter (Done)
- refactor date filter di Penjualan / Sale itu pakai component DateRangePicker dan isikan reset filter (Done)
- refactor date filter di Return Penjualan / Sales Return itu pakai component DateRangePicker dan isikan reset filter (Done)
- refactor date filter di Transaksi Midtrans itu pakai component DateRangePicker dan isikan reset filter (Done)
- refactor date filter di Log Stock page itu pakai component DateRangePicker dan isikan reset filter (Done)
- tambahkan lagi test case di unit/service itu untuk setiap servicenya (Done)
- fitur kirim email ke user jika ingin invoice dari POS (Done)
- fitur schedule untuk kirim email dan notification dashboard (Done)
- buatkan view untuk logs/queue-worker.log yang ada di route console (Done)
- fix warning component vue (Done)
- buatin service class untuk controller QueueWorkerLogController (Done)
- fix struktur tabel di profit loss report (Done)
- normalisasi database untuk performa (Done)
- fix error di queue worker log page (Done)

<!-- TODO: 17/05/2026 -->

- fix bug di dashboard logic admin Penjualan Hari Ini, Penjualan Bulan Ini, Pengeluaran Bulan Ini itu masih 0 padahal ada transaksi (Done)
- fix bug yang dimana ketika adjust stock di product opname ketika dibawah stock minimal itu ga mau ada notifikasi (Done)
- fix Status column di Penjualan page (Done)
- fix sidebar ketika pindah page itu malah viewnya loncat ke atas ga mau stay di sidebar (Done)
- refactor Product Movement page buatih component terpisah biar tidak panjang codenya (Done)
- refactor Profit Loss Report page buatih component terpisah biar tidak panjang codenya (Done)
- fix filter di folder laporan/report itu di mobile karena itu belum mobile friendly dan component DateRangeFilter tidak mobile friendly (Done)
- refactor component DateRangeFilter untuk mobile itu tanggalnya pakai Date atau calendar dari shadcn vue dan pisahin juga componentnya biar tidak panjang (Done)
- refactor role menggunakan role permission dari spatie (Done)
- bisa crud permission untuk role dan permission untuk setiap user (Done)
- fix datatable pagination lompat 2 kali (Done)
- fix di datatable bagian Rows per page itu bug ga mau di pilih misal milih 50 itu tetap 10 (Done)
- fitur activity log di aplikasi ini (Done)
- refactor bagian Edit Direct Permission itu biar isi search dan pakaikan component switch aja buatin page jangan page dialog (Done)
- refactor di bagian Queue Worker Logs itu untuk permissionnya harus spesifik (Done)
- fix npm run build (Done)
- fitur Multi-Gudang (Multi-Warehouse) (Done)

<!-- TODO: 24/05/2026 -->

- fix testing slow (Done)
- fix pencarian di stock-transfer (Done)
- fix bug di stock-transfer form kasih warehouse di search product (Done)
- warehouse-first stock architecture: warehouse_id ke purchases, purchase_items, stock_adjustments, stock_logs (Done)
- PurchaseService warehouse-aware: stok masuk ke warehouse_product pivot (Done)
- StockAdjustmentService warehouse-aware: old_stock dari pivot per gudang, FEFO per gudang (Done)
- form Pembelian dan Stock Opname: tambah warehouse selector (Done)
- implementasi POS warehouse-aware: kasir pilih gudang saat buka shift (Done)
- refactor untuk di pos page itu harus open shif dan pilih gudang terlebih dahulu dan productnya dari gudang yang dipilih dan fix bug modal di pos jika klik X itu kalau masukkin ke keranjang itu masih bisa harusnya cegah untuk ga bisa close modal dan cegah untuk nambahin ke keranjang lagi (Done)
- fix tabel di page penjualan bagian kasir itu rata kirikan (Done)
- fix query duplikat di page stock opname atau https://stockease.test/stock-adjustment dan https://stockease.test/activity-logs (Done)
- rafactor controller trash untuk cek yang soft delete karena ada penambahan (Done)
- refactor untuk date range picker di menu data transaksi dan penjualan serta log stock (Done)
- fix bug Broadcast events tidak implement ShouldDispatchAfterCommit (Done)
- fix bug API /api/low-stock terbuka tanpa autentikasi - data inventori bocor ke publik (Done)
- fix PaymentController: input amount Midtrans tidak divalidasi di sisi server (Done)
- fix ShiftController::close() missing authorization/ownership check (Done)
- fix NotifyStockAlert loads all users with N+1 notification query (Done)
- fix PosService calls getActiveShiftId() up to 3 times per checkout (Done)

<!-- TODO: 31/05/2026 -->

- fix TrashService loads all soft-deleted records into memory before paginating (Done)
- fix TrashService::resolveAttributeValue() causes N+1 query per FK attribute (Done)
- fix HandleInertiaRequests runs getAllPermissions() on every Inertia request (Done)
- fix PurchaseService::updatePurchase() has N+1 query in foreach loop (Done)
- refactor struktur folder test biar sesuai dengan struktur folder app (Done)
- fix RecalculateSaleTotal fetches all promotions on every execute() call (Done)
- fix Excel export class instantiated twice (store + download) in 3 report controllers (Done)
- fix Carbon::setLocale('id') called 4 times in DashboardService (Done)
- fix whereMonth() without whereYear() causes cross-year data pollution in dashboard (Done)
- fix StockAdjustmentService logs abs(diff) losing decrease direction in StockLog (Done)
- fix ReduceProductStock and RestoreProductStock use hardcoded type strings instead of enum (Done)
- fix PurchaseService::updatePurchase() can decrement stock below zero (Done)
- fix StockTransferService silently succeeds on insufficient stock (Done)
- refactor dari Refresh Database testing ke lazily refresh database (Done)
- fix DashboardService::cashierData() recent transactions not filtered by user_id (Done)
- fix PosController compares sale status with raw string instead of SaleStatus enum (Done)
- fix ResetUserPasswordRequest uses weak password validation instead of Password::defaults() (Done)
- buatkan test untuk semua code request (Done)
- fix query duplikat di route https://stockease.test/sale dan https://stockease.test/sale-return (Done)
- fix PosController overwrites validated() data with raw request input for order_id (Done)
- fix NotificationController N+1 query: Product::find() inside transform loop (Done)
- fix FormRequest classes have authorize() returning true without role/permission check (Done)
- fix StoreProductRequest missing unique validation for SKU and barcode (Done)
- fix PosService draft sale race condition allows multiple drafts per user/shift (Done)
- fix File upload uses time() filename (collision risk) and file_get_contents() (memory inefficient) (Done)
- fix LIKE query used on integer ID columns bypasses index (Done)
- fix ProductService uses \DB and \Auth global namespace instead of imported facades (Done)
- fix AppServiceProvider uses loose == comparison instead of strict === for env check (Done)
- fix pagination di page activity log (Done)

<!-- TODO: 07/06/2026 -->

- fix error ci (Done)
- fix Missing Authorization on FileManager Routes — Semua User Terauthentikasi Bisa Akses (Done)
- fix Path Traversal — FileManager download/destroy Tidak Dibatasi ke Folder uploads/ (Done)
- fix IDOR di PosController::sendInvoice() — Invoice Bisa Dikirim dari Sale Orang Lain (Done)
- fix Missing authorize() di POS FormRequests — Tidak Ada Defense-in-Depth (Done)
- fix N+1 Query di PurchaseService::updatePurchase() untuk Item yang Dihapus (Done)
- fix Product::stockInWarehouse() Melakukan Query Individual per Pemanggilan dalam Loop (Done)
- buatkan landing page untuk sistem ini (Done)
- rubah untuk demo login itu pakai superadmin@dewajayon.my.id dengan password "password" (Done)
- fix error This action is unauthorized. di pos page ini aku pakai role super_admin (Done)
- implementasi fitur untuk langganan, fitur sesuai dengan di https://stockease.test/pricing (Done)
- fix data bocor di route https://stockease.test/user-permissions, https://stockease.test/file-manager, dan https://stockease.test/activity-logs (Done)
- fix query duplikat select \* company di semua route page (Done)
- fix trait deprecated untuk Tenancy (Done)
- buatkan test untuk race condition di semua logic yang sekiranya perlu untuk anti race condition (Done)

<!-- TODO: 14/06/2026 -->

- untuk UMKM itu 50 ribu aja perbulan tidak ada diskon untuk tahunan (Done)
- untuk ada dashboard lain untuk platform ku ini biar bisa lihat berapa user yang langganan gitu dan ada metrik lainnya yang mendukung bisnis (Done)
- refactor untuk dashboard owner itu pakai sidebar biar sama seperti page tenant (Done)
- fix harga di pricing page 17% hemat untuk tahunan (Done)
- kirim email ke admin sales di contact us page (Done)
- refactor untuk queue log page itu hanya bisa diakses oleh platform owner (Done)

<!-- TODO: -->

- fix dark mode di landing page
- buatkan landing pagenya biar SEO friendly dan mudah di crawl dengan domain my.id dan biar bisa paling atas di google
- kirim email terimakasih dan laporan untuk yang langganan platformnya
- pastikan coverage test diatas 80 persen
- isikan docblock untuk tiap method di code backendnya biar gampang nanti baca codenya
- tambahin lagi beberapa laporan atau fitur di laporan penjualan
- bikin API untuk mobile app

<!-- AI Prompt -->

- tolong cek codebase ku ini secara menyeluruh, cek untuk issue yang ada entah itu dari security, performa, best practice laravel dan sebagainya cek juga di bagian frontend pastikan semua sesuai best practice dan sebagainya. gunakan skill yang kamu perlukan untuk cek issue yang mungkin ada di codebase ku ini dan buatkan ISSUE.md di root folder untuk issue tersebut. gunakan juga mcp laravel untuk mengecek issue lebih mendalam. pastikan jalankan dengan pararel untuk mempercepat audit

- pastikan sesuai dengan best practice laravel dan gunakan skill yang kamu perlukan. buatkan testnya secara menyeluruh utamakan untuk performa dan kualitas kode yang baik

cek issue ini pakai gh cli
https://github.com/DewaJayon/StockEase/issues/128

cek issue tersebut apakah valid, jika valid bisa di fix langsung sesuaikan dengan best practice laravel dan buatkan testnya secara menyeluruh untuk struktur folder testnya itu adalah cerminan struktur folder app, gunakan skill yang kamu perlukan untuk menyelesaikan masalah tersebut. utamakan untuk performa dan kualitas kode yang baik. pastikan frontend dan backendnya compatible jika ada perubahan yang perlu berubah frontendnya itu bisa di fix langsung. close issue tersebut ketika sudah selesai

tolong improve testku ini biar minimal 80 persen dong aku jalaninnya pakai php artisan test --compact --coverage untuk melihat coverage dari testnya, untuk struktur folder testnya itu cerminan dari struktur folder app nya, berikut hasil dari coverage testku tolong di improve lagi dan gunakan skill yang kamu butuhkan pakai bash aja jangan powershell

push branch develop ini ke github dan buatkan PR nya. jangan pakai 'git add .' melainkan commit messagenya sesuai dengan code yang berubah. untuk yang aku kerjakan yaitu

- untuk UMKM itu 50 ribu aja perbulan tidak ada diskon untuk tahunan (Done)
- untuk ada dashboard lain untuk platform ku ini biar bisa lihat berapa user yang langganan gitu dan ada metrik lainnya yang mendukung bisnis (Done)
- refactor untuk dashboard owner itu pakai sidebar biar sama seperti page tenant (Done)
- fix harga di pricing page 17% hemat untuk tahunan (Done)
- kirim email ke admin sales di contact us page (Done)
- refactor untuk queue log page itu hanya bisa diakses oleh platform owner (Done)

pastikan semuanya sudah di commit dan di push baru buatkan PR nya pakai gh cli. pakai conventional commit dengan bahasa inggris. jangan pakai emoji
