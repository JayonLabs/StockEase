<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(LazilyRefreshDatabase::class);

// ============================================================
// Public accessibility
// ============================================================

it('renders the landing page for guests', function () {
    get(route('landing'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Landing/Index'));
});

it('renders the pricing page for guests', function () {
    get(route('landing.pricing'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Landing/Pricing'));
});

it('renders the why-us page for guests', function () {
    get(route('landing.why'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Landing/Why'));
});

it('renders the testimonials page for guests', function () {
    get(route('landing.testimonials'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Landing/Testimonials'));
});

it('renders the contact page for guests', function () {
    get(route('landing.contact'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Landing/Contact'));
});

// ============================================================
// Authenticated users can still view landing pages
// ============================================================

it('renders the landing page for authenticated users', function () {
    $user = User::factory()->create();

    /** @var User $user */
    actingAs($user)
        ->get(route('landing'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Landing/Index'));
});

// ============================================================
// Dashboard requires auth
// ============================================================

it('redirects guests from dashboard to login', function () {
    get(route('dashboard'))
        ->assertRedirect(route('login'));
});

it('renders dashboard for authenticated users', function () {
    $user = User::factory()->create();

    /** @var User $user */
    actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

// ============================================================
// Contact form submission
// ============================================================

it('rejects contact form with missing fields', function () {
    post(route('landing.contact.send'), [])
        ->assertSessionHasErrors(['name', 'email', 'subject', 'message']);
});

it('rejects contact form with invalid email', function () {
    post(route('landing.contact.send'), [
        'name' => 'John Doe',
        'email' => 'not-an-email',
        'subject' => 'Hello',
        'message' => 'This is a test message.',
    ])->assertSessionHasErrors(['email']);
});

it('redirects back to contact page with success flash after valid submission', function () {
    from(route('landing.contact'))
        ->post(route('landing.contact.send'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'Product inquiry',
            'message' => 'I would like to know more about your pricing.',
        ])
        ->assertRedirect(route('landing.contact'))
        ->assertSessionHas('success');
});

it('rejects contact message that exceeds max length', function () {
    post(route('landing.contact.send'), [
        'name' => str_repeat('a', 101),
        'email' => 'john@example.com',
        'subject' => 'Hi',
        'message' => 'A valid message.',
    ])->assertSessionHasErrors(['name']);
});
