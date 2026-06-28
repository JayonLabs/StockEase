<?php

use App\Models\Plan;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

// ---------------------------------------------------------------------------
// isFree()
// ---------------------------------------------------------------------------

it('returns true when price_monthly and price_annual are zero', function () {
    $plan = Plan::factory()->free()->create();

    expect($plan->isFree())->toBeTrue();
});

it('returns false when price_monthly is greater than zero', function () {
    $plan = Plan::factory()->paid()->create();

    expect($plan->isFree())->toBeFalse();
});

// ---------------------------------------------------------------------------
// cardFeatures()
// ---------------------------------------------------------------------------

it('returns only features with card_order sorted ascending', function () {
    $plan = Plan::factory()->create([
        'features' => [
            ['key' => 'a', 'label' => 'A', 'included' => true, 'card_order' => 3],
            ['key' => 'b', 'label' => 'B', 'included' => false],
            ['key' => 'c', 'label' => 'C', 'included' => true, 'card_order' => 1],
            ['key' => 'd', 'label' => 'D', 'included' => true, 'card_order' => 2],
        ],
    ]);

    $card = $plan->cardFeatures();

    expect($card)->toHaveCount(3);
    expect($card[0]['key'])->toBe('c');
    expect($card[1]['key'])->toBe('d');
    expect($card[2]['key'])->toBe('a');
});

it('returns empty array when no features have card_order', function () {
    $plan = Plan::factory()->create([
        'features' => [
            ['key' => 'a', 'label' => 'A', 'included' => true],
        ],
    ]);

    expect($plan->cardFeatures())->toBe([]);
});

it('returns empty array when features is null', function () {
    $plan = Plan::factory()->create(['features' => null]);

    expect($plan->cardFeatures())->toBe([]);
});

// ---------------------------------------------------------------------------
// scopeActive()
// ---------------------------------------------------------------------------

it('scope active returns only active plans sorted by sort_order', function () {
    Plan::factory()->create(['name' => 'Z', 'sort_order' => 3, 'is_active' => true]);
    Plan::factory()->create(['name' => 'A', 'sort_order' => 1, 'is_active' => true]);
    Plan::factory()->create(['name' => 'B', 'sort_order' => 2, 'is_active' => false]);

    $active = Plan::active()->get();

    expect($active)->toHaveCount(2);
    expect($active[0]->name)->toBe('A');
    expect($active[1]->name)->toBe('Z');
});

// ---------------------------------------------------------------------------
// comparisonFeatures()
// ---------------------------------------------------------------------------

it('builds comparison features from all active plans', function () {
    Plan::factory()->pemula()->create();
    Plan::factory()->profesional()->create();

    $comparison = Plan::comparisonFeatures();

    $products = collect($comparison)->firstWhere('key', 'products');
    expect($products)->not->toBeNull();
    expect($products['label'])->toBe('Produk, Kategori & Satuan');
    expect($products['plans']['pemula'])->toBeTrue();
    expect($products['plans']['profesional'])->toBeTrue();

    $profitLoss = collect($comparison)->firstWhere('key', 'profit_loss');
    expect($profitLoss)->not->toBeNull();
    expect($profitLoss['plans']['pemula'])->toBeFalse();
    expect($profitLoss['plans']['profesional'])->toBeFalse();
});

it('excludes inactive plans from comparison features', function () {
    Plan::factory()->pemula()->create();
    Plan::factory()->create(['slug' => 'inactive-plan', 'is_active' => false]);

    $comparison = Plan::comparisonFeatures();

    $firstRow = $comparison[0] ?? [];
    expect($firstRow)->toHaveKey('plans');
    expect($firstRow['plans'])->not->toHaveKey('inactive-plan');
});

it('returns empty array when no active plans exist', function () {
    expect(Plan::comparisonFeatures())->toBe([]);
});

it('handles plans with null features in comparison', function () {
    Plan::factory()->create(['features' => null, 'slug' => 'no-features', 'is_active' => true]);

    $comparison = Plan::comparisonFeatures();

    expect($comparison)->toBe([]);
});

it('handles plans with empty features array in comparison', function () {
    Plan::factory()->create(['features' => [], 'slug' => 'empty-features', 'is_active' => true]);

    $comparison = Plan::comparisonFeatures();

    expect($comparison)->toBe([]);
});

// ---------------------------------------------------------------------------
// hasFeature()
// ---------------------------------------------------------------------------

