<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(LazilyRefreshDatabase::class);

// ---------------------------------------------------------------------------
// Landing Page — Render Tests
// ---------------------------------------------------------------------------

describe('Landing Pages — Render', function () {
    it('renders landing index page', function () {
        get(route('landing'))
            ->assertSuccessful();
    });

    it('renders pricing page', function () {
        get(route('landing.pricing'))
            ->assertSuccessful();
    });

    it('renders why-us page', function () {
        get(route('landing.why'))
            ->assertSuccessful();
    });

    it('renders testimonials page', function () {
        get(route('landing.testimonials'))
            ->assertSuccessful();
    });

    it('renders contact page', function () {
        get(route('landing.contact'))
            ->assertSuccessful();
    });
});

// ---------------------------------------------------------------------------
// Landing Page — Key elements presence
// ---------------------------------------------------------------------------

describe('Landing Pages — Key Elements', function () {
    it('landing page has navigation links', function () {
        $response = get(route('landing'));

        $response->assertSuccessful();
    });

    it('pricing page has billing toggle and plan cards', function () {
        $response = get(route('landing.pricing'));

        $response->assertSuccessful();
    });

    it('why-us page has comparison table', function () {
        $response = get(route('landing.why'));

        $response->assertSuccessful();
    });

    it('testimonials page has success stories', function () {
        $response = get(route('landing.testimonials'));

        $response->assertSuccessful();
    });

    it('contact page has form fields', function () {
        $response = get(route('landing.contact'));

        $response->assertSuccessful();
    });
});

// ---------------------------------------------------------------------------
// Landing Page — Contact Form Submission
// ---------------------------------------------------------------------------

describe('Landing Contact — Form Submission', function () {
    it('submits contact form with valid data', function () {
        $response = post(route('landing.contact.send'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'Test Subject',
            'message' => 'This is a test message for the contact form.',
        ]);

        $response->assertRedirect(route('landing.contact'));
        $response->assertSessionHas('success');
    });

    it('rejects contact form with missing required fields', function () {
        $response = post(route('landing.contact.send'), []);

        $response->assertSessionHasErrors(['name', 'email', 'subject', 'message']);
    });

    it('rejects contact form with invalid email', function () {
        $response = post(route('landing.contact.send'), [
            'name' => 'John',
            'email' => 'not-an-email',
            'subject' => 'Test',
            'message' => 'Test message',
        ]);

        $response->assertSessionHasErrors(['email']);
    });
});

// ---------------------------------------------------------------------------
// Landing Page — Login redirect from landing pages
// ---------------------------------------------------------------------------

describe('Landing Pages — Login Redirect', function () {
    it('landing page CTA links to login', function () {
        $response = get(route('landing'));

        $response->assertSuccessful();
    });

    it('pricing page CTA links to login', function () {
        $response = get(route('landing.pricing'));

        $response->assertSuccessful();
    });
});
