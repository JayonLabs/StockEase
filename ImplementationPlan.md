# Implementasi Fitur Subscription & Langganan

## ⚠️ ARSITEKTUR MULTI-TENANT — Prasyarat Utama

### Masalah Saat Ini

Aplikasi StockEase saat ini adalah **single-tenant** (semua user berbagi data yang sama):
- Semua user melihat produk yang sama
- Semua user melihat gudang yang sama
- Tidak ada isolasi data antar organisasi

Ini **tidak cocok** untuk model subscription di mana setiap pelanggan adalah organisasi terpisah dengan data terisolasi, karyawan sendiri, dan batasan plan sendiri.

### Solusi: Multi-Tenant dengan Shared Database + `company_id` Scoping

Pendekatan: **Satu database, schema shared, semua tabel punya `company_id`**.

```
┌──────────────────────────────────────────────────────────┐
│  Database: stockease                                     │
│                                                          │
│  Company A (Warung Jaya - Profesional)                   │
│  ├── Products: 500 items                                 │
│  ├── Users: 5 (1 owner, 2 admin, 2 kasir)               │
│  ├── Warehouses: 2                                       │
│  ├── Sales: ...                                          │
│  └── ALL company_id = 1                                  │
│                                                          │
│  Company B (Toko Makmur - Pemula)                        │
│  ├── Products: 50 items                                  │
│  ├── Users: 2 (1 owner, 1 kasir)                        │
│  ├── Warehouses: 1                                       │
│  ├── Sales: ...                                          │
│  └── ALL company_id = 2                                  │
│                                                          │
│  Company C (PT Besar - Enterprise)                       │
│  ├── Products: unlimited                                 │
│  ├── Users: 25                                           │
│  ├── Warehouses: 5                                       │
│  └── ALL company_id = 3                                  │
└──────────────────────────────────────────────────────────┘
```

Subscription plan **per company**, bukan per user. Limit juga **per company**.

---

## Gambaran Umum

Sistem subscription berbasis plan untuk aplikasi StockEase dengan 3 tier: **Pemula** (Free), **Profesional** (Rp 299k/bulan), dan **Enterprise** (Rp 999k/bulan). Menggunakan Midtrans sebagai payment gateway (sudah terintegrasi untuk POS), dengan manajemen subscription oleh admin dan self-service upgrade oleh user.

### Pendekatan

| Aspek | Keputusan |
|-------|-----------|
| Payment Gateway | Midtrans (Snap) — satu provider untuk POS & subscription |
| Feature Gating | Batasan jumlah (produk/user/warehouse) per plan |
| Existing Users | Auto-assign plan Pemula (Free) saat migrasi |
| Trial Period | 14 hari untuk Profesional & Enterprise |
| Renewal Handling | Manual perpanjangan + scheduled job cek expired |

---

## Arsitektur Database

### Tabel `companies` (BARU — multi-tenant)

```sql
CREATE TABLE companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    owner_id BIGINT UNSIGNED NOT NULL COMMENT 'User yang subscribe (super_admin company)',
    address VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**Semua tabel data yang sudah ada** (20+ tabel) perlu ditambah `company_id`:

| Tabel | Tambah Kolom |
|-------|-------------|
| `users` | `company_id BIGINT UNSIGNED NULL` (null = superadmin global / platform) |
| `categories` | `company_id` |
| `units` | `company_id` |
| `suppliers` | `company_id` |
| `products` | `company_id` |
| `warehouses` | `company_id` |
| `warehouse_product` | `company_id` |
| `sales` | `company_id` |
| `sale_items` | `company_id` |
| `sale_returns` | `company_id` |
| `sale_return_items` | `company_id` |
| `sale_emails` | `company_id` |
| `purchases` | `company_id` |
| `purchase_items` | `company_id` |
| `stock_logs` | `company_id` |
| `stock_adjustments` | `company_id` |
| `stock_transfers` | `company_id` |
| `shifts` | `company_id` |
| `promotions` | `company_id` |
| `payment_transactions` | `company_id` |
| `price_histories` | `company_id` |

Index: `INDEX idx_company_id (company_id)` di setiap tabel.

### Tabel `plans`

```sql
CREATE TABLE plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    price_monthly DECIMAL(12,2) NOT NULL DEFAULT 0,
    price_annual DECIMAL(12,2) NOT NULL DEFAULT 0,
    max_products INT UNSIGNED NULL COMMENT 'null = unlimited',
    max_users INT UNSIGNED NULL,
    max_warehouses INT UNSIGNED NULL,
    max_shifts INT UNSIGNED NULL,
    features JSON NULL COMMENT '{"barcode":true,"reports":true,"multi_role":true}',
    trial_days INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Seed Data:**

| slug | name | price_monthly | price_annual | max_products | max_users | max_warehouses | trial_days |
|------|------|---------------|--------------|-------------|-----------|----------------|------------|
| pemula | Pemula | 0 | 0 | 100 | 3 | 1 | 0 |
| profesional | Profesional | 299000 | 249000 | 1000 | 10 | 3 | 14 |
| enterprise | Enterprise | 999000 | 849000 | null | null | null | 14 |

### Tabel `subscriptions` (Subscription per COMPANY, bukan per user)