it('returns true when feature is included', function () {
    $plan = Plan::factory()->create([
        'features' => [
            ['key' => 'purchasing', 'label' => 'Purchasing', 'included' => true],
        ],
    ]);

    expect($plan->hasFeature('purchasing'))->toBeTrue();
});

it('returns false when feature is not included', function () {
    $plan = Plan::factory()->create([
        'features' => [
            ['key' => 'purchasing', 'label' => 'Purchasing', 'included' => false],
        ],
    ]);

    expect($plan->hasFeature('purchasing'))->toBeFalse();
});

it('returns false for non-existent feature key', function () {
    $plan = Plan::factory()->pemula()->create();

    expect($plan->hasFeature('nonexistent_feature'))->toBeFalse();
});

it('returns false when features is null', function () {
    $plan = Plan::factory()->create(['features' => null]);

    expect($plan->hasFeature('purchasing'))->toBeFalse();
});

it('returns false when features is empty array', function () {
    $plan = Plan::factory()->create(['features' => []]);

    expect($plan->hasFeature('purchasing'))->toBeFalse();
});

it('hasFeature correctly reflects pemula plan restrictions', function () {
    $plan = Plan::factory()->pemula()->create();

    expect($plan->hasFeature('purchasing'))->toBeFalse();
    expect($plan->hasFeature('purchase_report'))->toBeFalse();
    expect($plan->hasFeature('stock_report'))->toBeFalse();
    expect($plan->hasFeature('profit_loss'))->toBeFalse();
    expect($plan->hasFeature('multi_warehouse'))->toBeFalse();
    expect($plan->hasFeature('file_manager'))->toBeFalse();
    expect($plan->hasFeature('activity_log'))->toBeFalse();

    expect($plan->hasFeature('pos'))->toBeTrue();
    expect($plan->hasFeature('products'))->toBeTrue();
    expect($plan->hasFeature('sales_report'))->toBeTrue();
});

