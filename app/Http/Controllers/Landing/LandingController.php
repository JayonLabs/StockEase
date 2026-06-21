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
        return Inertia::render('Landing/Index', $this->seoProps(
            title: 'StockEase — Sederhanakan Penjualan, Inventaris & Pembayaran',
            description: 'Sistem ERP & POS cerdas untuk bisnis ritel Indonesia. Kelola stok real-time, transaksi cepat, dan laporan otomatis. Coba gratis sekarang.',
            routeName: 'landing',
        ));
    }

    /**
     * Display the pricing page with plan data sourced from the database.
     */
    public function pricing(): Response
    {
        $data = Plan::forPricingPage();

        return Inertia::render('Landing/Pricing', array_merge($data, $this->seoProps(
            title: 'Harga Paket StockEase — Sederhana & Transparan',
            description: 'Pilih paket StockEase yang sesuai bisnis Anda. Mulai gratis tanpa kartu kredit, upgrade kapan saja seiring pertumbuhan bisnis.',
            routeName: 'landing.pricing',
        )));
    }

    /**
     * Display the "Why Us" page highlighting key product differentiators.
     */
    public function why(): Response
    {
        return Inertia::render('Landing/Why', $this->seoProps(
            title: 'Kenapa StockEase — Dirancang untuk Presisi & Pertumbuhan',
            description: 'Checkout di bawah 10 detik, inventaris prediktif real-time, dan laporan berbasis AI. Lihat perbandingan StockEase vs mesin kasir biasa.',
            routeName: 'landing.why',
        ));
    }

    /**
     * Display the testimonials page showcasing customer reviews.
     */
    public function testimonials(): Response
    {
        return Inertia::render('Landing/Testimonials', $this->seoProps(
            title: 'Testimoni Pelanggan StockEase — 120+ Bisnis Ritel Berkembang',
            description: 'Lebih dari 120 bisnis ritel Indonesia telah meningkatkan operasional mereka bersama StockEase. Baca cerita sukses nyata dari pelanggan kami.',
            routeName: 'landing.testimonials',
        ));
    }

    /**
     * Display the contact / enquiry page.
     */
    public function contact(): Response
    {
        return Inertia::render('Landing/Contact', $this->seoProps(
            title: 'Hubungi StockEase — Tim Kami Siap Membantu',
            description: 'Punya pertanyaan tentang StockEase? Tim kami merespons dalam 2–4 jam kerja. Kirim pesan atau jadwalkan demo langsung.',
            routeName: 'landing.contact',
        ));
    }

    private function seoProps(string $title, string $description, string $routeName): array
    {
        return [
            'seo' => [
                'title' => $title,
                'description' => $description,
                'canonical' => route($routeName),
                'ogImage' => asset('img/StockEase-Logo.png'),
            ],
        ];
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