```sql
CREATE TABLE subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    status ENUM('active','canceled','expired','trialing','pending') NOT NULL DEFAULT 'pending',
    billing_cycle ENUM('monthly','annual') NOT NULL DEFAULT 'monthly',
    starts_at TIMESTAMP NULL,
    ends_at TIMESTAMP NULL,
    trial_ends_at TIMESTAMP NULL,
    canceled_at TIMESTAMP NULL,
    auto_renew TINYINT(1) NOT NULL DEFAULT 0,
    payment_method VARCHAR(50) NULL,
    payment_token VARCHAR(255) NULL,
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE RESTRICT,
    INDEX idx_status (status),
    INDEX idx_ends_at (ends_at),
    INDEX idx_company_status (company_id, status)
);
```

### Tabel `subscription_invoices`

```sql
CREATE TABLE subscription_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('pending','paid','failed','expired','refunded') NOT NULL DEFAULT 'pending',
    midtrans_order_id VARCHAR(100) UNIQUE NULL,
    midtrans_transaction_id VARCHAR(100) NULL,
    midtrans_payment_type VARCHAR(50) NULL,
    midtrans_raw_response JSON NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

---

## Models

### `app/Models/Company.php` (BARU)

```php
class Company extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'owner_id', 'address', 'phone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscription()
            ->whereIn('status', ['active', 'trialing'])
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->first();
    }
}
```

### `app/Models/Traits/BelongsToTenant.php`

**Tidak perlu dibuat.** Gunakan trait dari package: `Stancl\Tenancy\Database\Concerns\BelongsToTenant`

Semua model data (Product, Category, Warehouse, Sale, Purchase, dll) cukup tambahkan:

```php
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Product extends Model
{
    use BelongsToTenant; // ← cukup ini, scope + auto-fill handled by package
    // ...
}
```

Package otomatis:
- **Global scope** — filter query ke `company_id` user yang sedang login
- **Auto-fill** — set `company_id` saat create (tanpa perlu manual set)
- **withoutTenancy()** — untuk query lintas company (platform admin)

### Update `app/Models/User.php`

```php
// Tambah kolom company_id di migration
// Tambah di $fillable: 'company_id'

public function company(): BelongsTo
{
    return $this->belongsTo(Company::class);
}

public function ownedCompany(): HasOne
{
    return $this->hasOne(Company::class, 'owner_id');
}

public function isPlatformAdmin(): bool
{
    return $this->company_id === null; // user tanpa company = admin platform
}

public function subscriptions(): HasMany
{
    return $this->hasMany(Subscription::class);
}
```

### `app/Models/Plan.php`

```php
class Plan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description',
        'price_monthly', 'price_annual',
        'max_products', 'max_users', 'max_warehouses', 'max_shifts',
        'features', 'trial_days', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_annual' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
            'trial_days' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeActive($query): void
    {
        $query->where('is_active', true)->orderBy('sort_order');
    }

    public function isFree(): bool
    {
        return (float) $this->price_monthly === 0.0
            && (float) $this->price_annual === 0.0;
    }
}
```

### `app/Models/Subscription.php`

```php
class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'plan_id', 'status', 'billing_cycle',
        'starts_at', 'ends_at', 'trial_ends_at', 'canceled_at',
        'auto_renew', 'payment_method', 'payment_token', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'canceled_at' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    public function isTrialing(): bool
    {
        return $this->status === 'trialing'
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    public function isOnGracePeriod(): bool
    {
        return $this->status === 'canceled'
            && $this->ends_at !== null
            && $this->ends_at->isFuture();
    }
}
```

### `app/Models/SubscriptionInvoice.php`

```php
class SubscriptionInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id', 'user_id', 'amount', 'status',
        'midtrans_order_id', 'midtrans_transaction_id',
        'midtrans_payment_type', 'midtrans_raw_response', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'midtrans_raw_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### Update `app/Models/User.php`

Tambahkan relationship dan helper:

```php
public function subscriptions(): HasMany
{
    return $this->hasMany(Subscription::class);
}

public function subscriptionInvoices(): HasMany
{
    return $this->hasMany(SubscriptionInvoice::class);
}

public function activeSubscription(): ?Subscription
{
    return $this->subscriptions()
        ->whereIn('status', ['active', 'trialing'])
        ->where(function ($q) {
            $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
        })
        ->latest()
        ->first();
}

public function currentPlan(): ?Plan
{
    return $this->activeSubscription()?->plan;
}
```

---

## Services

### `app/Services/Subscription/SubscriptionService.php`

