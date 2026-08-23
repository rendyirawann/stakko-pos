<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel untuk AI Assistant & AI Prediksi.
 *
 * Riwayat percakapan disimpan per TENANT dan per USER. Bukan sekadar kenyamanan:
 * jawaban AI berisi angka penjualan, jadi riwayatnya adalah data keuangan tenant
 * dan tidak boleh terlihat lintas tenant.
 *
 * `ai_usage_daily` menjaga kuota. Groq free tier dibatasi untuk SELURUH akun,
 * bukan per tenant — tanpa pagar per tenant, satu toko yang aktif di jam makan
 * siang bisa menghabiskan kuota 13 tenant lainnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('tenant_id')->index();
            $t->uuid('user_id')->index();
            $t->string('kind', 20)->default('assistant');   // assistant | prediksi
            $t->string('title', 200)->nullable();
            $t->timestamp('last_message_at')->nullable();
            $t->timestamps();

            $t->index(['tenant_id', 'user_id', 'kind']);
        });

        Schema::create('ai_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $t->foreignId('tenant_id')->index();
            $t->string('role', 20);                 // user | assistant | tool
            $t->text('content')->nullable();
            // Jejak sumber jawaban: fungsi database mana yang dipanggil, atau
            // apakah jawabannya dari pencarian web. Dipakai untuk menampilkan
            // "sumber" di UI supaya angka bisa ditelusuri, bukan dipercaya buta.
            $t->json('sources')->nullable();
            $t->string('brain', 20)->nullable();    // database | web | none
            $t->unsignedInteger('tokens_in')->default(0);
            $t->unsignedInteger('tokens_out')->default(0);
            $t->unsignedInteger('ms')->default(0);
            $t->timestamps();

            $t->index(['conversation_id', 'id']);
        });

        Schema::create('ai_usage_daily', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->index();
            $t->date('day');
            $t->unsignedInteger('messages')->default(0);
            $t->unsignedInteger('tokens_in')->default(0);
            $t->unsignedInteger('tokens_out')->default(0);
            $t->timestamps();

            $t->unique(['tenant_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_usage_daily');
        Schema::dropIfExists('ai_conversations');
    }
};
