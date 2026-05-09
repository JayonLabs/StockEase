<?php

use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PromotionType;
use App\Enums\Role;
use App\Enums\SaleReturnStatus;
use App\Enums\SaleReturnType;
use App\Enums\SaleStatus;
use App\Enums\ShiftStatus;
use App\Enums\StockLogType;
use Tests\TestCase;

uses(TestCase::class);

// ============================================================
// Role Enum
// ============================================================

describe('Role enum', function () {
    it('has three cases', function () {
        expect(Role::cases())->toHaveCount(3);
    });

    it('has correct values', function () {
        expect(Role::Admin->value)->toBe('admin');
        expect(Role::Cashier->value)->toBe('cashier');
        expect(Role::Warehouse->value)->toBe('warehouse');
    });

    it('has correct labels', function () {
        expect(Role::Admin->label())->toBe('Administrator');
        expect(Role::Cashier->label())->toBe('Kasir');
        expect(Role::Warehouse->label())->toBe('Gudang');
    });

    it('tryFrom returns enum for valid value', function () {
        expect(Role::tryFrom('admin'))->toBe(Role::Admin);
        expect(Role::tryFrom('cashier'))->toBe(Role::Cashier);
    });

    it('tryFrom returns null for invalid value', function () {
        expect(Role::tryFrom('superadmin'))->toBeNull();
    });

    it('can be converted to array of values', function () {
        $values = array_column(Role::cases(), 'value');

        expect($values)->toContain('admin', 'cashier', 'warehouse');
    });
});

// ============================================================
// SaleStatus Enum
// ============================================================

describe('SaleStatus enum', function () {
    it('has four cases', function () {
        expect(SaleStatus::cases())->toHaveCount(4);
    });

    it('has correct values', function () {
        expect(SaleStatus::Draft->value)->toBe('draft');
        expect(SaleStatus::Pending->value)->toBe('pending');
        expect(SaleStatus::Completed->value)->toBe('completed');
        expect(SaleStatus::Canceled->value)->toBe('canceled');
    });

    it('has correct labels', function () {
        expect(SaleStatus::Draft->label())->toBe('Draft');
        expect(SaleStatus::Pending->label())->toBe('Menunggu Pembayaran');
        expect(SaleStatus::Completed->label())->toBe('Selesai');
        expect(SaleStatus::Canceled->label())->toBe('Dibatalkan');
    });
});

// ============================================================
// StockLogType Enum
// ============================================================

describe('StockLogType enum', function () {
    it('has three cases', function () {
        expect(StockLogType::cases())->toHaveCount(3);
    });

    it('has correct values', function () {
        expect(StockLogType::In->value)->toBe('in');
        expect(StockLogType::Out->value)->toBe('out');
        expect(StockLogType::Adjust->value)->toBe('adjust');
    });

    it('has correct labels', function () {
        expect(StockLogType::In->label())->toBe('Masuk');
        expect(StockLogType::Out->label())->toBe('Keluar');
        expect(StockLogType::Adjust->label())->toBe('Penyesuaian');
    });
});

// ============================================================
// PromotionType Enum
// ============================================================

describe('PromotionType enum', function () {
    it('has three cases', function () {
        expect(PromotionType::cases())->toHaveCount(3);
    });

    it('has correct values', function () {
        expect(PromotionType::Percentage->value)->toBe('percentage');
        expect(PromotionType::Nominal->value)->toBe('nominal');
        expect(PromotionType::Bogo->value)->toBe('bogo');
    });

    it('has correct labels', function () {
        expect(PromotionType::Percentage->label())->toBe('Persentase');
        expect(PromotionType::Nominal->label())->toBe('Nominal');
        expect(PromotionType::Bogo->label())->toBe('Buy One Get One');
    });

    it('can compare using value strings', function () {
        expect(PromotionType::Percentage->value === 'percentage')->toBeTrue();
        expect(PromotionType::Bogo->value === 'nominal')->toBeFalse();
    });
});

// ============================================================
// SaleReturnType Enum
// ============================================================