```php
class SubscriptionService
{
    public function createTrial(
        User $user,
        Plan $plan,
        string $billingCycle = 'monthly'
    ): Subscription {
        $existingActive = $user->activeSubscription();
        if ($existingActive) {
            throw new RuntimeException('User sudah memiliki subscription aktif.');
        }

        $subscription = new Subscription([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => $plan->trial_days > 0 && !$plan->isFree() ? 'trialing' : 'active',
            'billing_cycle' => $billingCycle,
            'trial_ends_at' => $plan->trial_days > 0 && !$plan->isFree()
                ? now()->addDays($plan->trial_days) : null,
            'starts_at' => now(),
            'ends_at' => $plan->isFree() ? null : now()->addDays(
                $billingCycle === 'annual' ? 365 : 30
            ),
        ]);

        $subscription->save();

        activity()
            ->performedOn($subscription)
            ->withProperties(['plan' => $plan->name, 'cycle' => $billingCycle])
            ->log('subscription_created');

        return $subscription;
    }

    public function assignFreeSubscription(User $user): Subscription
    {
        $plan = Plan::where('slug', 'pemula')->firstOrFail();
        return $this->createTrial($user, $plan);
    }

    public function createInvoice(Subscription $subscription): SubscriptionInvoice
    {
        $plan = $subscription->plan;
        $amount = $subscription->billing_cycle === 'annual'
            ? $plan->price_annual
            : $plan->price_monthly;

        return SubscriptionInvoice::create([
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'amount' => (float) $amount,
            'status' => 'pending',
        ]);
    }

    public function generateMidtransOrderId(SubscriptionInvoice $invoice): string
    {
        return 'SUB-' . $invoice->id . '-' . now()->timestamp;
    }

    public function activateSubscription(
        Subscription $subscription,
        string $billingCycle = null
    ): void {
        $cycle = $billingCycle ?? $subscription->billing_cycle;
        $cycleDays = $cycle === 'annual' ? 365 : 30;

        $subscription->update([
            'status' => 'active',
            'starts_at' => $subscription->starts_at ?? now(),
            'ends_at' => now()->addDays($cycleDays),
            'trial_ends_at' => null,
        ]);
    }

    public function cancelSubscription(Subscription $subscription): void
    {
        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);
    }

    public function expireSubscription(Subscription $subscription): void
    {
        $subscription->update(['status' => 'expired']);
        $this->assignFreeSubscription($subscription->user);
    }

    public function downgradeExpiredSubscriptions(): int
    {
        $count = 0;

        Subscription::where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->each(function (Subscription $sub) use (&$count) {
                $this->expireSubscription($sub);
                $count++;
            });

        Subscription::where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->each(function (Subscription $sub) use (&$count) {
                $this->expireSubscription($sub);
                $count++;
            });

        return $count;
    }
}
```

### `app/Services/Subscription/PlanLimitService.php`

```php
class PlanLimitService
{
    public function canAddProduct(?Company $company = null): bool
    {
        $company ??= Auth::user()?->company;
        $plan = $company?->activeSubscription()?->plan;
        if (!$plan || $plan->max_products === null) {
            return true;
        }
        return Product::count() < $plan->max_products; // Global scope auto-filter by company_id
    }

    public function canAddUser(?Company $company = null): bool
    {
        $company ??= Auth::user()?->company;
        $plan = $company?->activeSubscription()?->plan;
        if (!$plan || $plan->max_users === null) {
            return true;
        }
        return User::where('company_id', $company->id)->count() < $plan->max_users;
    }

    public function canAddWarehouse(?Company $company = null): bool
    {
        $company ??= Auth::user()?->company;
        $plan = $company?->activeSubscription()?->plan;
        if (!$plan || $plan->max_warehouses === null) {
            return true;
        }
        return Warehouse::count() < $plan->max_warehouses; // Global scope auto-filter
    }
}
```
Catatan: `Product::count()` dan `Warehouse::count()` otomatis ter-filter ke company via global scope `BelongsToCompany`. `User::count()` tidak pakai global scope (agar platform admin bisa lihat semua), jadi kita filter manual dengan `where('company_id', ...)`.

---

## Middleware

### `app/Http/Middleware/CheckSubscriptionLimit.php`

```php
class CheckSubscriptionLimit
{
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        $plan = $user->currentPlan();

        if (!$plan) {
            app(SubscriptionService::class)->assignFreeSubscription($user);
            $plan = $user->fresh()->currentPlan();
        }

        $exceeded = match ($resource) {
            'product' => $plan->max_products !== null
                && Product::count() >= $plan->max_products,
            'user' => $plan->max_users !== null
                && User::count() >= $plan->max_users,
            'warehouse' => $plan->max_warehouses !== null
                && Warehouse::count() >= $plan->max_warehouses,
            default => false,
        };

        if ($exceeded) {
            $message = match ($resource) {
                'product' => 'Batas maksimal produk untuk plan '
                    . $plan->name . ' telah tercapai. Upgrade plan Anda.',
                'user' => 'Batas maksimal user untuk plan '
                    . $plan->name . ' telah tercapai. Upgrade plan Anda.',
                'warehouse' => 'Batas maksimal gudang untuk plan '
                    . $plan->name . ' telah tercapai. Upgrade plan Anda.',
                default => 'Limit plan tercapai.',
            };

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return back()->with('error', $message);
        }

        return $next($request);
    }
}
```

Registrasi alias di `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'role' => RoleMiddleware::class,
        'subscription.limit' => CheckSubscriptionLimit::class,
    ]);
})
```

---

## Controllers

### `app/Http/Controllers/Subscription/SubscriptionController.php`

