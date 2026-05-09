<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tambahkan kolom unit_id
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('barcode')->constrained('units')->onDelete('restrict');
            $table->renameColumn('unit', 'unit_old');
        });

        // 2. Data Migration: Pindahkan data dari UnitEnum ke tabel units baru
        $units = [
            'pcs' => 'Buah (pcs)',
            'box' => 'Kotak (box)',
            'pack' => 'Bungkus (pack)',
            'set' => 'Set',
            'botol' => 'Botol',
            'liter' => 'Liter',
            'ml' => 'Mililiter (ml)',
            'kg' => 'Kilogram (kg)',
            'gram' => 'Gram',
            'meter' => 'Meter',
            'cm' => 'Sentimeter (cm)',
            'roll' => 'Gulungan (roll)',
            'lusin' => 'Lusin (12 pcs)',
            'rim' => 'Rim (500 lembar)',
            'karung' => 'Karung',
        ];

        foreach ($units as $key => $name) {
            $existing = DB::table('units')->where('short_name', $key)->first();

            if ($existing) {
                DB::table('units')->where('id', $existing->id)->update([
                    'name' => $name,
                    'slug' => Str::slug($name),
                ]);
            } else {
                DB::table('units')->insert([
                    'short_name' => $key,
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 3. Update products.unit_id berdasarkan products.unit_old
        $products = DB::table('products')->get();
        foreach ($products as $product) {
            $unit = DB::table('units')->where('short_name', $product->unit_old)->first();
            if ($unit) {
                DB::table('products')->where('id', $product->id)->update(['unit_id' => $unit->id]);
            }
        }

        // 4. Hapus kolom unit_old dan buat unit_id menjadi non-nullable
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('unit_old');
            $table->foreignId('unit_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('unit')->after('barcode')->nullable();
        });

        // Restore data
        $products = DB::table('products')->get();
        foreach ($products as $product) {
            $unit = DB::table('units')->where('id', $product->unit_id)->first();
            if ($unit) {
                DB::table('products')->where('id', $product->id)->update(['unit' => $unit->short_name]);
            }
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn('unit_id');
        });
    }
};
