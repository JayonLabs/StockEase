<?php

use App\Enums\EmailStatus;
use App\Mail\SendSaleInvoice;
use App\Models\Sale;
use App\Models\SaleEmail;
use App\Services\Sale\SaleEmailService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase&object{service: SaleEmailService} $this */
    Mail::fake();
    $this->service = new SaleEmailService;
});

it('creates a pending sale email record and queues mailable', function () {
    $sale = Sale::factory()->create(['status' => 'completed', 'total' => 10000]);

    /** @var TestCase&object{service: SaleEmailService} $this */
    $saleEmail = $this->service->sendInvoice($sale, 'customer@example.com');

    expect($saleEmail)->toBeInstanceOf(SaleEmail::class);
    expect($saleEmail->sale_id)->toBe($sale->id);
    expect($saleEmail->email)->toBe('customer@example.com');
    expect($saleEmail->status)->toBe(EmailStatus::Sent);
    expect($saleEmail->sent_at)->not->toBeNull();
});

it('updates status to sent on successful queue', function () {
    $sale = Sale::factory()->create(['status' => 'completed', 'total' => 5000]);

    /** @var TestCase&object{service: SaleEmailService} $this */
    $saleEmail = $this->service->sendInvoice($sale, 'test@example.com');

    expect($saleEmail->status)->toBe(EmailStatus::Sent);
    expect($saleEmail->sent_at)->not->toBeNull();
    expect($saleEmail->error_message)->toBeNull();
});

it('stores failed status when queue throws exception', function () {
    $sale = Sale::factory()->create(['status' => 'completed', 'total' => 5000]);
    $errorMessage = 'Connection refused';
    Mail::shouldReceive('to->queue')->andThrow(new RuntimeException($errorMessage));

    try {
        $this->service->sendInvoice($sale, 'fail@example.com');
    } catch (RuntimeException) {
        // Expected
    }

    $this->assertDatabaseHas('sale_emails', [
        'sale_id' => $sale->id,
        'email' => 'fail@example.com',
        'status' => 'failed',
        'error_message' => $errorMessage,
    ]);
});

it('handles sale with no items gracefully', function () {
    $sale = Sale::factory()->create(['status' => 'completed', 'total' => 0]);

    /** @var TestCase&object{service: SaleEmailService} $this */
    $saleEmail = $this->service->sendInvoice($sale, 'empty@example.com');

    expect($saleEmail->status)->toBe(EmailStatus::Sent);
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

it('stores correct record in database with enum status', function () {
    $sale = Sale::factory()->create(['status' => 'completed', 'total' => 75000]);

    /** @var TestCase&object{service: SaleEmailService} $this */
    $this->service->sendInvoice($sale, 'db@example.com');

    $this->assertDatabaseHas('sale_emails', [
        'sale_id' => $sale->id,
        'email' => 'db@example.com',
        'status' => 'sent',
    ]);
});

it('throws exception when mail fails after creating pending record', function () {
    $sale = Sale::factory()->create(['status' => 'completed', 'total' => 10000]);
    Mail::shouldReceive('to->queue')->andThrow(new InvalidArgumentException('Invalid address'));

    $this->expectException(InvalidArgumentException::class);

    $this->service->sendInvoice($sale, 'invalid-email');
});

it('rethrows the original exception after marking failed', function () {
    $sale = Sale::factory()->create(['status' => 'completed', 'total' => 10000]);
    $exception = new LogicException('Queue connection error');
    Mail::shouldReceive('to->queue')->andThrow($exception);

    try {
        $this->service->sendInvoice($sale, 'error@example.com');
    } catch (LogicException $caught) {
        expect($caught)->toBe($exception);
    }
});