```php
class SubscriptionController extends Controller
{
    public function index(): Response
    {
        $subscription = Auth::user()->activeSubscription();
        $invoices = Auth::user()
            ->subscriptionInvoices()
            ->latest()
            ->take(10)
            ->get();
        $plans = Plan::active()->get();

        return Inertia::render('Subscription/Index', [
            'currentSubscription' => $subscription?->load('plan'),
            'invoices' => $invoices,
            'plans' => $plans,
        ]);
    }

    public function upgrade(SubscriptionUpgradeRequest $request): JsonResponse
    {
        $plan = Plan::findOrFail($request->plan_id);
        $user = Auth::user();
        $billingCycle = $request->billing_cycle ?? 'monthly';

        if ($plan->isFree()) {
            $subscription = app(SubscriptionService::class)
                ->createTrial($user, $plan);
            return response()->json([
                'message' => 'Berlangganan plan Pemula.',
                'subscription' => $subscription,
            ]);
        }

        $subscription = app(SubscriptionService::class)
            ->createTrial($user, $plan, $billingCycle);

        if ($subscription->status === 'trialing') {
            return response()->json([
                'message' => 'Trial 14 hari dimulai!',
                'subscription' => $subscription,
            ]);
        }

        $invoice = app(SubscriptionService::class)
            ->createInvoice($subscription);
        $orderId = app(SubscriptionService::class)
            ->generateMidtransOrderId($invoice);
        $snapToken = app(PaymentService::class)
            ->createSnapTokenForSubscription($invoice, $orderId, $user);

        $invoice->update(['midtrans_order_id' => $orderId]);

        return response()->json([
            'snap_token' => $snapToken,
            'order_id' => $orderId,
        ]);
    }

    public function cancel(Subscription $subscription): RedirectResponse
    {
        Gate::authorize('cancel', $subscription);
        app(SubscriptionService::class)->cancelSubscription($subscription);
        return back()->with('success', 'Subscription dibatalkan.');
    }
}
```

### `app/Http/Controllers/Admin/AdminSubscriptionController.php`

```php
class AdminSubscriptionController extends Controller
{
    public function index(Request $request): Response
    {
        $subscriptions = Subscription::with(['user', 'plan'])
            ->when($request->status,
                fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(25);

        return Inertia::render('Admin/Subscriptions/Index', [
            'subscriptions' => $subscriptions,
            'filters' => $request->only('status'),
        ]);
    }

    public function show(Subscription $subscription): Response
    {
        return Inertia::render('Admin/Subscriptions/Show', [
            'subscription' => $subscription->load(['user', 'plan', 'invoices']),
        ]);
    }

    public function assign(AdminAssignSubscriptionRequest $request): RedirectResponse
    {
        $user = User::findOrFail($request->user_id);
        $plan = Plan::findOrFail($request->plan_id);
        app(SubscriptionService::class)->createTrial($user, $plan);

        return back()->with('success', 'Subscription berhasil di-assign.');
    }

    public function update(
        AdminUpdateSubscriptionRequest $request,
        Subscription $subscription
    ): RedirectResponse {
        $subscription->update($request->validated());
        return back()->with('success', 'Subscription diperbarui.');
    }
}
```

### `app/Http/Controllers/Admin/PlanController.php`

```php
class PlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Plans/Index', [
            'plans' => Plan::orderBy('sort_order')->get(),
        ]);
    }

    public function update(
        AdminUpdatePlanRequest $request,
        Plan $plan
    ): RedirectResponse {
        $plan->update($request->validated());
        return back()->with('success', 'Plan diperbarui.');
    }
}
```

---

## Payment Gateway — Midtrans

### Update `app/Services/Payment/PaymentService.php`

```php
public function createSnapTokenForSubscription(
    SubscriptionInvoice $invoice,
    string $orderId,
    User $user
): string {
    $params = [
        'transaction_details' => [
            'order_id' => $orderId,
            'gross_amount' => (int) ($invoice->amount),
        ],
        'customer_details' => [
            'first_name' => $user->name,
            'email' => $user->email,
        ],
        'item_details' => [[
            'id' => 'SUBSCRIPTION-' . $invoice->subscription_id,
            'price' => (int) ($invoice->amount),
            'quantity' => 1,
            'name' => 'Langganan StockEase',
        ]],
    ];

    return Snap::getSnapToken($params);
}
```

### Update `app/Http/Controllers/Payment/PaymentController.php`

Tambahkan di method `midtransNotification()`:

```php
if (str_starts_with($orderId, 'SUB-')) {
    $this->handleSubscriptionNotification($notification);
    return;
}
```

```php
private function handleSubscriptionNotification(object $notification): void
{
    $invoice = SubscriptionInvoice::where(
        'midtrans_order_id',
        $notification->order_id
    )->firstOrFail();

    $transactionStatus = $notification->transaction_status;

    if (in_array($transactionStatus, ['settlement', 'capture'])) {
        $invoice->update([
            'status' => 'paid',
            'midtrans_transaction_id' => $notification->transaction_id,
            'midtrans_payment_type' => $notification->payment_type,
            'midtrans_raw_response' => (array) $notification,
            'paid_at' => now(),
        ]);

        app(SubscriptionService::class)->activateSubscription(
            $invoice->subscription
        );
    }

    if (in_array($transactionStatus, ['deny', 'cancel', 'expire'])) {
        $invoice->update(['status' => 'failed']);
    }
}
```

---

## Scheduled Task