describe('SaleReturnType enum', function () {
    it('has two cases', function () {
        expect(SaleReturnType::cases())->toHaveCount(2);
    });

    it('has correct values', function () {
        expect(SaleReturnType::Refund->value)->toBe('refund');
        expect(SaleReturnType::Exchange->value)->toBe('exchange');
    });

    it('has correct labels', function () {
        expect(SaleReturnType::Refund->label())->toBe('Pengembalian Uang');
        expect(SaleReturnType::Exchange->label())->toBe('Tukar Barang');
    });

    it('can be used in conditional logic with value', function () {
        $type = SaleReturnType::Refund;

        $isRefund = $type->value === 'refund';
        expect($isRefund)->toBeTrue();

        $isExchange = $type->value === 'exchange';
        expect($isExchange)->toBeFalse();
    });
});

// ============================================================
// SaleReturnStatus Enum
// ============================================================

describe('SaleReturnStatus enum', function () {
    it('has two cases', function () {
        expect(SaleReturnStatus::cases())->toHaveCount(2);
    });

    it('has correct values', function () {
        expect(SaleReturnStatus::Completed->value)->toBe('completed');
        expect(SaleReturnStatus::Canceled->value)->toBe('canceled');
    });

    it('has correct labels', function () {
        expect(SaleReturnStatus::Completed->label())->toBe('Selesai');
        expect(SaleReturnStatus::Canceled->label())->toBe('Dibatalkan');
    });

    it('isCompleted returns true only for Completed', function () {
        expect(SaleReturnStatus::Completed->isCompleted())->toBeTrue();
        expect(SaleReturnStatus::Canceled->isCompleted())->toBeFalse();
    });

    it('isCanceled returns true only for Canceled', function () {
        expect(SaleReturnStatus::Completed->isCanceled())->toBeFalse();
        expect(SaleReturnStatus::Canceled->isCanceled())->toBeTrue();
    });
});

// ============================================================
// ShiftStatus Enum
// ============================================================

describe('ShiftStatus enum', function () {
    it('has two cases', function () {
        expect(ShiftStatus::cases())->toHaveCount(2);
    });

    it('has correct values', function () {
        expect(ShiftStatus::Open->value)->toBe('open');
        expect(ShiftStatus::Closed->value)->toBe('closed');
    });

    it('has correct labels', function () {
        expect(ShiftStatus::Open->label())->toBe('Buka');
        expect(ShiftStatus::Closed->label())->toBe('Tutup');
    });

    it('isOpen returns true only for Open', function () {
        expect(ShiftStatus::Open->isOpen())->toBeTrue();
        expect(ShiftStatus::Closed->isOpen())->toBeFalse();
    });
});

// ============================================================
// PaymentMethod Enum
// ============================================================

describe('PaymentMethod enum', function () {
    it('has three cases', function () {
        expect(PaymentMethod::cases())->toHaveCount(3);
    });

    it('has correct values', function () {
        expect(PaymentMethod::Cash->value)->toBe('cash');
        expect(PaymentMethod::Qris->value)->toBe('qris');
        expect(PaymentMethod::Pending->value)->toBe('pending');
    });

    it('has correct labels', function () {
        expect(PaymentMethod::Cash->label())->toBe('Tunai');
        expect(PaymentMethod::Qris->label())->toBe('QRIS');
        expect(PaymentMethod::Pending->label())->toBe('Menunggu');
    });
});

// ============================================================
// PaymentStatus Enum
// ============================================================

