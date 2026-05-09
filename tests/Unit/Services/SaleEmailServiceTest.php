<?php

use App\Mail\SendSaleInvoice;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleEmail;
use App\Models\SaleItem;
use App\Services\Sale\SaleEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase&object{service: SaleEmailService} $this */
    Mail::fake();
    $this->service = new SaleEmailService;
});

it('creates a pending sale email record and queues mailable', function () {
    $product = Product::factory()->create();
    $sale = Sale::factory()->create([
        'status' => 'completed',
        'payment_method' => 'cash',
        'total' => 10000,
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 2,
        'price' => 5000,
    ]);

    /** @var TestCase&object{service: SaleEmailService} $this */
    $saleEmail = $this->service->sendInvoice($sale, 'customer@example.com');

    expect($saleEmail)->toBeInstanceOf(SaleEmail::class);
    expect($saleEmail->sale_id)->toBe($sale->id);
    expect($saleEmail->email)->toBe('customer@example.com');
    expect($saleEmail->status)->toBe('sent');
    expect($saleEmail->sent_at)->not->toBeNull();

    Mail::assertQueued(SendSaleInvoice::class, function ($mail) use ($sale) {
        return $mail->sale->id === $sale->id;
    });
});

it('updates status to sent on successful queue', function () {
    $sale = Sale::factory()->create(['status' => 'completed', 'total' => 5000]);

    /** @var TestCase&object{service: SaleEmailService} $this */
    $saleEmail = $this->service->sendInvoice($sale, 'test@example.com');

    expect($saleEmail->status)->toBe('sent');
    expect($saleEmail->sent_at)->not->toBeNull();
    expect($saleEmail->error_message)->toBeNull();
});

it('handles sale with no items gracefully', function () {
    $sale = Sale::factory()->create(['status' => 'completed', 'total' => 0]);

    /** @var TestCase&object{service: SaleEmailService} $this */
    $saleEmail = $this->service->sendInvoice($sale, 'empty@example.com');

    expect($saleEmail->status)->toBe('sent');
    Mail::assertQueued(SendSaleInvoice::class);
});

it('queues mail to the correct recipient', function () {
    $sale = Sale::factory()->create(['status' => 'completed', 'total' => 10000]);

    /** @var TestCase&object{service: SaleEmailService} $this */
    $this->service->sendInvoice($sale, 'specific@example.com');

    Mail::assertQueued(SendSaleInvoice::class, function ($mail) {
        return $mail->hasTo('specific@example.com');
    });
});

it('includes sale id in email subject', function () {
    $sale = Sale::factory()->create(['status' => 'completed', 'total' => 10000]);

    /** @var TestCase&object{service: SaleEmailService} $this */
    $this->service->sendInvoice($sale, 'customer@example.com');

    Mail::assertQueued(SendSaleInvoice::class, function ($mail) use ($sale) {
        expect($mail->envelope()->subject)->toContain("#{$sale->id}");

        return true;
    });
});

it('stores correct record in database', function () {
    $sale = Sale::factory()->create(['status' => 'completed', 'total' => 75000]);

    /** @var TestCase&object{service: SaleEmailService} $this */
    $this->service->sendInvoice($sale, 'db@example.com');

    $this->assertDatabaseHas('sale_emails', [
        'sale_id' => $sale->id,
        'email' => 'db@example.com',
        'status' => 'sent',
    ]);
});
