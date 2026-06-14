<?php

// ---------------------------------------------------------------------------
// Existing Code — runs now against real classes
// ---------------------------------------------------------------------------

arch('plan model uses Cache::flexible for pricing page')
    ->expect('App\Models\Plan')
    ->toUse('Illuminate\Support\Facades\Cache');

arch('plan model has forPricingPage and related methods')
    ->expect('App\Models\Plan')
    ->toHaveMethods(['forPricingPage', 'cardFeatures', 'comparisonFeatures']);

arch('plan model uses HasFactory and SoftDeletes')
    ->expect('App\Models\Plan')
    ->toUse('Illuminate\Database\Eloquent\Factories\HasFactory')
    ->toUse('Illuminate\Database\Eloquent\SoftDeletes');

arch('plan factory has all expected states')
    ->expect('Database\Factories\PlanFactory')
    ->toHaveMethods(['pemula', 'profesional', 'enterprise', 'free', 'paid', 'inactive']);

arch('landing controller has pricing method')
    ->expect('App\Http\Controllers\Landing\LandingController')
    ->toHaveMethod('pricing');

arch('landing controller uses Inertia for rendering')
    ->expect('App\Http\Controllers\Landing\LandingController')
    ->toUse('Inertia\Inertia');

// ---------------------------------------------------------------------------
// Platform Admin — requires implementation, skip if classes don't exist yet
// These are validated by Unit/Feature tests when implementation is done.
// ---------------------------------------------------------------------------