### `app/Console/Commands/Subscription/DowngradeExpiredSubscriptions.php`

```php
class DowngradeExpiredSubscriptions extends Command
{
    protected $signature = 'subscription:downgrade-expired';
    protected $description = 'Downgrade expired subscriptions ke Pemula';

    public function handle(SubscriptionService $service): int
    {
        $count = $service->downgradeExpiredSubscriptions();
        $this->info("$count subscription expired telah didowngrade.");
        return Command::SUCCESS;
    }
}
```

Registrasi di `routes/console.php`:

```php
Schedule::command('subscription:downgrade-expired')->daily();
```

---

## Routes

### `routes/web/subscription.php`

```php
<?php

use App\Http\Controllers\Subscription\SubscriptionController;

Route::middleware('auth')->group(function () {
    Route::get('/subscription',
        [SubscriptionController::class, 'index'])
        ->name('subscription.index');

    Route::post('/subscription/upgrade',
        [SubscriptionController::class, 'upgrade'])
        ->name('subscription.upgrade');

    Route::post('/subscription/{subscription}/cancel',
        [SubscriptionController::class, 'cancel'])
        ->name('subscription.cancel');
});
```

Include di `routes/web.php`:

```php
require __DIR__.'/web/subscription.php';
```

### Route dengan Middleware Subscription Limit

```php
// routes/web/master.php
Route::post('/products', [ProductController::class, 'store'])
    ->middleware('subscription.limit:product')
    ->name('products.store');

// routes/web/admin.php
Route::post('/users', [UserController::class, 'store'])
    ->middleware('subscription.limit:user')
    ->name('users.store');

// routes/web/warehouse.php
Route::post('/warehouses', [WarehouseController::class, 'store'])
    ->middleware('subscription.limit:warehouse')
    ->name('warehouses.store');
```

### Admin Routes (tambahkan ke `routes/web/admin.php`)

```php
Route::prefix('subscriptions')
    ->name('subscriptions.')
    ->group(function () {
        Route::get('/',
            [AdminSubscriptionController::class, 'index'])
            ->name('index');
        Route::get('/{subscription}',
            [AdminSubscriptionController::class, 'show'])
            ->name('show');
        Route::post('/assign',
            [AdminSubscriptionController::class, 'assign'])
            ->name('assign');
        Route::patch('/{subscription}',
            [AdminSubscriptionController::class, 'update'])
            ->name('update');
    });

Route::prefix('plans')
    ->name('plans.')
    ->group(function () {
        Route::get('/',
            [PlanController::class, 'index'])
            ->name('index');
        Route::patch('/{plan}',
            [PlanController::class, 'update'])
            ->name('update');
    });
```

---

## Frontend

### Vue Pages Baru

| File | Deskripsi |
|------|-----------|
| `resources/js/Pages/Subscription/Index.vue` | User kelola subscription — lihat plan, upgrade, cancel |
| `resources/js/Pages/Admin/Subscriptions/Index.vue` | Admin — list & filter subscription |
| `resources/js/Pages/Admin/Subscriptions/Show.vue` | Admin — detail subscription + invoices |
| `resources/js/Pages/Admin/Plans/Index.vue` | Admin — edit limits & harga plans |

### Update Page Existing

| File | Perubahan |
|------|-----------|
| `resources/js/Pages/Landing/Pricing.vue` | Ganti CTA buttons: user login → redirect ke subscription page, user guest → redirect ke register |
| `resources/js/Layouts/AuthenticatedLayout.vue` | Tambah sidebar nav item "Langganan" |
| `resources/js/Components/Subscription/PlanLimitAlert.vue` | Alert banner batas limit plan mendekati maksimal |

### Update `HandleInertiaRequests.php`

Share subscription data ke semua halaman:

```php
public function share(Request $request): array
{
    $user = $request->user();

    return [
        ...parent::share($request),
        'auth' => [
            'user' => $user,
            'roles' => $user?->getRoleNames() ?? [],
            'permissions' => $user?->getAllPermissions()->pluck('name') ?? [],
            'subscription' => $user
                ? fn () => $user->activeSubscription()?->load('plan')
                : null,
        ],
    ];
}
```

---

## Registration & Onboarding Flow (Multi-Tenant)

### User Baru Daftar (Self-Service)

```
1. User isi form register (name, email, password, nama_toko)
2. System buat Company baru (slug dari nama_toko)
3. System buat User dengan role "super_admin"
4. System assign User sebagai owner Company (company.owner_id = user.id)
5. System assign User.company_id = company.id
6. System assign role super_admin ke User (via Spatie)
7. System buat Subscription Pemula (Free) untuk Company
8. User redirect ke dashboard — langsung bisa pakai
```

### User Subscribe / Upgrade

```
1. Owner klik "Upgrade" di halaman /subscription
2. System cek existing subscription Company
3. Kalau upgrade ke Profesional/Enterprise:
   a. Buat Subscription baru untuk Company (bukan user)
   b. Status: trialing (14 hari)
   c. Kalau langsung bayar: generate Midtrans Snap token
4. Midtrans webhook settlement → activate subscription
5. Company sekarang punya akses Profesional/Enterprise
```

### Owner Tambah Karyawan

