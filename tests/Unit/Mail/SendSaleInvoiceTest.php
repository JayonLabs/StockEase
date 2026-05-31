<?php

use App\Mail\SendSaleInvoice;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

it('renders the invoice email content', function () {
    $product = Product::factory()->create(['name' => 'Product Alpha']);
    $sale = Sale::factory()->create([
        'id' => 42,
        'payment_method' => 'cash',
        'status' => 'completed',
        'total' => 50000,
        'paid' => 60000,
        'change' => 10000,
        'customer_name' => 'Budi',
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 2,
        'price' => 25000,
    ]);

    $mailable = new SendSaleInvoice($sale);

    $mailable->assertSeeInHtml('Invoice #42');
    $mailable->assertSeeInHtml('Budi');
    $mailable->assertSeeInHtml('Product Alpha');
    $mailable->assertSeeInHtml('2');
    $mailable->assertSeeInHtml('50.000');
    $mailable->assertSeeInHtml('60.000');
    $mailable->assertSeeInHtml('10.000');
});

it('renders invoice for QRIS sale', function () {
    $product = Product::factory()->create(['name' => 'Digital Item']);
    $sale = Sale::factory()->create([
        'id' => 99,
        'payment_method' => 'qris',
        'status' => 'pending',
        'total' => 35000,
        'paid' => 35000,
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 1,
        'price' => 35000,
    ]);

    $mailable = new SendSaleInvoice($sale);

    $mailable->assertSeeInHtml('Invoice #99');
    $mailable->assertSeeInHtml('QRIS');
    $mailable->assertSeeInHtml('Menunggu Pembayaran');
    $mailable->assertSeeInHtml('Digital Item');
    $mailable->assertSeeInHtml('35.000');
});

it('renders invoice without customer name gracefully', function () {
    $product = Product::factory()->create(['name' => 'Anonymous Item']);
    $sale = Sale::factory()->create([
        'id' => 10,
        'payment_method' => 'cash',
        'status' => 'completed',
        'total' => 10000,
        'paid' => 10000,
        'change' => 0,
        'customer_name' => null,
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 1,
        'price' => 10000,
    ]);

    $mailable = new SendSaleInvoice($sale);

    $mailable->assertSeeInHtml('Invoice #10');
    $mailable->assertSeeInHtml('Tunai');
    $mailable->assertSeeInHtml('10.000');
});

it('uses correct subject with sale id', function () {
    $sale = Sale::factory()->create(['id' => 77]);

    $mailable = new SendSaleInvoice($sale);

    expect($mailable->envelope()->subject)->toBe('Invoice #77 - StockEase');
});

it('implements ShouldQueue', function () {
    $sale = Sale::factory()->create();

    $mailable = new SendSaleInvoice($sale);

    expect($mailable)->toBeInstanceOf(ShouldQueue::class);
});

it('renders with multiple sale items', function () {
    $product1 = Product::factory()->create(['name' => 'Item Satu']);
    $product2 = Product::factory()->create(['name' => 'Item Dua']);
    $sale = Sale::factory()->create([
        'id' => 50,
        'payment_method' => 'cash',
        'status' => 'completed',
        'total' => 30000,
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product1->id,
        'qty' => 2,
        'price' => 10000,
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product2->id,
        'qty' => 1,
        'price' => 10000,
    ]);

    $mailable = new SendSaleInvoice($sale);

    $mailable->assertSeeInHtml('Item Satu');
    $mailable->assertSeeInHtml('Item Dua');
    $mailable->assertSeeInHtml('30.000');
});
