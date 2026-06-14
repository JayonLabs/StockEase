<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landing\ContactFormRequest;
use App\Mail\ContactInquiryMail;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    /**
     * Display the public landing / home page.
     */
    public function index(): Response
    {
        return Inertia::render('Landing/Index');
    }

    /**
     * Display the pricing page with plan data sourced from the database.
     */
    public function pricing(): Response
    {
        $data = Plan::forPricingPage();

        return Inertia::render('Landing/Pricing', $data);
    }

    /**
     * Display the "Why Us" page highlighting key product differentiators.
     */
    public function why(): Response
    {
        return Inertia::render('Landing/Why');
    }

    /**
     * Display the testimonials page showcasing customer reviews.
     */
    public function testimonials(): Response
    {
        return Inertia::render('Landing/Testimonials');
    }

    /**
     * Display the contact / enquiry page.
     */
    public function contact(): Response
    {
        return Inertia::render('Landing/Contact');
    }

    /**
     * Handle the contact form submission.
     *
     * Validates the incoming payload via ContactFormRequest, then dispatches
     * a queued ContactInquiryMail to the admin sales address configured in
     * CONTACT_ADMIN_EMAIL. The Reply-To header is set to the sender's address
     * so the admin can reply directly from their email client.
     */
    public function sendContact(ContactFormRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Mail::to(config('mail.contact_admin_email'))
            ->send(new ContactInquiryMail(
                senderName: $validated['name'],
                senderEmail: $validated['email'],
                inquirySubject: $validated['subject'],
                body: $validated['message'],
            ));

        return redirect()->route('landing.contact')
            ->with('success', 'Pesan Anda telah terkirim! Kami akan menghubungi Anda kembali dalam 2–4 jam kerja.');
    }
}