describe('PaymentStatus enum', function () {
    it('has nine cases', function () {
        expect(PaymentStatus::cases())->toHaveCount(9);
    });

    it('has correct values', function () {
        expect(PaymentStatus::Settlement->value)->toBe('settlement');
        expect(PaymentStatus::Success->value)->toBe('success');
        expect(PaymentStatus::Capture->value)->toBe('capture');
        expect(PaymentStatus::Pending->value)->toBe('pending');
        expect(PaymentStatus::Deny->value)->toBe('deny');
        expect(PaymentStatus::Expired->value)->toBe('expired');
        expect(PaymentStatus::Cancel->value)->toBe('cancel');
        expect(PaymentStatus::Challenge->value)->toBe('challenge');
        expect(PaymentStatus::Unknown->value)->toBe('unknown');
    });

    it('has correct labels', function () {
        expect(PaymentStatus::Settlement->label())->toBe('Selesai');
        expect(PaymentStatus::Success->label())->toBe('Sukses');
        expect(PaymentStatus::Pending->label())->toBe('Menunggu');
        expect(PaymentStatus::Deny->label())->toBe('Ditolak');
        expect(PaymentStatus::Expired->label())->toBe('Kedaluwarsa');
        expect(PaymentStatus::Cancel->label())->toBe('Dibatalkan');
    });

    it('isPaid returns true for settlement, success, capture', function () {
        expect(PaymentStatus::Settlement->isPaid())->toBeTrue();
        expect(PaymentStatus::Success->isPaid())->toBeTrue();
        expect(PaymentStatus::Capture->isPaid())->toBeTrue();
    });

    it('isPaid returns false for non-completed statuses', function () {
        expect(PaymentStatus::Pending->isPaid())->toBeFalse();
        expect(PaymentStatus::Deny->isPaid())->toBeFalse();
        expect(PaymentStatus::Expired->isPaid())->toBeFalse();
        expect(PaymentStatus::Cancel->isPaid())->toBeFalse();
        expect(PaymentStatus::Challenge->isPaid())->toBeFalse();
        expect(PaymentStatus::Unknown->isPaid())->toBeFalse();
    });

    it('isPaid matches old in_array string logic', function () {
        $paidValues = ['settlement', 'success', 'capture'];

        foreach (PaymentStatus::cases() as $status) {
            $expected = in_array($status->value, $paidValues);
            expect($status->isPaid())->toBe($expected);
        }
    });
});

// ============================================================
// PaymentGateway Enum
// ============================================================

describe('PaymentGateway enum', function () {
    it('has one case', function () {
        expect(PaymentGateway::cases())->toHaveCount(1);
    });

    it('has correct value', function () {
        expect(PaymentGateway::Midtrans->value)->toBe('midtrans');
    });

    it('has correct label', function () {
        expect(PaymentGateway::Midtrans->label())->toBe('Midtrans');
    });
});

// ============================================================
// PaymentType Enum
// ============================================================

describe('PaymentType enum', function () {
    it('has one case', function () {
        expect(PaymentType::cases())->toHaveCount(1);
    });

    it('has correct value', function () {
        expect(PaymentType::Qris->value)->toBe('qris');
    });

    it('has correct label', function () {
        expect(PaymentType::Qris->label())->toBe('QRIS');
    });
});

// ============================================================
// Enum cross-compatibility
// ============================================================

describe('Enum cross-compatibility', function () {
    it('all enums are string-backed', function () {
        $enums = [
            Role::class,
            SaleStatus::class,
            StockLogType::class,
            PromotionType::class,
            SaleReturnType::class,
            SaleReturnStatus::class,
            ShiftStatus::class,
            PaymentMethod::class,
            PaymentStatus::class,
            PaymentGateway::class,
            PaymentType::class,
        ];

        foreach ($enums as $enum) {
            $reflection = new ReflectionEnum($enum);
            expect($reflection->isBacked())->toBeTrue()
                ->and($reflection->getBackingType()?->getName())->toBe('string');
        }
    });

    it('all enum labels match known conventions', function () {
        // Every case must have a non-empty label
        foreach (Role::cases() as $case) {
            expect($case->label())->not->toBeEmpty();
        }
        foreach (SaleStatus::cases() as $case) {
            expect($case->label())->not->toBeEmpty();
        }
        foreach (PaymentStatus::cases() as $case) {
            expect($case->label())->not->toBeEmpty();
        }
    });

    it('PaymentStatus isPaid covers the same values as old string comparison', function () {
        $oldLogic = ['settlement', 'success', 'capture'];
        $enumPaid = array_column(
            array_filter(PaymentStatus::cases(), fn ($s) => $s->isPaid()),
            'value'
        );

        sort($oldLogic);
        sort($enumPaid);

        expect($enumPaid)->toBe($oldLogic);
    });
});