```
1. Owner (super_admin company) buka User Management
2. Klik "Tambah User" → isi nama, email, password, role (admin/kasir/warehouse)
3. System buat User baru dengan company_id = company si owner
4. User baru otomatis ter-scope ke data company tersebut
5. Middleware subscription.limit:user ngecek max_users plan
```

### Karyawan Login

```
1. Karyawan login → system deteksi company_id dari user
2. Semua query otomatis ter-filter ke company_id tersebut (via Global Scope)
3. Karyawan hanya lihat data company-nya sendiri
4. Karyawan tidak bisa lihat data company lain
```

### Super Admin Platform

```
1. User dengan company_id = NULL adalah "platform admin"
2. Bisa lihat SEMUA data dari SEMUA company (global scope tidak apply)
3. Bisa manage subscription semua company
4. Bisa manage plans
5. Super admin platform berbeda dengan super_admin company
```

---

## Diagram Alur Subscription (Per Company)

```
User Register → Buat Company → Auto-assign Pemula (Free, Active, tanpa ends_at)
     │
     ├── Upgrade ke Profesional / Enterprise (oleh Company Owner)
     │       │
     │       ├── Trial 14 hari → Status: trialing, trial_ends_at = now + 14d
     │       │       │
     │       │       ├── Bayar sebelum trial habis → Midtrans Snap → Webhook → Status: active
     │       │       └── Tidak bayar → Auto-downgrade ke Pemula (via scheduler)
     │       │
     │       └── Bayar langsung → Midtrans Snap → Webhook → Status: active
     │
     ├── Subscription Active (Company)
     │       │
     │       ├── Perpanjang (bayar invoice baru) → ends_at diperpanjang
     │       ├── Cancel → Status: canceled, tetap bisa akses sampai ends_at (grace period)
     │       └── Expired (ends_at < now) → Downgrade ke Pemula via daily scheduler
     │
     ├── Owner tambah karyawan → User baru dengan company_id yang sama
     │       │
     │       └── Karyawan login → auto-scope ke data company
     │
     └── Admin Platform (company_id = NULL) → manage semua company & subscription
```

---

## Plan Limits Default

| Resource | Pemula | Profesional | Enterprise |
|----------|--------|-------------|------------|
| Products | 100 | 1,000 | Unlimited |
| Users | 3 | 10 | Unlimited |
| Warehouses | 1 | 3 | Unlimited |
| Shifts | Unlimited | Unlimited | Unlimited |

---

## Tests

Struktur test mencerminkan struktur folder `app/`:

```
tests/
├── Feature/
│   └── Subscription/
│       ├── SubscriptionControllerTest.php
│       └── Payment/
│           └── SubscriptionNotificationTest.php
│   └── Admin/
│       ├── AdminSubscriptionControllerTest.php
│       └── PlanControllerTest.php
├── Unit/
│   └── Services/
│       └── Subscription/
│           ├── SubscriptionServiceTest.php
│           └── PlanLimitServiceTest.php
```

### Test Coverage per File

| Test File | Cakupan |
|-----------|---------|
| `SubscriptionServiceTest.php` | createTrial (free + paid + trial), activateSubscription, cancelSubscription, expireSubscription + auto-downgrade, downgradeExpiredSubscriptions bulk, error existing active subscription, assignFreeSubscription |
| `PlanLimitServiceTest.php` | canAddProduct/User/Warehouse (below limit = true, at limit = false), unlimited plan (null = true), no subscription (auto Pemula), tidak terautentikasi |
| `SubscriptionControllerTest.php` | index page render, upgrade free plan, upgrade paid plan + snap token, cancel own subscription, cannot cancel others, unauthenticated redirect, trial start correct dates |
| `SubscriptionNotificationTest.php` | settlement activate subscription, deny/cancel/expire fail invoice, SUB-prefix handled, non-SUB not affected |
| `AdminSubscriptionControllerTest.php` | admin list + filter, admin detail, assign to user, update subscription, non-admin forbidden |
| `PlanControllerTest.php` | admin list, admin update price/limits, non-admin forbidden |

### Middleware Test (dalam `SubscriptionLimitMiddlewareTest.php`)

- Create product gagal ketika mencapai limit — response 403 JSON
- Create user gagal ketika mencapai limit — response 403 JSON
- Create warehouse gagal ketika mencapai limit
- Create berhasil ketika dibawah limit
- User tanpa subscription auto-assign Pemula
- Unlimited plan selalu allow create

---

## Migration Steps (Urutan Implementasi — Diperbarui untuk Multi-Tenant)

