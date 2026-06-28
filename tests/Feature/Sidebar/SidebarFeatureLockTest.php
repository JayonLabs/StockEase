<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

// ===========================================================================
// PlanFeature keys used in menu.js sidebar configuration
// ===========================================================================
const MENU_PLAN_FEATURE_KEYS = [
    'purchasing',
    'purchase_report',
    'stock_report',
    'profit_loss',
    'multi_warehouse',
    'file_manager',
    'activity_log',
];

// ===========================================================================
// Feature keys that enterprise has but profesional does not
// ===========================================================================
const ENTERPRISE_ONLY_FEATURES = [
    'profit_loss',
    'file_manager',
];

// ===========================================================================
// Feature keys included in profesional but not in pemula
// ===========================================================================
const PROFESIONAL_ONLY_FEATURES = [
    'purchasing',
    'purchase_report',
    'stock_report',
    'multi_warehouse',
    'activity_log',
];

beforeEach(function () {
    /** @var object{pemula: Plan, profesional: Plan, enterprise: Plan, company: Company, user: User} $this */
    $this->pemula = Plan::factory()->pemula()->create();
    $this->profesional = Plan::factory()->profesional()->create();
    $this->enterprise = Plan::factory()->enterprise()->create();

    $this->company = Company::factory()->create();
    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
        'role' => 'admin',
    ]);
    $this->company->update(['owner_id' => $this->user->id]);
});

// ===========================================================================
// 1. Feature Key Coverage — menu.js keys exist in Plan features
// ===========================================================================

it('all menu planFeature keys exist in plan features configuration', function () {
    /** @var object{pemula: Plan, profesional: Plan, enterprise: Plan, company: Company, user: User} $this */
    $planFeatures = collect($this->enterprise->features)->pluck('key')->all();

    foreach (MENU_PLAN_FEATURE_KEYS as $key) {
        expect(in_array($key, $planFeatures, true))
            ->toBeTrue("Menu planFeature key '{$key}' not found in any plan features.");
    }
});

// ===========================================================================
// 2. Plan::hasFeature() — locked/unlocked state per plan tier
// ===========================================================================

describe('Pemula — all menu features should be locked', function () {
    it('locks every sidebar planFeature key', function () {
        /** @var object{pemula: Plan, profesional: Plan, enterprise: Plan, company: Company, user: User} $this */
        foreach (MENU_PLAN_FEATURE_KEYS as $key) {
            expect($this->pemula->hasFeature($key))
                ->toBeFalse("Pemula should NOT have '{$key}'.");
        }
    });
});

describe('Profesional — partial unlock', function () {
    it('unlocks profesional-level features', function () {
        /** @var object{pemula: Plan, profesional: Plan, enterprise: Plan, company: Company, user: User} $this */
        foreach (PROFESIONAL_ONLY_FEATURES as $key) {
            expect($this->profesional->hasFeature($key))
                ->toBeTrue("Profesional should have '{$key}'.");
        }
    });

    it('keeps enterprise-only features locked', function () {
        /** @var object{pemula: Plan, profesional: Plan, enterprise: Plan, company: Company, user: User} $this */
        foreach (ENTERPRISE_ONLY_FEATURES as $key) {
            expect($this->profesional->hasFeature($key))
                ->toBeFalse("Profesional should NOT have '{$key}'.");
        }
    });
});

describe('Enterprise — all features unlocked', function () {
    it('unlocks every sidebar planFeature key', function () {
        /** @var object{pemula: Plan, profesional: Plan, enterprise: Plan, company: Company, user: User} $this */
        foreach (MENU_PLAN_FEATURE_KEYS as $key) {
            expect($this->enterprise->hasFeature($key))
                ->toBeTrue("Enterprise should have '{$key}'.");
        }
    });
});

// ===========================================================================
// 3. Plan feature data — structure for Inertia frontend sharing
// ===========================================================================

describe('plan feature structure for frontend', function () {
    it('shapes as key => boolean map from pemula plan', function () {
        /** @var object{pemula: Plan, profesional: Plan, enterprise: Plan, company: Company, user: User} $this */
        $this->company->subscription()->create([
            'plan_id' => $this->pemula->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        $subscription = $this->company->activeSubscription();
        $featureMap = collect($subscription->plan->features ?? [])
            ->pluck('included', 'key')
            ->all();

        foreach (MENU_PLAN_FEATURE_KEYS as $key) {
            expect($featureMap)->toHaveKey($key);
            expect($featureMap[$key])->toBeFalse("Pemula '{$key}' should be false.");
        }
    });

    it('shapes as key => boolean map from enterprise plan', function () {
        /** @var object{pemula: Plan, profesional: Plan, enterprise: Plan, company: Company, user: User} $this */
        $this->company->subscription()->create([
            'plan_id' => $this->enterprise->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        $subscription = $this->company->activeSubscription();
        $featureMap = collect($subscription->plan->features ?? [])
            ->pluck('included', 'key')
            ->all();

        foreach (MENU_PLAN_FEATURE_KEYS as $key) {
            expect($featureMap)->toHaveKey($key);
            expect($featureMap[$key])->toBeTrue("Enterprise '{$key}' should be true.");
        }
    });
});

// ===========================================================================
// 4. Plan::hasFeature() — edge cases
// ===========================================================================

it('returns false for non-existent feature key', function () {
    /** @var object{pemula: Plan, profesional: Plan, enterprise: Plan, company: Company, user: User} $this */
    expect($this->pemula->hasFeature('nonexistent'))->toBeFalse();
});

it('returns false when features is null', function () {
    $plan = Plan::factory()->create(['features' => null]);
    expect($plan->hasFeature('purchasing'))->toBeFalse();
});

it('returns false when features is empty array', function () {
    $plan = Plan::factory()->create(['features' => []]);
    expect($plan->hasFeature('purchasing'))->toBeFalse();
});
