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
// Landing Pages — SEO Props
// ---------------------------------------------------------------------------

describe('Landing Pages — SEO Props', function () {
    it('index page returns seo props', function () {
        get(route('landing'))
            ->assertInertia(fn ($page) => $page
                ->has('seo.title')
                ->has('seo.description')
                ->has('seo.canonical')
                ->has('seo.ogImage')
                ->where('seo.canonical', route('landing'))
            );
    });

    it('pricing page returns seo props', function () {
        get(route('landing.pricing'))
            ->assertInertia(fn ($page) => $page
                ->has('seo.title')
                ->has('seo.description')
                ->where('seo.canonical', route('landing.pricing'))
            );
    });

    it('why page returns seo props', function () {
        get(route('landing.why'))
            ->assertInertia(fn ($page) => $page
                ->has('seo.title')
                ->has('seo.description')
                ->where('seo.canonical', route('landing.why'))
            );
    });

    it('testimonials page returns seo props', function () {
        get(route('landing.testimonials'))
            ->assertInertia(fn ($page) => $page
                ->has('seo.title')
                ->has('seo.description')
                ->where('seo.canonical', route('landing.testimonials'))
            );
    });

    it('contact page returns seo props', function () {
        get(route('landing.contact'))
            ->assertInertia(fn ($page) => $page
                ->has('seo.title')
                ->has('seo.description')
                ->where('seo.canonical', route('landing.contact'))
            );
    });

    it('seo og image is set on all pages', function () {
        foreach (['landing', 'landing.pricing', 'landing.why', 'landing.testimonials', 'landing.contact'] as $routeName) {
            get(route($routeName))
                ->assertInertia(fn ($page) => $page->has('seo.ogImage'));
        }
    });
});

// ---------------------------------------------------------------------------
// Landing Pages — Sitemap & Robots
// ---------------------------------------------------------------------------

describe('Landing Pages — Sitemap & Robots', function () {
    it('sitemap.xml is accessible and returns xml content-type', function () {
        get(route('sitemap'))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/xml');
    });

    it('sitemap.xml contains all landing page urls', function () {
        $response = get(route('sitemap'));

        $response->assertSee(route('landing'), false)
            ->assertSee(route('landing.pricing'), false)
            ->assertSee(route('landing.why'), false)
            ->assertSee(route('landing.testimonials'), false)
            ->assertSee(route('landing.contact'), false);
    });

    it('sitemap.xml is valid xml with urlset root', function () {
        $body = get(route('sitemap'))->getContent();

        $xml = simplexml_load_string($body);

        expect($xml)->not->toBeFalse()
            ->and($xml->getName())->toBe('urlset');
    });

    it('robots.txt exists in public directory', function () {
        expect(file_exists(public_path('robots.txt')))->toBeTrue();
    });

    it('robots.txt references the sitemap', function () {
        $content = file_get_contents(public_path('robots.txt'));

        expect($content)->toContain('Sitemap:');
    });

    it('robots.txt disallows authenticated routes', function () {
        $content = file_get_contents(public_path('robots.txt'));

        expect($content)
            ->toContain('Disallow: /dashboard')
            ->toContain('Disallow: /login');
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
