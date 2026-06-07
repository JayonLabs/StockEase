<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    /**
     * Display the landing page.
     */
    public function index(): Response
    {
        return Inertia::render('Landing/Index');
    }

    /**
     * Display the pricing page.
     */
    public function pricing(): Response
    {
        return Inertia::render('Landing/Pricing');
    }

    /**
     * Display the why page.
     */
    public function why(): Response
    {
        return Inertia::render('Landing/Why');
    }

    /**
     * Display the testimonials page.
     */
    public function testimonials(): Response
    {
        return Inertia::render('Landing/Testimonials');
    }

    /**
     * Display the contact page.
     */
    public function contact(): Response
    {
        return Inertia::render('Landing/Contact');
    }

    /**
     * Send a contact message.
     */
    public function sendContact(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:200'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        return redirect()->route('landing.contact')
            ->with('success', 'Pesan Anda telah terkirim! Kami akan menghubungi Anda kembali dalam 2–4 jam kerja.');
    }
}
