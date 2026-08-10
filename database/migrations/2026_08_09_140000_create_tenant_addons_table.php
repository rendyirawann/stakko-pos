<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADD-ON FITUR PER TENANT.
 *
 * Sampai sekarang fitur hanya bisa dibuka dengan menaikkan paket: tenant paket
 * Basic yang cuma butuh SATU fitur tambahan terpaksa pindah ke paket yang jauh
 * lebih mahal. Tabel ini membuka jalur ketiga — membeli satu modul saja, dengan
 * masa berlaku dan harganya sendiri, tanpa mengubah paketnya.
 *
 * Dibuat sebagai tabel tersendiri (bukan kolom JSON di `tenants`) karena add-on
 * punya periode, harga, dan status pembayaran — persis seperti langganan. Kalau
 * ditempel sebagai kolom, riwayatnya hilang setiap kali diperpanjang.
 *
 * `allowed_roles` menjawab kebutuhan yang berbeda dari sekadar "fitur aktif":
 * ada tenant yang ingin modulnya berjalan untuk seluruh transaksi (mis. stok
 * terpotong tiap penjualan) tetapi LAYARNYA hanya boleh dibuka pemilik. Kosong =
 * semua peran yang berhak menurut izin biasa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_addons', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('tenant_id')->index();
            $t->string('module', 60);                 // mis. inventory_hpp
            $t->string('label', 120);                 // nama yang dibaca orang
            $t->decimal('price_per_month', 15, 2)->default(0);
            $t->unsignedSmallInteger('months')->default(1);
            $t->decimal('amount', 15, 2)->default(0); // price_per_month x months
            $t->string('status', 20)->default('active'); // active | pending | expired | cancelled
            $t->date('starts_at')->nullable();
            $t->date('ends_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->json('allowed_roles')->nullable();    // null = semua peran yang berhak
            $t->string('note', 255)->nullable();
            $t->uuid('created_by')->nullable();       // superadmin yang menambahkan
            $t->timestamps();

            $t->index(['tenant_id', 'module', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_addons');
    }
};