| # | Step | Command / File |
|---|------|---------------|
| 1 | Buat migration `companies` | `php artisan make:migration create_companies_table` |
| 2 | Buat migration `company_id` untuk semua tabel | 20+ migration `add_company_id_to_{table}_table` |
| 3 | Buat migration `plans` | `php artisan make:migration create_plans_table` |
| 4 | Buat migration `subscriptions` (company-based) | `php artisan make:migration create_subscriptions_table` |
| 5 | Buat migration `subscription_invoices` | `php artisan make:migration create_subscription_invoices_table` |
| 6 | Buat seeders: PlanSeeder + TenantSeeder | `php artisan make:seeder PlanSeeder` |
| 7 | Run migration & seed | `php artisan migrate --seed` |
| 8 | Buat Model `Company` | Company model + relationships |
| 9 | Buat Trait `BelongsToCompany` | Global scope + auto-set company_id |
| 10 | Tambah trait ke semua model data | 20+ model tambah `use BelongsToCompany` |
| 11 | Update User model | Tambah `company_id` fillable, `company()`, `ownedCompany()`, `isPlatformAdmin()` |
| 12 | Buat Model `Plan` | Plan model |
| 13 | Buat Model `Subscription` (company-based) | Subscription model |
| 14 | Buat Model `SubscriptionInvoice` | SubscriptionInvoice model |
| 15 | Buat Services | SubscriptionService (company-based), PlanLimitService (per-company) |
| 16 | Buat Middleware | CheckSubscriptionLimit |
| 17 | Buat Controller | RegistrationController (buat Company + User + Subscription) |
| 18 | Buat Controllers | SubscriptionController, AdminSubscriptionController, PlanController |
| 19 | Buat Form Requests | SubscriptionUpgradeRequest, dll. |
| 20 | Buat routes | `routes/web/subscription.php` + update admin + update register |
| 21 | Update Payment | PaymentService + PaymentController webhook |
| 22 | Buat Command | DowngradeExpiredSubscriptions + scheduler |
| 23 | Buat migration command | `subscription:migrate-existing-users` (bikin company per user existing + assign Pemula) |
| 24 | Buat Vue pages | Registration, Subscription, Admin pages |
| 25 | Update existing Vue | Pricing.vue, AuthenticatedLayout, HandleInertiaRequests |
| 26 | Buat tests | Unit + Feature tests mencerminkan struktur app/ |
| 27 | Run full test suite | `php artisan test --compact` |
| 28 | Run pint | `vendor/bin/pint` |

> **Total: ~28 langkah dengan signifikan lebih banyak pekerjaan dari plan original karena multi-tenancy.**

---

## Dependencies

### Dependency Baru

```bash
composer require archtechx/tenancy ^4.0
```

**`archtechx/tenancy`** (4,300+ stars, 6M+ downloads, dibuat 2019) adalah package standar industri untuk multi-tenancy di Laravel. Versi 4 (2026) mendukung Laravel 13, Inertia.js, dan Sanctum.

**Kenapa pakai ini (bukan custom trait)?**

| Aspek | Custom Trait Sendiri | `archtechx/tenancy` |
|-------|---------------------|---------------------|
| Global scope | Buat & maintain sendiri | `BelongsToTenant` trait — proven & tested |
| Auto-fill company_id | Buat sendiri | `FillsCurrentTenant` trait — auto set on create |
| Tenant identification | Buat middleware custom | 5+ metode built-in (request/path-based) |
| Scoped validation | Manual `where('company_id')` | `tenant()->unique()`, `tenant()->exists()` |
| Global queries | Manual override scope | `withoutTenancy()` |
| Tenant helper | `Auth::user()->company` | `tenant()` helper |
| Secondary models | Tidak ada | `BelongsToPrimaryModel` trait |
| Inertia integration | Build sendiri | Built-in Inertia share |
| Event system | Tidak ada | TenantCreated, TenantDeleted, dll |

### Package Existing (Tetap Dipakai)

- `midtrans/midtrans-php` — payment gateway
- `spatie/laravel-permission` — role management
- `spatie/laravel-activitylog` — audit log
- `inertiajs/inertia-laravel` + Vue — frontend
- `archtechx/tenancy` — **multi-tenancy (BARU)**

---

## FAQ — Cara Kerja Platform Subscription

### 1. Gimana kalau user order / subscribe? Flow-nya kayak apa?

**Skenario A — User baru daftar:**
```
Daftar akun → Otomatis dapat Subscription Pemula (Free) → Langsung bisa pakai aplikasi
```
Tidak ada pembayaran, tidak ada Midtrans. User langsung bisa akses dengan batasan 100 produk, 3 user, 1 gudang.

**Skenario B — User upgrade ke Profesional (trial 14 hari):**
```
Klik "Upgrade Profesional" di halaman /subscription
→ System create Subscription dengan status "trialing" (14 hari gratis)
→ User bisa langsung pakai fitur Profesional
→ Tiap hari scheduler cek: trial udah lewat belum?
  ├── Sudah bayar? → status jadi "active", lanjut Profesional
  └── Belum bayar? → auto-downgrade ke Pemula
```

**Skenario C — User upgrade & langsung bayar (skip trial):**
```
Klik "Upgrade Profesional" → Pilih billing cycle (monthly/annual)
→ System create Invoice Rp 299.000 (atau Rp 249.000/bulan untuk annual)
→ Generate Midtrans Snap Token → Muncul popup pembayaran Midtrans
→ User bayar via QRIS / Bank Transfer / GoPay, dll
→ Midtrans kirim webhook ke server kita
→ Server terima notifikasi "settlement" → Subscription status = "active"
→ User bisa pakai Profesional selama 30 hari (monthly) atau 365 hari (annual)
```

