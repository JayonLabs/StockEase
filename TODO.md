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

<!-- TODO: -->

- fix pencarian di stock-transfer
- fix tabel di page penjualan bagian kasir itu rata kirikan
- fix query duplikat di page stock opname atau https://stockease.test/stock-adjustment
- rafactor controller trash untuk cek yang soft delete karena ada penambahan
- buatkan landing page
- cek issue yang ada suruh claude dan buatkan ISSUE.md
- tambahin lagi beberapa laporan atau fitur di laporan penjualan
- fix github CD ke cpanel
- bikin API untuk mobile app
