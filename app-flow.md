# StockEase — Application Flow

## Arsitektur Multi-Tenant

StockEase adalah aplikasi **SaaS multi-tenant** di mana setiap organisasi (Company) memiliki data terisolasi, subscription plan sendiri, dan karyawan dengan role berbeda.

```
┌──────────────────────────────────────────────────────────────┐
│  Platform StockEase (stockease.test)                         │
│                                                              │
│  ┌─────────────────────┐  ┌─────────────────────┐           │
│  │ Company A           │  │ Company B           │           │
│  │ "Toko Makmur Jaya"  │  │ "Warung Berkah"     │           │
│  │ Plan: Profesional   │  │ Plan: Pemula        │           │
│  │ ├── Budi (owner)    │  │ ├── Ani (owner)     │           │
│  │ ├── Dodi (kasir)    │  │ └── Eko (kasir)     │           │
│  │ ├── Sari (warehouse)│  │                     │           │
│  │ └── Data: 500 prod  │  │ Data: 50 produk     │           │
│  └─────────────────────┘  └─────────────────────┘           │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐    │
│  │ Platform Admin (company_id = NULL)                  │    │
│  │ - superadmin@dewajayon.my.id                        │    │
│  │ - Lihat semua company & data                        │    │
│  │ - Manage plans & subscription                       │    │
│  └─────────────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────────────┘
```

---

## 1. Registration Flow (User Baru)

```
Browser: /register
  │
  ├── GET  → Tampilkan form Register (nama_toko, nama, email, password)
  │
  └── POST → RegisteredUserController@store
              │
              └── DB::transaction
                    ├── 1. Buat Company (name="Toko Makmur", slug, is_active=true)
                    ├── 2. Buat User (name, email, password, company_id)
                    ├── 3. Update Company.owner_id = user.id
                    ├── 4. Assign role "super_admin" via Spatie
                    └── 5. Buat Subscription Pemula (Free, status=active)
              │
              └── Auth::login(user) → redirect /dashboard
```

**Hasil:** User langsung bisa pakai aplikasi dengan batasan Pemula (100 produk, 3 user, 1 gudang).

---

## 2. Login Flow

```
Browser: /login
  │
  ├── GET  → Tampilkan form Login (email, password)
  │
  └── POST → AuthenticatedSessionController@store
              │
              ├── Validasi kredensial
              ├── Rate limiting (5 attempts)
              └── Session regenerate → redirect /dashboard
```

**Identifikasi Tenant:**

Setelah login, middleware `InitializeTenancyByRequestData` menginisialisasi konteks tenant berdasarkan `company_id` user. Semua query Eloquent otomatis ter-filter ke `company_id` ini via global scope `BelongsToTenant`.

```
Login as Budi (company_id=1)
  → Product::all() → otomatis WHERE company_id = 1
  → Hanya lihat produk Toko Makmur Jaya
```

---

## 3. Dashboard Flow

```
Browser: /dashboard
  │
  ├── Role: super_admin / admin / warehouse
  │     ├── DashboardService@adminData() / warehouseData()
  │     ├── Sales summary (today, month)
  │     ├── Low stock alerts
  │     ├── Activity history (cached 2 menit)
  │     └── Charts (weekly sales, price updates)
  │
  └── Role: cashier
        ├── DashboardService@cashierData()
        ├── Today's income
        ├── Weekly transactions
        ├── Best selling product
        └── Recent transactions
```

---

## 4. POS (Point of Sale) Flow

```
Browser: /pos
  │
  ├── GET  → PosController@index
  │          ├── Load categories, products (paginated 12)
  │          ├── Load active promotions (cached)
  │          ├── Load warehouses + active warehouse
  │          ├── Load existing cart (session-based)
  │          └── Check active shift
  │
  ├── Add to Cart
  │     ├── Click produk → POST /pos/add-to-cart
  │     ├── Scan barcode → POST /pos/add-to-cart-barcode
  │     └── Cart stored di database (Sale dengan status=draft)
  │
  ├── Cart Operations
  │     ├── Change qty (±) → PATCH /pos/change-qty (dengan race-condition guard)
  │     ├── Remove item → DELETE /pos/remove-from-cart
  │     └── Clear cart → DELETE /pos/empty-cart
  │
  └── Checkout
        ├── Cash Payment → PUT /pos/checkout
        │     ├── Validasi paid >= total
        │     ├── Create Sale (status=completed)
        │     ├── Decrement stock
        │     └── Record PaymentTransaction
        │
        └── QRIS Payment → Midtrans Snap
              ├── POST /pos/qris-token → get snap_token
              ├── window.snap.pay() → Midtrans popup
              ├── onSuccess → PUT /pos/checkout (complete sale)
              └── onError → toast error
```

---

## 5. Subscription & Upgrade Flow

### 5.1 Lihat Status Langganan

```
Browser: /subscription
  │
  └── GET → SubscriptionController@index
            ├── Current subscription (plan, status, dates, limits)
            ├── Available plans (Pemula, Profesional, Enterprise)
            └── Billing toggle (monthly vs annual)
```