**Skenario D — Subscription expired:**
```
Scheduler jalan tiap hari jam 00:00 → cek semua subscription
→ Subscription yang ends_at < sekarang → status = "expired"
→ Auto assign Subscription Pemula baru (gratis)
→ User bisa lanjut pakai dengan batasan Pemula
```

### 2. Data demo / seeder gimana? Ikutan kena limit?

**Tidak.** Demo user (seeder `UserSeeder`) hanya digunakan untuk development & testing. Saat production:

- UserSeeder TIDAK dijalankan di production (`DatabaseSeeder` hanya untuk dev)
- Migration command `subscription:migrate-existing-users` hanya memproses user REAL di database
- Demo user bisa tetap ada tanpa subscription — middleware `CheckSubscriptionLimit` hanya aktif di route yang dipasangi middleware (`products.store`, `users.store`, `warehouses.store`)

**Cara amannya:** Di `DatabaseSeeder`, kita bisa panggil `assignFreeSubscription()` untuk demo user juga, jadi mereka tetap bisa dipakai testing. Tapi di production, migration command `subscription:migrate-existing-users` hanya jalan sekali untuk assign Pemula ke semua existing user.

### 3. Sistem lisensi? License key?

**Tidak perlu license key.** Ini SaaS (Software as a Service) — bukan on-premise software. Modelnya:

- User daftar → dapat akses ke aplikasi web
- Subscription menentukan batasan fitur
- Tidak ada software yang di-install di komputer user
- Semua berjalan di server kita, user akses via browser

Kalau nanti ada kebutuhan whitelabel (user install di server sendiri), baru butuh license key. Tapi untuk model sekarang, subscription-based SaaS sudah cukup.

### 4. Gimana Midtrans handle subscription payment?

Midtrans TIDAK punya native "recurring billing" seperti Stripe. Yang kita lakukan:

1. **Saat user mau bayar:** Generate Snap token dengan nominal sesuai plan
2. **User bayar:** Via Midtrans popup (QRIS, bank transfer, GoPay, dll)
3. **Webhook:** Midtrans kirim notifikasi `settlement` → kita aktifkan subscription
4. **Perpanjangan:** User harus manual bayar lagi sebelum `ends_at` habis. Bisa di-improve nanti dengan:
   - Kirim email reminder H-7 sebelum expired
   - Tombol "Perpanjang" di halaman subscription
   - Opsi `auto_renew` kalau ada fitur card tokenization Midtrans

**Untuk MVP:** Manual perpanjangan. Scheduler hanya untuk downgrade expired, bukan untuk auto-charge.

### 5. Gimana cara ngecek limit? Real-time atau periodik?

**Real-time via Middleware.** Setiap kali user klik "Tambah Produk":

```php
Route::post('/products', [ProductController::class, 'store'])
    ->middleware('subscription.limit:product');  // ← ini yang ngecek
```

Middleware langsung query `Product::count()` dan bandingkan dengan `currentPlan->max_products`. Kalau melebihi → response 403.

**Tidak pakai queue/job** karena count query-nya ringan (`SELECT COUNT(*) FROM products` pakai index). Tapi kalau nanti perlu, bisa di-cache dengan short TTL.

### 6. Apa yang terjadi kalau user di tengah jalan downgrade?

Contoh: User Profesional punya 500 produk, lalu subscription expired → downgrade ke Pemula (max 100).

- **Produk yang sudah ada:** Tetap ada (tidak dihapus)
- **Tambah produk baru:** Ditolak karena melebihi limit 100
- **Edit/update produk existing:** Tetap bisa
- **Delete produk:** Tetap bisa

Jadi downgrade hanya mencegah penambahan baru, tidak menghapus data yang sudah ada.

### 7. Bagaimana dengan super_admin? Dia kena limit?

**Tidak.** Super_admin pakai gate `Gate::before` yang auto-allow semua permission. Di middleware `CheckSubscriptionLimit`, kita bisa tambah pengecualian:

```php
if ($user->hasRole(Role::SuperAdmin->value)) {
    return $next($request);  // super_admin selalu lolos
}
```

### 8. Bisa ganti dari bulanan ke tahunan (atau sebaliknya)?

**Bisa.** Saat user upgrade, mereka pilih billing cycle. Untuk ganti cycle:
1. Cancel subscription yang sekarang
2. Tunggu sampai masa aktif habis (grace period)
3. Upgrade lagi dengan cycle baru

Atau admin bisa langsung update via Admin Panel.

### 9. Gimana cara Midtrans webhook tahu ini pembayaran subscription atau POS?

Pakai prefix Order ID:
- `SUB-{invoice_id}-{timestamp}` → subscription invoice → handle pakai `handleSubscriptionNotification()`
- Format POS lain → handle pakai logic POS yang sudah ada

Jadi satu endpoint `midtransNotification()` bisa handle keduanya.

### 10. Apakah implementation plan ini bisa langsung dipakai production?

**Bisa**, dengan catatan:
- Midtrans harus sudah production-ready (server key production)
- Plan limit disesuaikan dengan kebutuhan bisnis
- Email reminder expired perlu ditambahkan nanti
- Auto-renewal via card tokenization bisa jadi iterasi berikutnya

Untuk MVP, plan ini sudah cukup: registration → auto Pemula → upgrade bayar via Midtrans → scheduler cek expired → downgrade otomatis.

