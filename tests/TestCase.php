<?php

namespace Tests;

use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // FAIL-SAFE: Memastikan database yang digunakan adalah 'testing'
        if (app()->environment() !== 'testing') {
            // Cobalah untuk memaksa environment ke testing jika belum
        }

        // Jika terdeteksi 'stockease', hentikan test seketika.
        if (DB::connection()->getDatabaseName() === 'stockease') {
            throw new \Exception('BAHAYA: Testing mencoba mengakses database utama (stockease)! Koneksi dihentikan untuk melindungi data Anda.');
        }

        $this->seed(RoleAndPermissionSeeder::class);
    }
}