it('hasFeature correctly reflects enterprise plan includes all features', function () {
    $plan = Plan::factory()->enterprise()->create();

    expect($plan->hasFeature('purchasing'))->toBeTrue();
    expect($plan->hasFeature('purchase_report'))->toBeTrue();
    expect($plan->hasFeature('stock_report'))->toBeTrue();
    expect($plan->hasFeature('profit_loss'))->toBeTrue();
    expect($plan->hasFeature('multi_warehouse'))->toBeTrue();
    expect($plan->hasFeature('file_manager'))->toBeTrue();
    expect($plan->hasFeature('activity_log'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// forPricingPage()
// ---------------------------------------------------------------------------

it('returns structured data from forPricingPage', function () {
    Plan::factory()->pemula()->create();
    Plan::factory()->profesional()->create();
    Plan::factory()->enterprise()->create();

    $data = Plan::forPricingPage();

    expect($data)->toHaveKeys(['plans', 'comparison']);
    expect($data['plans'])->toHaveCount(3);
    expect($data['plans'][0]['name'])->toBe('Pemula');
    expect($data['plans'][1]['name'])->toBe('Profesional');
    expect($data['plans'][2]['name'])->toBe('Enterprise');
});

it('formats pricing data correctly', function () {
    Plan::factory()->pemula()->create();

    $data = Plan::forPricingPage();

    $plan = $data['plans'][0];
    expect($plan)->toHaveKeys([
        'id', 'name', 'slug', 'description', 'price_monthly', 'price_annual',
        'features', 'trial_days', 'is_free', 'sort_order',
    ]);
    expect($plan['price_monthly'])->toBeInt();
    expect($plan['price_annual'])->toBeInt();
    expect($plan['is_free'])->toBeBool();
});

it('uses Cache::flexible with correct TTL', function () {
    Plan::factory()->pemula()->create();

    Cache::shouldReceive('flexible')
        ->once()
        ->with('plans_pricing', [86400, 604800], Mockery::on(fn ($callback) => is_callable($callback)))
        ->andReturn(['plans' => [], 'comparison' => []]);

    $result = Plan::forPricingPage();
    expect($result)->toBe(['plans' => [], 'comparison' => []]);
});

it('returns only card features in plan features', function () {
    Plan::factory()->pemula()->create();

    $data = Plan::forPricingPage();

    foreach ($data['plans'] as $plan) {
        foreach ($plan['features'] as $feature) {
            expect($feature)->toHaveKey('card_order');
        }
    }
});

// ---------------------------------------------------------------------------
// Factory states
// ---------------------------------------------------------------------------

it('factory pemula state creates correct default plan', function () {
    $plan = Plan::factory()->pemula()->create();

    expect($plan->slug)->toBe('pemula');
    expect((int) $plan->price_monthly)->toBe(50000);
    expect((int) $plan->price_annual)->toBe(500000);
    expect($plan->trial_days)->toBe(14);
    expect($plan->is_active)->toBeTrue();
    expect($plan->isFree())->toBeFalse();
});

it('factory profesional state creates correct plan', function () {
    $plan = Plan::factory()->profesional()->create();

    expect($plan->slug)->toBe('profesional');
    expect((int) $plan->price_monthly)->toBe(149000);
    expect((int) $plan->price_annual)->toBe(1490000);
    expect($plan->trial_days)->toBe(0);
});

it('factory enterprise state creates correct plan', function () {
    $plan = Plan::factory()->enterprise()->create();

    expect($plan->slug)->toBe('enterprise');
    expect((int) $plan->price_monthly)->toBe(299000);
    expect((int) $plan->price_annual)->toBe(2990000);
});

it('factory inactive state creates inactive plan', function () {
    $plan = Plan::factory()->inactive()->create();

    expect($plan->is_active)->toBeFalse();
});

it('factory free state creates free plan', function () {
    $plan = Plan::factory()->free()->create();

    expect((int) $plan->price_monthly)->toBe(0);
    expect((int) $plan->price_annual)->toBe(0);
    expect($plan->trial_days)->toBe(0);
});

it('factory paid state creates paid plan without trial days', function () {
    $plan = Plan::factory()->paid()->create();

    expect((int) $plan->price_monthly)->toBe(149000);
    expect((int) $plan->price_annual)->toBe(1490000);
    expect($plan->trial_days)->toBe(0);
});

// ---------------------------------------------------------------------------
// annualSavingsPercent()
// ---------------------------------------------------------------------------

it('annual savings is 17% for pemula plan', function () {
    $plan = Plan::factory()->pemula()->create();

    expect($plan->annualSavingsPercent())->toBe(17);
});

it('annual savings is 0 for free plan', function () {
    $plan = Plan::factory()->free()->create();

    expect($plan->annualSavingsPercent())->toBe(0);
});

it('annual savings is 0 when monthly price is zero', function () {
    $plan = Plan::factory()->create([
        'price_monthly' => 0,
        'price_annual' => 100000,
    ]);

    expect($plan->annualSavingsPercent())->toBe(0);
});

// ---------------------------------------------------------------------------
// annualPerMonth()
// ---------------------------------------------------------------------------

it('calculates annual per month correctly', function () {
    $plan = Plan::factory()->pemula()->create();

    expect($plan->annualPerMonth())->toBe(41700);
});

it('returns 0 for free plan annual per month', function () {
    $plan = Plan::factory()->free()->create();

    expect($plan->annualPerMonth())->toBe(0);
});

// ---------------------------------------------------------------------------
// priceForBilling()
// ---------------------------------------------------------------------------

it('returns monthly price for monthly billing', function () {
    $plan = Plan::factory()->pemula()->create();

    expect($plan->priceForBilling('monthly'))->toBe(50000);
});

it('returns annual price for annual billing', function () {
    $plan = Plan::factory()->pemula()->create();

    expect($plan->priceForBilling('annual'))->toBe(500000);
});

it('defaults to monthly billing', function () {
    $plan = Plan::factory()->pemula()->create();

    expect($plan->priceForBilling())->toBe(50000);
});

// ---------------------------------------------------------------------------
// Pricing integrity: annual MUST always be cheaper than monthly x 12
// ---------------------------------------------------------------------------

it('ensures annual price is less than monthly x 12 for all paid plans', function () {
    Plan::factory()->pemula()->create();
    Plan::factory()->profesional()->create();
    Plan::factory()->enterprise()->create();

    $plans = Plan::all();
    foreach ($plans as $plan) {
        if (! $plan->isFree()) {
            $monthlyTotal = (int) $plan->price_monthly * 12;
            expect((int) $plan->price_annual)->toBeLessThan($monthlyTotal);
        }
    }
});

it('ensures annual savings is always positive for paid plans', function () {
    Plan::factory()->pemula()->create();
    Plan::factory()->profesional()->create();
    Plan::factory()->enterprise()->create();

    $plans = Plan::all();
    foreach ($plans as $plan) {
        if (! $plan->isFree()) {
            expect($plan->annualSavingsPercent())->toBeGreaterThan(0);
        }
    }
});

it('ensures annual per month is less than monthly price for all paid plans', function () {
    Plan::factory()->pemula()->create();
    Plan::factory()->profesional()->create();
    Plan::factory()->enterprise()->create();

    $plans = Plan::all();
    foreach ($plans as $plan) {
        if (! $plan->isFree()) {
            expect($plan->annualPerMonth())->toBeLessThan((int) $plan->price_monthly);
        }
    }
});

// ---------------------------------------------------------------------------
// Pricing ladder: each higher tier must be more expensive
// ---------------------------------------------------------------------------

it('ensures pricing increases with each plan tier', function () {
    Plan::factory()->pemula()->create();
    Plan::factory()->profesional()->create();
    Plan::factory()->enterprise()->create();

    $plans = Plan::active()->get();
    $monthlyPrices = $plans->pluck('price_monthly')->map(fn ($p) => (int) $p)->values();

    expect($monthlyPrices[0])->toBeLessThan($monthlyPrices[1]);
    expect($monthlyPrices[1])->toBeLessThan($monthlyPrices[2]);
});

it('ensures annual prices increase with each plan tier', function () {
    Plan::factory()->pemula()->create();
    Plan::factory()->profesional()->create();
    Plan::factory()->enterprise()->create();

    $plans = Plan::active()->get();
    $annualPrices = $plans->pluck('price_annual')->map(fn ($p) => (int) $p)->values();

    expect($annualPrices[0])->toBeLessThan($annualPrices[1]);
    expect($annualPrices[1])->toBeLessThan($annualPrices[2]);
});

// ---------------------------------------------------------------------------
// Feature progression: each higher tier has strictly more features
// ---------------------------------------------------------------------------

it('ensures each tier includes all features of lower tiers', function () {
    Plan::factory()->pemula()->create();
    Plan::factory()->profesional()->create();
    Plan::factory()->enterprise()->create();

    $plans = Plan::active()->get();

    $featuresByPlan = $plans->mapWithKeys(fn (Plan $plan) => [
        $plan->slug => collect($plan->features ?? [])
            ->filter(fn ($f) => $f['included'])
            ->pluck('key')
            ->values()
            ->all(),
    ]);

    // Profesional must include all Pemula features
    foreach ($featuresByPlan['pemula'] as $feature) {
        expect(in_array($feature, $featuresByPlan['profesional']))->toBeTrue(
            "Profesional missing feature: {$feature}"
        );
    }

    // Enterprise must include all Profesional features
    foreach ($featuresByPlan['profesional'] as $feature) {
        expect(in_array($feature, $featuresByPlan['enterprise']))->toBeTrue(
            "Enterprise missing feature: {$feature}"
        );
    }
});

it('ensures enterprise has more features than profesional', function () {
    Plan::factory()->pemula()->create();
    Plan::factory()->profesional()->create();
    Plan::factory()->enterprise()->create();

    $plans = Plan::active()->get();

    $counts = $plans->mapWithKeys(fn (Plan $plan) => [
        $plan->slug => collect($plan->features ?? [])->filter(fn ($f) => $f['included'])->count(),
    ]);

    expect($counts['pemula'])->toBeLessThan($counts['profesional']);
    expect($counts['profesional'])->toBeLessThan($counts['enterprise']);
});

// ---------------------------------------------------------------------------
// Default factory pricing integrity
// ---------------------------------------------------------------------------

it('ensures default factory always produces valid annual pricing', function () {
    Plan::factory()->count(10)->create();

    $plans = Plan::all();
    foreach ($plans as $plan) {
        if (! $plan->isFree()) {
            $expectedAnnual = (int) round((int) $plan->price_monthly * 10);
            expect((int) $plan->price_annual)->toBe($expectedAnnual);
        }
    }
});

it('ensures default factory never produces annual more expensive than monthly x 12', function () {
    Plan::factory()->count(20)->create();

    $plans = Plan::all();
    foreach ($plans as $plan) {
        $monthlyTotal = (int) $plan->price_monthly * 12;
        expect((int) $plan->price_annual)->toBeLessThanOrEqual($monthlyTotal);
    }
});
