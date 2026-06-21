<?php

use App\Http\Controllers\Landing\LandingController;
use App\Http\Controllers\Landing\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/pricing', [LandingController::class, 'pricing'])->name('landing.pricing');
Route::get('/why-us', [LandingController::class, 'why'])->name('landing.why');
Route::get('/testimonials', [LandingController::class, 'testimonials'])->name('landing.testimonials');
Route::get('/contact', [LandingController::class, 'contact'])->name('landing.contact');
Route::post('/contact', [LandingController::class, 'sendContact'])->name('landing.contact.send');