### 5.2 Upgrade ke Plan Berbayar

```
Browser: /subscription → klik "Mulai Trial" / "Upgrade"
  │
  └── POST /subscription/upgrade
        │
        ├── Plan = Pemula (Free)
        │     └── createTrial(company, plan) → status=active → reload
        │
        └── Plan = Profesional / Enterprise
              │
              ├── createTrial(company, plan) → status=trialing (14 hari)
              │     └── Response: "Trial 14 hari dimulai!"
              │
              └── Jika ingin langsung bayar:
                    ├── createInvoice(subscription) → amount
                    ├── generateMidtransOrderId → "SUB-{id}-{timestamp}"
                    ├── createSnapToken → Midtrans Snap API
                    └── Response: { snap_token, order_id }
                          │
                          └── Browser: window.snap.pay(snap_token)
                                ├── onSuccess → reload (subscription active)
                                ├── onPending → toast "menunggu"
                                └── onError → toast error
```

### 5.3 Midtrans Webhook (Pembayaran Subscription)

```
Midtrans Server → POST /payment/midtrans/notification
  │
  ├── order_id starts with "SUB-"?
  │     ├── YES → handleSubscriptionNotification()
  │     │         ├── settlement/capture → invoice paid + activateSubscription()
  │     │         └── deny/cancel/expire → invoice failed
  │     │
  │     └── NO  → handle regular POS notification
  │
  └── Response: 200 OK
```

### 5.4 Pembatalan Langganan

```
Browser: /subscription → klik "Batalkan Langganan"
  │
  └── POST /subscription/{id}/cancel
        └── cancelSubscription() → status=canceled
              └── User tetap bisa akses sampai ends_at (grace period)
```

### 5.5 Auto-Downgrade (Scheduler)

```
CRON: php artisan schedule:run (daily)
  │
  └── subscription:downgrade-expired
        │
        ├── Cari subscription active dengan ends_at < now
        │     └── expireSubscription() → status=expired
        │           └── assignFreeSubscription() → Pemula baru
        │
        └── Cari subscription trialing dengan trial_ends_at < now
              └── expireSubscription() → status=expired
                    └── assignFreeSubscription() → Pemula baru
```

---

## 6. Plan Limit Enforcement

### 6.1 Middleware Flow

Setiap kali user mencoba membuat resource baru:

```
POST /products  ──→  CheckSubscriptionLimit(product)
                      │
                      ├── user.company_id = NULL? → Lolos (platform admin)
                      ├── user.hasRole(super_admin)? → Lolos
                      ├── company.currentPlan() = null? → assignFreeSubscription()
                      ├── Product::count() >= plan.max_products? → 403 "Limit tercapai"
                      └── OK → lanjut
```

### 6.2 Plan Limits

| Resource | Pemula | Profesional | Enterprise |
|----------|--------|-------------|------------|
| Produk | 100 | 1.000 | Unlimited |
| User | 3 | 10 | Unlimited |
| Gudang | 1 | 3 | Unlimited |

---

## 7. User & Role Management (Company Internal)

### 7.1 Owner Tambah Karyawan

```
Browser: /users → "Tambah User"
  │
  └── POST /users
        ├── CheckSubscriptionLimit(user) → cek max_users plan
        ├── User::create(name, email, password, company_id=owner.company_id)
        └── Assign role (admin / cashier / warehouse)
```

### 7.2 Karyawan Login

```
Login as Dodi (company_id=1, role=kasir)
  │
  ├── BelongsToTenant global scope aktif → WHERE company_id = 1
  ├── Role middleware → akses POS, sale history, file manager
  └── Hanya lihat data Toko Makmur Jaya
```

### 7.3 Role Permissions

| Role | Akses Utama |
|------|------------|
| super_admin (company) | Semua fitur dalam company-nya |
| admin | User mgmt, produk, supplier, promosi, laporan |
| cashier | POS, sale history, retur, file manager |
| warehouse | Produk, stok, gudang, transfer stok, purchase |

---

## 8. Data Isolation (BelongsToTenant)

### 8.1 Global Scope

Semua model data menggunakan trait `BelongsToTenant` dari package `stancl/tenancy`:

```php
class Product extends Model
{
    use BelongsToTenant; // otomatis WHERE company_id = current_tenant_id
}
```

### 8.2 Query Examples

| Context | Query | Hasil |
|---------|-------|-------|
| Budi login (company_id=1) | `Product::all()` | Hanya produk company 1 |
| Platform admin (company_id=NULL) | `Product::all()` | Semua produk |
| Platform admin | `Product::withoutTenancy()->get()` | Semua produk (explicit) |
| Budi login | `Sale::where('status', 'completed')->sum('total')` | Total sales company 1 |

### 8.3 Auto-Fill on Create

Package `BelongsToTenant` juga auto-fill `company_id` saat create:

```php
$product = Product::create(['name' => 'Kopi', 'price' => 15000]);
// otomatis: company_id = current tenant id
```

---

## 9. Platform Admin (company_id = NULL)

```
Login as superadmin@dewajayon.my.id (company_id = NULL)
  │
  ├── Tidak ada global scope → lihat SEMUA data
  ├── Akses semua halaman admin
  ├── Manage plans (harga, limit)
  ├── Manage semua subscription
  └── Manage semua user (lintas company)
```

---

## 10. Cache Strategy

| Data | Cache Key | TTL | Tempat |
|------|-----------|-----|--------|
| Activity log events | `activity_log_events` | 6 jam | ActivityLogController |
| Activity log names | `activity_log_names` | 6 jam | ActivityLogController |
| Dashboard activities | `dashboard_activity_history` | 2 menit | DashboardService |
| POS promotions | (query optimized dengan select()) | - | PosController |

---

## 11. Complete Routing Table

```
GET    /                              Landing page
GET    /register                      Form registrasi (guest)
POST   /register                      Proses registrasi (guest)
GET    /login                         Form login (guest)
POST   /login                         Proses login (guest)
POST   /logout                        Logout (auth)
PUT    /password                      Update password (auth)

GET    /dashboard                     Dashboard (auth)
GET    /pos                           POS page (auth)
POST   /pos/add-to-cart               Tambah item ke cart
PATCH  /pos/change-qty                Ubah qty cart item
DELETE /pos/remove-from-cart          Hapus item dari cart
DELETE /pos/empty-cart                Kosongkan cart
PUT    /pos/checkout                  Checkout sale
POST   /pos/send-invoice              Kirim invoice email

GET    /subscription                  Halaman langganan (auth)
POST   /subscription/upgrade          Upgrade plan (auth)
POST   /subscription/{id}/cancel      Batalkan langganan (auth)

GET    /admin/subscriptions           Admin: list subscription
GET    /admin/subscriptions/{id}      Admin: detail subscription
POST   /admin/subscriptions/assign    Admin: assign subscription
PATCH  /admin/subscriptions/{id}      Admin: update subscription
GET    /admin/plans                   Admin: list plans
PATCH  /admin/plans/{id}              Admin: update plan

POST   /products                      Tambah produk [subscription.limit:product]
POST   /users                         Tambah user [subscription.limit:user]
POST   /warehouse                     Tambah gudang [subscription.limit:warehouse]
POST   /payment/midtrans/notification Midtrans webhook (POS + Subscription)
```

---

## 12. Scheduler

```
php artisan schedule:run (setiap menit via CRON)
  │
  ├── subscription:downgrade-expired (daily)
  │     └── Cek & downgrade subscription expired/trial habis
  │
  └── queue:work (existing)
        └── Process background jobs
```

---

## 13. Complete Flow Diagram

```
                          ┌─────────────────┐
                          │  User buka URL  │
                          │ stockease.test  │
                          └────────┬────────┘
                                   │
                          ┌────────▼────────┐
                          │   Sudah login?  │
                          └───┬─────────┬───┘
                              │ NO      │ YES
                    ┌─────────▼──┐  ┌───▼──────────┐
                    │ /login     │  │ Ada company?  │
                    │ /register  │  └───┬────────┬──┘
                    └────────────┘      │ NO     │ YES
                              ┌─────────▼──┐ ┌───▼──────────────────┐
                              │ /register  │ │ InitializeTenancy    │
                              │ Buat       │ │ (BelongsToTenant     │
                              │ Company +  │ │  scope aktif)        │
                              │ User + Sub │ └───┬──────────────────┘
                              └────────────┘     │
                                          ┌──────▼──────┐
                                          │  Dashboard  │
                                          └──────┬──────┘
                                                 │
                    ┌────────────────────────────┼────────────────────────────┐
                    │                            │                            │
           ┌────────▼────────┐          ┌───────▼───────┐          ┌─────────▼────────┐
           │  POS / Produk   │          │  Subscription  │          │  Admin Panel     │
           │  Sales / Stok   │          │  /subscription │          │  /admin/*        │
           └────────┬────────┘          └───────┬───────┘          └─────────┬────────┘
                    │                            │                            │
                    │                   ┌────────▼────────┐                   │
                    │                   │ Upgrade Plan    │                   │
                    │                   │ → Trial 14 hari │                   │
                    │                   │ → Midtrans Snap │                   │
                    │                   │ → Webhook       │                   │
                    │                   │ → Active/Settle │                   │
                    │                   └────────┬────────┘                   │
                    │                            │                            │
                    │                   ┌────────▼────────┐                   │
                    │                   │ Scheduler Daily │                   │
                    │                   │ → Downgrade     │                   │
                    │                   │   Expired Subs  │                   │
                    │                   └─────────────────┘                   │
                    └────────────────────────────┼────────────────────────────┘
                                                 │
                                          ┌──────▼──────┐
                                          │ Semua data  │
                                          │ terisolasi  │
                                          │ per company │
                                          └─────────────┘
```
