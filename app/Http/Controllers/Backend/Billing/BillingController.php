<?php

namespace App\Http\Controllers\Backend\Billing;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\DepositTopup;
use App\Models\DokuVaChannel;
use App\Services\DepositService;
use App\Services\Tripay\Tripay;
use App\Support\Billing;
use App\Tenancy\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    /**
     * Halaman langganan: status sekarang + pilihan paket + riwayat pembayaran.
     */
    public function index()
    {
        $tenant = Auth::user()->tenant;

        if (!$tenant) {
            abort(404, 'Tenant tidak ditemukan untuk akun ini.');
        }

        $plans = Plan::all();
        $history = Subscription::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $clientKey = config('services.midtrans.client_key');
        $isProduction = (bool) config('services.midtrans.is_production', false);
        $driver = Billing::driver();
        $dokuChannels = $driver === 'doku' ? DokuVaChannel::activeForCurrentEnv() : collect();
        $tripayChannels = $driver === 'tripay' ? \App\Models\TripayChannel::activeOrdered() : collect();

        return view('backend.billing.index', compact('tenant', 'plans', 'history', 'clientKey', 'isProduction', 'driver', 'dokuChannels', 'tripayChannels'));
    }

    /**
     * Buat transaksi langganan + Snap Token Midtrans.
     */
    public function checkout(Request $request)
    {
        // Pembelian paket dinonaktifkan sementara (menunggu Midtrans production siap).
        // Re-enable: set BILLING_PURCHASE_ENABLED=true di .env lalu `php artisan optimize`.
        if (! config('billing.purchase_enabled', false)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pembelian paket langganan segera hadir. Fitur ini sementara dinonaktifkan.',
            ], 503);
        }

        $request->validate([
            'plan'   => ['required', 'string', 'in:' . implode(',', array_keys(Plan::all()))],
            'months' => ['nullable', 'integer', 'min:1', 'max:36'],
        ]);

        $tenant = Auth::user()->tenant;
        if (!$tenant) {
            return response()->json(['status' => 'error', 'message' => 'Tenant tidak ditemukan.'], 404);
        }

        $planKey = $request->plan;

        // Paket konsultasi (Customize) tidak melalui checkout Midtrans.
        if (Plan::isContact($planKey)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Paket ' . Plan::name($planKey) . ' diaktifkan via konsultasi. Silakan hubungi kami melalui WhatsApp.',
            ], 422);
        }

        // Perpanjangan paket yang SAMA hanya dibuka H-7 sebelum masa aktif habis
        // (mencegah bypass tombol UI "Plan Saat Ini"). Upgrade ke paket lain / paket
        // yang sudah habis tetap boleh kapan saja.
        if ($tenant->plan === $planKey && $tenant->hasActiveAccess()) {
            $until = $tenant->subscription_status === 'trial' ? $tenant->trial_ends_at : $tenant->subscription_ends_at;
            if ($until && $until->gt(now()->addDays(7))) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Paket masih aktif. Perpanjangan tersedia mulai H-7 sebelum masa aktif habis (' . $until->translatedFormat('d M Y') . ').',
                ], 422);
            }
        }

        // Durasi langganan (default 1 bulan). Harga dihitung server-side dari config
        // agar tidak bisa dimanipulasi dari front-end.
        $months = (int) $request->input('months', 1);
        $amount = Plan::periodAmount($planKey, $months);
        if ($amount === null) {
            return response()->json(['status' => 'error', 'message' => 'Durasi langganan tidak valid.'], 422);
        }

        // Cashback (potongan) untuk user yang daftar via referral affiliate — hanya Basic/Enterprise.
        // Persen diatur Superadmin (default 0 = tanpa potongan). Nominal dipotong dari yang dibayar.
        $cashbackPercent = \App\Services\AffiliateService::cashbackPercentFor($tenant->id, $planKey);
        $cashbackAmount  = \App\Services\AffiliateService::cashbackAmount($tenant->id, $planKey, (int) $amount);
        $amount = max(0, (int) $amount - $cashbackAmount);

        $driver = Billing::driver();

        // Driver DOKU (Virtual Account SNAP).
        if ($driver === 'doku') {
            return $this->checkoutDoku($tenant, $planKey, (int) $amount, $months, $request->input('bank'), $cashbackPercent, $cashbackAmount);
        }

        // Driver Tripay (Closed Payment, customer pilih channel).
        if ($driver === 'tripay') {
            return $this->checkoutTripay($tenant, $planKey, (int) $amount, $months, (string) $request->input('method', ''), $cashbackPercent, $cashbackAmount);
        }

        try {
            $subscription = DB::transaction(function () use ($tenant, $planKey, $amount, $months, $cashbackPercent, $cashbackAmount) {
                $orderId = 'STK-SUB-' . strtoupper(Str::random(6)) . '-' . $tenant->id . '-' . substr((string) Str::uuid(), 0, 8);

                return Subscription::create([
                    'tenant_id'         => $tenant->id,
                    'plan'              => $planKey,
                    'amount'            => $amount,
                    'cashback_percent'  => $cashbackPercent ?: null,
                    'cashback_amount'   => $cashbackAmount ?: null,
                    'billing_period'    => (string) $months, // jumlah bulan (dipakai saat aktivasi)
                    'status'            => 'pending',
                    'midtrans_order_id' => $orderId,
                ]);
            });

            $this->configureMidtrans();

            $params = [
                'transaction_details' => [
                    'order_id'     => $subscription->midtrans_order_id,
                    'gross_amount' => (int) $amount,
                ],
                'item_details' => [[
                    'id'       => 'plan-' . $planKey,
                    'price'    => (int) $amount,
                    'quantity' => 1,
                    'name'     => 'Langganan ' . Plan::name($planKey) . ' (' . $months . ' bulan)',
                ]],
                'customer_details' => [
                    'first_name' => Auth::user()->name,
                    'email'      => Auth::user()->email,
                    'phone'      => $tenant->phone,
                ],
                'callbacks' => [
                    'finish' => route('billing.index'),
                ],
            ];

            $snapToken = \Midtrans\Snap::getSnapToken($params);
            $subscription->update(['snap_token' => $snapToken]);

            return response()->json([
                'status'     => 'success',
                'snap_token' => $snapToken,
                'order_id'   => $subscription->midtrans_order_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Subscription checkout failed: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal memproses pembayaran: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Webhook Midtrans untuk langganan (route publik, dikecualikan dari CSRF).
     */
    public function webhook(Request $request)
    {
        $serverKey = config('services.midtrans.server_key');

        // 1. Verifikasi signature (anti-pemalsuan)
        $expected = hash('sha512', $request->order_id . $request->status_code . $request->gross_amount . $serverKey);
        if (!hash_equals($expected, (string) $request->signature_key)) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $transaction = $request->transaction_status;
        $fraud       = $request->fraud_status;
        $succeeded   = ($transaction === 'capture' && $fraud === 'accept') || $transaction === 'settlement';
        $failed      = in_array($transaction, ['cancel', 'deny', 'expire'], true);

        // 2. Cari subscription (tanpa scope tenant — model ini memang lintas konteks)
        $subscription = Subscription::where('midtrans_order_id', $request->order_id)->first();
        if ($subscription) {
            if ($succeeded) {
                $this->activateSubscription($subscription, $request->payment_type);
            } elseif ($failed) {
                $subscription->update(['status' => 'failed']);
            } elseif ($transaction === 'pending') {
                $subscription->update(['status' => 'pending', 'payment_type' => $request->payment_type]);
            }

            return response()->json(['message' => 'OK']);
        }

        // 3. Bila bukan langganan, cek top-up deposit (endpoint webhook dipakai bersama)
        $topup = DepositTopup::where('midtrans_order_id', $request->order_id)->first();
        if ($topup) {
            if ($succeeded) {
                $this->activateTopup($topup, $request->payment_type);
            } elseif ($failed) {
                $topup->update(['status' => 'failed']);
            } elseif ($transaction === 'pending') {
                $topup->update(['status' => 'pending', 'payment_type' => $request->payment_type]);
            }

            return response()->json(['message' => 'OK']);
        }

        return response()->json(['message' => 'Order not found'], 404);
    }

    /**
     * Webhook DOKU (diteruskan oleh doku-gateway). Gateway sudah memverifikasi Bearer JWT;
     * di sini kita cocokkan order + NOMINAL, lalu aktivasi (idempoten via lockForUpdate).
     * Route publik, dikecualikan CSRF. Menangani langganan (STK-SUB-) & deposit (STK-DEP-).
     */
    public function dokuWebhook(Request $request)
    {
        $data = $request->all();

        // trxId DOKU = order id kita (disimpan di kolom midtrans_order_id).
        $trxId = (string) ($data['trxId']
            ?? data_get($data, 'additionalInfo.trxId')
            ?? data_get($data, 'virtualAccountData.trxId')
            ?? '');

        // Nominal dibayar (Close Amount) untuk verifikasi anti-manipulasi.
        $paidRaw = (string) (data_get($data, 'paidAmount.value')
            ?? data_get($data, 'virtualAccountData.paidAmount.value')
            ?? '0');
        $paid = (int) round((float) $paidRaw);

        // Log penuh notifikasi pertama untuk konfirmasi format riil DOKU.
        Log::info('DOKU webhook diterima', ['trxId' => $trxId, 'paid' => $paid]);

        if ($trxId === '') {
            Log::warning('DOKU webhook: trxId kosong', ['body' => $data]);
            return response()->json(['responseCode' => '2002700', 'responseMessage' => 'success']);
        }

        // Langganan?
        $subscription = Subscription::where('midtrans_order_id', $trxId)->first();
        if ($subscription) {
            if ($paid > 0 && (int) $subscription->amount !== $paid) {
                Log::warning('DOKU webhook: nominal langganan tak cocok — TIDAK diaktifkan', [
                    'trxId' => $trxId, 'expected' => $subscription->amount, 'paid' => $paid,
                ]);
            } else {
                $this->activateSubscription($subscription, 'doku_va');
            }
            return response()->json(['responseCode' => '2002700', 'responseMessage' => 'success']);
        }

        // Top-up deposit?
        $topup = DepositTopup::where('midtrans_order_id', $trxId)->first();
        if ($topup) {
            if ($paid > 0 && (int) $topup->amount !== $paid) {
                Log::warning('DOKU webhook: nominal top-up tak cocok — TIDAK dikreditkan', [
                    'trxId' => $trxId, 'expected' => $topup->amount, 'paid' => $paid,
                ]);
            } else {
                $this->activateTopup($topup, 'doku_va');
            }
            return response()->json(['responseCode' => '2002700', 'responseMessage' => 'success']);
        }

        Log::info('DOKU webhook: order tak ditemukan', ['trxId' => $trxId]);
        return response()->json(['responseCode' => '2002700', 'responseMessage' => 'success']);
    }

    /**
     * Webhook Tripay (route publik, dikecualikan CSRF). Verifikasi X-Callback-Signature
     * (HMAC-SHA256 raw body dgn private key), lalu aktivasi langganan (STK-SUB-) / deposit (STK-DEP-).
     * Idempoten via activateSubscription/activateTopup (lockForUpdate).
     */
    public function tripayWebhook(Request $request)
    {
        $tripay = new Tripay();
        $raw    = $request->getContent();               // WAJIB raw body (bukan re-encode).
        $sig    = $request->header('X-Callback-Signature');

        if (! $tripay->verifyCallbackSignature($raw, $sig)) {
            Log::warning('Tripay webhook: signature tidak valid');
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        $data        = json_decode($raw, true) ?: [];
        $merchantRef = (string) ($data['merchant_ref'] ?? '');
        $status      = strtoupper((string) ($data['status'] ?? ''));
        $paid        = (int) ($data['amount_received'] ?? $data['total_amount'] ?? $data['amount'] ?? 0);
        $method      = (string) ($data['payment_method_code'] ?? $data['payment_method'] ?? '');

        Log::info('Tripay webhook diterima', ['ref' => $merchantRef, 'status' => $status, 'paid' => $paid]);

        if ($merchantRef === '') {
            return response()->json(['success' => true]);
        }

        $succeeded = $status === 'PAID';
        $failed    = in_array($status, ['EXPIRED', 'FAILED', 'REFUND'], true);

        // Nominal transaksi yang KITA minta (di-echo balik Tripay sbg data.amount). JANGAN pakai
        // amount_received (itu sudah dipotong fee_merchant -> bisa lebih kecil & memicu false-negative).
        $expected = (int) ($data['amount'] ?? 0);

        // Langganan?
        $subscription = Subscription::where('midtrans_order_id', $merchantRef)->first();
        if ($subscription) {
            if ($succeeded) {
                if ($expected > 0 && (int) $subscription->amount !== $expected) {
                    Log::warning('Tripay webhook: nominal langganan tak cocok — TIDAK diaktifkan', [
                        'ref' => $merchantRef, 'expected' => $subscription->amount, 'amount' => $expected,
                    ]);
                } else {
                    $this->activateSubscription($subscription, 'tripay:' . $method);
                }
            } elseif ($failed) {
                // Jangan menimpa langganan yg SUDAH 'paid' (mis. REFUND / callback telat) -> tangani reversal manual.
                if ($subscription->status === 'paid') {
                    Log::warning('Tripay webhook: status ' . $status . ' untuk langganan yang SUDAH paid — perlu penanganan manual', ['ref' => $merchantRef]);
                } else {
                    $subscription->update(['status' => 'failed']);
                }
            }
            return response()->json(['success' => true]);
        }

        // Top-up deposit?
        $topup = DepositTopup::where('midtrans_order_id', $merchantRef)->first();
        if ($topup) {
            if ($succeeded) {
                if ($expected > 0 && (int) $topup->amount !== $expected) {
                    Log::warning('Tripay webhook: nominal top-up tak cocok — TIDAK dikreditkan', [
                        'ref' => $merchantRef, 'expected' => $topup->amount, 'amount' => $expected,
                    ]);
                } else {
                    $this->activateTopup($topup, 'tripay:' . $method);
                }
            } elseif ($failed) {
                // Poin yg sudah dikreditkan tidak di-debit di sini; jangan tandai 'failed' bila sudah 'paid'.
                if ($topup->status === 'paid') {
                    Log::warning('Tripay webhook: status ' . $status . ' untuk top-up yang SUDAH paid — perlu penanganan manual (refund poin)', ['ref' => $merchantRef]);
                } else {
                    $topup->update(['status' => 'failed']);
                }
            }
            return response()->json(['success' => true]);
        }

        Log::info('Tripay webhook: order tak ditemukan', ['ref' => $merchantRef]);
        return response()->json(['success' => true]);
    }

    /**
     * Checkout langganan via DOKU SNAP Virtual Account (Close Amount).
     * Membuat Subscription pending + VA, mengembalikan detail VA ke front-end.
     */
    private function checkoutDoku($tenant, string $planKey, int $amount, int $months, ?string $bank = null, float $cashbackPercent = 0, int $cashbackAmount = 0)
    {
        try {
            // Pilih channel bank DOKU (dari DokuVaChannel yang dikelola Superadmin).
            $channel = $this->resolveDokuChannel($bank);
            if (! $channel) {
                return response()->json(['status' => 'error', 'message' => 'Metode pembayaran (bank) tidak valid atau belum aktif.'], 422);
            }

            $subscription = DB::transaction(function () use ($tenant, $planKey, $amount, $months, $cashbackPercent, $cashbackAmount) {
                $orderId = 'STK-SUB-' . strtoupper(Str::random(6)) . '-' . $tenant->id . '-' . substr((string) Str::uuid(), 0, 8);
                return Subscription::create([
                    'tenant_id'         => $tenant->id,
                    'plan'              => $planKey,
                    'amount'            => $amount,
                    'cashback_percent'  => $cashbackPercent ?: null,
                    'cashback_amount'   => $cashbackAmount ?: null,
                    'billing_period'    => (string) $months,
                    'status'            => 'pending',
                    'midtrans_order_id' => $orderId,   // dipakai sebagai trxId DOKU
                ]);
            });

            $doku = new \App\Services\Doku\DokuSnap();
            $res = $doku->createVa([
                'trx_id'             => $subscription->midtrans_order_id,
                'customer_no'        => $this->dokuCustomerNo($tenant->id, $subscription->id),
                'amount'             => $amount,
                'name'               => Auth::user()->name,
                'email'              => Auth::user()->email,
                'phone'              => $tenant->phone,
                'channel'            => $channel->channel,
                'partner_service_id' => $channel->partner_service_id,
                'expiry_minutes'     => 60 * 24,
            ]);

            if (($res['responseCode'] ?? null) !== '2002700') {
                $subscription->update(['status' => 'failed']);
                Log::error('DOKU checkout createVa gagal', ['res' => $res]);
                return response()->json(['status' => 'error', 'message' => 'Gagal membuat Virtual Account DOKU: ' . ($res['responseMessage'] ?? 'unknown')], 500);
            }

            $va = $res['virtualAccountData'] ?? [];
            $subscription->update(['payment_type' => 'doku_va']);

            return response()->json([
                'status'       => 'success',
                'driver'       => 'doku',
                'order_id'     => $subscription->midtrans_order_id,
                'va_number'    => trim($va['virtualAccountNo'] ?? ''),
                'amount'       => $amount,
                'channel'      => $channel->channel,
                'bank_name'    => $channel->name,
                'expired_date' => $va['expiredDate'] ?? null,
                'how_to_pay'   => $va['additionalInfo']['howToPayPage'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('DOKU subscription checkout failed: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal memproses pembayaran DOKU: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Checkout langganan via Tripay (Closed Payment). Membuat Subscription pending,
     * meminta transaksi ke Tripay dgn channel pilihan customer, kembalikan checkout_url.
     */
    private function checkoutTripay($tenant, string $planKey, int $amount, int $months, string $method, float $cashbackPercent = 0, int $cashbackAmount = 0)
    {
        try {
            $tripay = new Tripay();
            if (! $tripay->isConfigured()) {
                return response()->json(['status' => 'error', 'message' => 'Tripay belum dikonfigurasi.'], 500);
            }
            if ($method === '' || ! \App\Models\TripayChannel::where('code', $method)->where('is_active', true)->exists()) {
                return response()->json(['status' => 'error', 'message' => 'Metode pembayaran tidak valid atau belum aktif.'], 422);
            }

            $subscription = DB::transaction(function () use ($tenant, $planKey, $amount, $months, $cashbackPercent, $cashbackAmount) {
                $orderId = 'STK-SUB-' . strtoupper(Str::random(6)) . '-' . $tenant->id . '-' . substr((string) Str::uuid(), 0, 8);
                return Subscription::create([
                    'tenant_id'         => $tenant->id,
                    'plan'              => $planKey,
                    'amount'            => $amount,
                    'cashback_percent'  => $cashbackPercent ?: null,
                    'cashback_amount'   => $cashbackAmount ?: null,
                    'billing_period'    => (string) $months,
                    'status'            => 'pending',
                    'midtrans_order_id' => $orderId,   // dipakai sebagai merchant_ref Tripay
                ]);
            });

            // Keterangan cashback pada item Tripay (bila ada).
            $itemName = 'Langganan ' . Plan::name($planKey) . ' (' . $months . ' bulan)';
            if ($cashbackAmount > 0) {
                $itemName .= ' — cashback ' . rtrim(rtrim(number_format($cashbackPercent, 2, '.', ''), '0'), '.') . '% (-Rp' . number_format($cashbackAmount, 0, ',', '.') . ')';
            }

            $res = $tripay->createClosedTransaction([
                'method'         => $method,
                'merchant_ref'   => $subscription->midtrans_order_id,
                'amount'         => $amount,
                'customer_name'  => Auth::user()->name,
                'customer_email' => Auth::user()->email ?: ('tenant' . $tenant->id . '@mooda.id'),
                'customer_phone' => $tenant->phone,
                'order_items'    => [[
                    'sku'      => 'plan-' . $planKey,
                    'name'     => $itemName,
                    'price'    => $amount,
                    'quantity' => 1,
                ]],
                'callback_url'   => url('/api/tripay-webhook'),
                'return_url'     => route('billing.index'),
            ]);

            if (! ($res['success'] ?? false) || empty($res['data']['checkout_url'])) {
                $subscription->update(['status' => 'failed']);
                Log::error('Tripay subscription checkout gagal', ['res' => $res]);
                return response()->json(['status' => 'error', 'message' => 'Gagal membuat transaksi Tripay: ' . ($res['message'] ?? 'unknown')], 500);
            }

            $subscription->update(['payment_type' => 'tripay:' . $method]);

            $d = $res['data'];
            return response()->json([
                'status'       => 'success',
                'driver'       => 'tripay',
                'order_id'     => $subscription->midtrans_order_id,
                'reference'    => $d['reference'] ?? null,
                'method'       => $method,
                'payment_name' => $d['payment_name'] ?? $method,
                'pay_code'     => $d['pay_code'] ?? null,
                'qr_url'       => $d['qr_url'] ?? null,
                'amount'       => (int) ($d['amount'] ?? $amount),
                'expired_time' => $d['expired_time'] ?? null,
                'checkout_url' => $d['checkout_url'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Tripay subscription checkout failed: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal memproses pembayaran Tripay. Silakan coba lagi.'], 500);
        }
    }

    /** customerNo DOKU: digit unik <=20 dari id tenant + id order. */
    private function dokuCustomerNo(int $tenantId, int $orderId): string
    {
        return substr(str_pad((string) $tenantId, 4, '0', STR_PAD_LEFT) . str_pad((string) $orderId, 10, '0', STR_PAD_LEFT), 0, 20);
    }

    /** Pilih channel DOKU aktif (env sekarang). Jika $bank kosong, pakai channel pertama. */
    private function resolveDokuChannel(?string $bank): ?DokuVaChannel
    {
        $channels = DokuVaChannel::activeForCurrentEnv();
        if ($bank) {
            return $channels->firstWhere('channel', $bank);
        }
        return $channels->first();
    }

    /**
     * Kreditkan poin saat top-up deposit lunas + alihkan tenant ke mode deposit.
     */
    private function activateTopup(DepositTopup $topup, ?string $paymentType): void
    {
        DB::transaction(function () use ($topup, $paymentType) {
            // Kunci baris + re-cek status DI DALAM transaksi -> idempoten & anti-race:
            // webhook Midtrans bisa dikirim berkali-kali / (settlement+capture) hampir bersamaan.
            $topup = DepositTopup::whereKey($topup->getKey())->lockForUpdate()->first();
            if (! $topup || $topup->status === 'paid') {
                return; // sudah diproses
            }

            $topup->update([
                'status'       => 'paid',
                'payment_type' => $paymentType,
                'paid_at'      => now(),
            ]);

            $tenant  = $topup->tenant;
            $service = app(DepositService::class);

            // Poin dikreditkan tanpa enforce cap (pembayaran sudah terjadi; cap ditegakkan saat checkout).
            $service->credit(
                $tenant,
                (int) $topup->points,
                (int) $topup->amount,
                $topup->midtrans_order_id,
                null,
                'Top-up deposit Rp' . number_format($topup->amount, 0, ',', '.'),
                false
            );

            // Aktifkan mode deposit (langganan bulanan, bila ada, hangus).
            $service->switchToDeposit($tenant->fresh());
        });
    }

    private function activateSubscription(Subscription $subscription, ?string $paymentType): void
    {
        $activated = DB::transaction(function () use ($subscription, $paymentType) {
            // Kunci baris + re-cek status DI DALAM transaksi -> idempoten & anti-race:
            // webhook Midtrans bisa dikirim berkali-kali / (settlement+capture) hampir bersamaan,
            // sehingga tanpa lock masa aktif bisa diperpanjang DOBEL.
            $subscription = Subscription::whereKey($subscription->getKey())->lockForUpdate()->first();
            if (! $subscription || $subscription->status === 'paid') {
                return false; // sudah diproses
            }

            $tenant = $subscription->tenant()->lockForUpdate()->first();

            // Perpanjang dari sisa masa aktif jika masih berlaku
            $base = ($tenant->subscription_ends_at && $tenant->subscription_ends_at->isFuture())
                ? $tenant->subscription_ends_at->copy()
                : now();
            // Jumlah bulan sesuai durasi yang dibeli (billing_period menyimpan angka bulan).
            $months = max(1, (int) $subscription->billing_period);
            $endsAt = $base->addMonthsNoOverflow($months);

            $subscription->update([
                'status'       => 'paid',
                'payment_type' => $paymentType,
                'paid_at'      => now(),
                'starts_at'    => now(),
                'ends_at'      => $endsAt,
            ]);

            $tenant->update([
                'plan'                 => $subscription->plan,
                'subscription_status'  => 'active',
                'subscription_ends_at' => $endsAt,
                'is_active'            => true,
                // Beralih ke mode bulanan: poin deposit (bila ada) dibekukan, tetap tersimpan.
                'billing_mode'         => 'monthly',
            ]);

            return true;
        });

        // Catat komisi affiliate SETELAH langganan benar-benar baru diaktifkan (sekali saja,
        // karena transaksi di atas idempoten). Non-fatal: tak akan menggagalkan webhook.
        if ($activated) {
            \App\Services\AffiliateService::rewardOnSubscription($subscription->refresh());
        }
    }

    private function configureMidtrans(): void
    {
        \Midtrans\Config::$serverKey    = config('services.midtrans.server_key');
        \Midtrans\Config::$isProduction = (bool) config('services.midtrans.is_production', false);
        \Midtrans\Config::$isSanitized  = true;
        \Midtrans\Config::$is3ds        = true;

        // URL notifikasi opsional (dari config/env). Jika kosong, Midtrans memakai
        // Notification URL yang diset di dashboard Midtrans (arahkan ke /api/subscription-webhook).
        $notifyUrl = config('services.midtrans.notify_url');
        if (!empty($notifyUrl)) {
            \Midtrans\Config::$overrideNotifUrl = $notifyUrl;
        }
    }
}
