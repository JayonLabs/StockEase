<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Generate the XML sitemap for all public landing pages.
     *
     * Lists each landing URL with its crawl priority and change frequency
     * so that search engine bots can efficiently discover and re-index pages.
     * Returns a plain XML response with the correct Content-Type header so
     * crawlers parse it as a sitemap rather than an HTML page.
     */
    public function __invoke(): Response
    {
        $urls = [
            ['loc' => route('landing'),              'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => route('landing.pricing'),      'priority' => '0.9', 'changefreq' => 'weekly'],
            ['loc' => route('landing.why'),          'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => route('landing.testimonials'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => route('landing.contact'),      'priority' => '0.6', 'changefreq' => 'monthly'],
        ];

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
