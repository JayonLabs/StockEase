<?php

use App\Mail\ContactInquiryMail;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;

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
// Contact form – validation
// ============================================================

it('rejects contact form with missing fields', function () {
    Mail::fake();

    post(route('landing.contact.send'), [])
        ->assertSessionHasErrors(['name', 'email', 'subject', 'message']);

    Mail::assertNothingSent();
});

it('rejects contact form with invalid email', function () {
    Mail::fake();

    post(route('landing.contact.send'), [
        'name' => 'John Doe',
        'email' => 'not-an-email',
        'subject' => 'Hello',
        'message' => 'This is a test message.',
    ])->assertSessionHasErrors(['email']);

    Mail::assertNothingSent();
});

it('rejects name that exceeds 100 characters', function () {
    Mail::fake();

    post(route('landing.contact.send'), [
        'name' => str_repeat('a', 101),
        'email' => 'john@example.com',
        'subject' => 'Hi',
        'message' => 'A valid message.',
    ])->assertSessionHasErrors(['name']);

    Mail::assertNothingSent();
});

it('rejects subject that exceeds 200 characters', function () {
    Mail::fake();

    post(route('landing.contact.send'), [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'subject' => str_repeat('s', 201),
        'message' => 'A valid message.',
    ])->assertSessionHasErrors(['subject']);

    Mail::assertNothingSent();
});

it('rejects message that exceeds 5000 characters', function () {
    Mail::fake();

    post(route('landing.contact.send'), [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'subject' => 'Hi',
        'message' => str_repeat('x', 5001),
    ])->assertSessionHasErrors(['message']);

    Mail::assertNothingSent();
});

// ============================================================
// Contact form – successful submission
// ============================================================

it('queues a ContactInquiryMail to the admin on valid submission', function () {
    Mail::fake();

    from(route('landing.contact'))
        ->post(route('landing.contact.send'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'Product inquiry',
            'message' => 'I would like to know more about your pricing.',
        ])
        ->assertRedirect(route('landing.contact'))
        ->assertSessionHas('success');

    Mail::assertQueued(ContactInquiryMail::class, function (ContactInquiryMail $mail) {
        return $mail->senderName === 'John Doe'
            && $mail->senderEmail === 'john@example.com'
            && $mail->inquirySubject === 'Product inquiry'
            && $mail->body === 'I would like to know more about your pricing.';
    });
});

it('queues exactly one email per contact form submission', function () {
    Mail::fake();

    from(route('landing.contact'))
        ->post(route('landing.contact.send'), [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'subject' => 'Demo request',
            'message' => 'Can I schedule a demo?',
        ]);

    Mail::assertQueuedCount(1);
});

it('queues the email to the configured admin address', function () {
    Mail::fake();

    $adminEmail = config('mail.contact_admin_email');

    from(route('landing.contact'))
        ->post(route('landing.contact.send'), [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'subject' => 'Demo request',
            'message' => 'Can I schedule a demo?',
        ]);

    Mail::assertQueued(ContactInquiryMail::class, fn ($mail) => $mail->hasTo($adminEmail));
});

it('sets reply-to to the sender email so admin can reply directly', function () {
    Mail::fake();

    from(route('landing.contact'))
        ->post(route('landing.contact.send'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'Support needed',
            'message' => 'Please help me with onboarding.',
        ]);

    Mail::assertQueued(
        ContactInquiryMail::class,
        fn ($mail) => $mail->hasReplyTo('john@example.com', 'John Doe'),
    );
});

it('prefixes the subject with [Pertanyaan] in the envelope', function () {
    $mail = new ContactInquiryMail(
        senderName: 'John Doe',
        senderEmail: 'john@example.com',
        inquirySubject: 'Product inquiry',
        body: 'Some message',
    );

    expect($mail->envelope()->subject)->toBe('[Pertanyaan] Product inquiry');
});

it('allows authenticated users to submit the contact form', function () {
    Mail::fake();

    $user = User::factory()->create();

    /** @var User $user */
    actingAs($user)
        ->from(route('landing.contact'))
        ->post(route('landing.contact.send'), [
            'name' => $user->name,
            'email' => $user->email,
            'subject' => 'Billing question',
            'message' => 'I have a question about my invoice.',
        ])
        ->assertRedirect(route('landing.contact'))
        ->assertSessionHas('success');

    Mail::assertQueued(ContactInquiryMail::class);
});
