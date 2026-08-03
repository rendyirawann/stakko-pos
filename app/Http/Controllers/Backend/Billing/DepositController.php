<?php

namespace App\Http\Controllers\Backend\Billing;

use App\Http\Controllers\Controller;
use App\Models\DepositTopup;
use App\Models\DepositTransaction;
use App\Models\DokuVaChannel;
use App\Services\DepositService;
use App\Services\Tripay\Tripay;
use App\Support\Billing;
use App\Tenancy\DepositConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DepositController extends Controller
{
    public function __construct(private DepositService $deposit)
    {
    }

    /** Halaman plan deposit: saldo poin, pilihan top-up, aturan, riwayat. */
    public function index()
    {
        $tenant = Auth::user()->tenant;
        if (!$tenant) {
            abort(404, 'Tenant tidak ditemukan untuk akun ini.');
        }

        $tiers   = $this->deposit->tierOptions($tenant);
        $history = DepositTransaction::where('tenant_id', $tenant->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('backend.billing.deposit', [
            'tenant'           => $tenant,
            'tiers'            => $tiers,
            'history'          => $history,
            'fee'              => DepositConfig::feePerTransaction(),
            'maxPoints'        => DepositConfig::maxPoints(),
            'minDeposit'       => DepositConfig::minDeposit(),
            'expiryDays'       => DepositConfig::expiryDays(),
            'purchaseEnabled'  => (bool) config('billing.purchase_enabled', false),
            'maintenanceText'  => config('billing.maintenance_text', 'Segera hadir.'),
            'clientKey'        => config('services.midtrans.client_key'),
            'isProduction'     => (bool) config('services.midtrans.is_production', false),
            'driver'           => Billing::driver(),
            'dokuChannels'     => Billing::driver() === 'doku' ? DokuVaChannel::activeForCurrentEnv() : collect(),
            'tripayChannels'   => Billing::driver() === 'tripay' ? \App\Models\TripayChannel::activeOrdered() : collect(),
            'monthlyActive'    => $tenant->monthlyActive(),
            'needsInitial'     => $this->deposit->needsInitialTopup($tenant),
            'initialTopup'     => DepositConfig::initialTopup(),
            'initialPoints'    => (int) DepositConfig::pointsForTopup(DepositConfig::initialTopup()),
            'minTopup'         => DepositConfig::minDeposit(),
            'manualWa'         => DepositConfig::manualWa(),
            'manualBank'       => DepositConfig::manualBank(),
        ]);
    }

    /** Buat transaksi top-up + Snap Token Midtrans. Cap ditegakkan di sini. */
    public function checkout(Request $request)
    {
        if (! config('billing.purchase_enabled', false)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Top-up deposit sementara dinonaktifkan. ' . config('billing.maintenance_text', ''),
            ], 503);
        }

        $tenant = Auth::user()->tenant;
        if (!$tenant) {
            return response()->json(['status' => 'error', 'message' => 'Tenant tidak ditemukan.'], 404);
        }

        $amount = (int) $request->input('amount', 0);

        // Aktivasi: akun deposit baru WAJIB memilih paket top-up awal (mis. Rp50.000).
        if ($this->deposit->needsInitialTopup($tenant)) {
            $initial = DepositConfig::initialTopup();
            if ($amount !== $initial) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Aktivasi plan deposit mewajibkan top-up awal Rp' . number_format($initial, 0, ',', '.')
                        . ' (dapat ' . number_format((int) DepositConfig::pointsForTopup($initial), 0, ',', '.') . ' saldo). Silakan pilih paket tersebut.',
                ], 422);
            }
            $points = DepositConfig::pointsForTopup($amount);
        } else {
            // Hanya nominal paket/tier aktif yang diterima (tidak ada nominal bebas).
            $points = DepositConfig::pointsFor($amount);
        }

        if ($points === null) {
            return response()->json(['status' => 'error', 'message' => 'Nominal top-up tidak valid. Silakan pilih paket yang tersedia.'], 422);
        }

        // Cek batas maksimum saldo poin (null = tanpa batas -> selalu lolos).
        if (! $this->deposit->canTopUp($tenant, $points)) {
            $opt = $this->deposit->tierOptions($tenant);
            $msg = $opt['any_fits']
                ? 'Top-up ini akan melebihi batas maksimum saldo (Rp' . number_format($opt['max'], 0, ',', '.')
                    . '). Pilih paket lebih kecil (maks yang muat: Rp' . number_format($opt['recommended'], 0, ',', '.') . ').'
                : 'Saldo Anda sudah mendekati batas maksimum (Rp' . number_format($opt['max'], 0, ',', '.')
                    . '). Tidak ada paket yang muat saat ini. Pakai saldo dulu, lalu top-up lagi.';
            return response()->json(['status' => 'error', 'message' => $msg], 422);
        }

        $driver = Billing::driver();

        // Driver DOKU (Virtual Account SNAP).
        if ($driver === 'doku') {
            return $this->checkoutDoku($tenant, (int) $amount, (int) $points, $request->input('bank'));
        }

        // Driver Tripay (Closed Payment, customer pilih channel).
        if ($driver === 'tripay') {
            return $this->checkoutTripay($tenant, (int) $amount, (int) $points, (string) $request->input('method', ''));
        }

        try {
            $topup = DB::transaction(function () use ($tenant, $amount, $points) {
                $orderId = 'STK-DEP-' . strtoupper(Str::random(6)) . '-' . $tenant->id . '-' . substr((string) Str::uuid(), 0, 8);

                return DepositTopup::create([
                    'tenant_id'         => $tenant->id,
                    'amount'            => $amount,
                    'points'            => $points,
                    'status'            => 'pending',
                    'midtrans_order_id' => $orderId,
                ]);
            });

            $this->configureMidtrans();

            $params = [
                'transaction_details' => [
                    'order_id'     => $topup->midtrans_order_id,
                    'gross_amount' => $amount,
                ],
                'item_details' => [[
                    'id'       => 'deposit-' . $amount,
                    'price'    => $amount,
                    'quantity' => 1,
                    'name'     => 'Top-up Deposit (' . number_format($points, 0, ',', '.') . ' saldo)',
                ]],
                'customer_details' => [
                    'first_name' => Auth::user()->name,
                    'email'      => Auth::user()->email,
                    'phone'      => $tenant->phone,
                ],
                'callbacks' => [
                    'finish' => route('deposit.index'),
                ],
            ];

            $snapToken = \Midtrans\Snap::getSnapToken($params);
            $topup->update(['snap_token' => $snapToken]);

            return response()->json([
                'status'     => 'success',
                'snap_token' => $snapToken,
                'order_id'   => $topup->midtrans_order_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Deposit top-up checkout failed: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal memproses top-up: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Beralih ke plan DEPOSIT. Jika sedang berlangganan bulanan aktif, langganan HANGUS.
     */
    public function switchToDeposit(Request $request)
    {
        $tenant = Auth::user()->tenant;
        if (!$tenant) {
            abort(404, 'Tenant tidak ditemukan.');
        }

        if ($tenant->isDepositMode()) {
            return redirect()->route('deposit.index')->with('info', 'Akun Anda sudah memakai plan deposit.');
        }

        $this->deposit->switchToDeposit($tenant);

        return redirect()->route('deposit.index')->with('success', 'Berhasil beralih ke plan deposit. Saldo Anda kini aktif kembali. Langganan bulanan (jika ada) telah dihentikan.');
    }

    private function configureMidtrans(): void
    {
        \Midtrans\Config::$serverKey    = config('services.midtrans.server_key');
        \Midtrans\Config::$isProduction = (bool) config('services.midtrans.is_production', false);
        \Midtrans\Config::$isSanitized  = true;
        \Midtrans\Config::$is3ds        = true;

        $notifyUrl = config('services.midtrans.notify_url');
        if (!empty($notifyUrl)) {
            \Midtrans\Config::$overrideNotifUrl = $notifyUrl;
        }
    }

    /**
     * Checkout top-up deposit via DOKU SNAP Virtual Account (Close Amount).
     * Membuat DepositTopup pending + VA, mengembalikan detail VA ke front-end.
     */
    private function checkoutDoku($tenant, int $amount, int $points, ?string $bank = null)
    {
        try {
            $channel = DokuVaChannel::activeForCurrentEnv();
            $channel = $bank ? $channel->firstWhere('channel', $bank) : $channel->first();
            if (! $channel) {
                return response()->json(['status' => 'error', 'message' => 'Metode pembayaran (bank) tidak valid atau belum aktif.'], 422);
            }

            $topup = DB::transaction(function () use ($tenant, $amount, $points) {
                $orderId = 'STK-DEP-' . strtoupper(Str::random(6)) . '-' . $tenant->id . '-' . substr((string) Str::uuid(), 0, 8);
                return DepositTopup::create([
                    'tenant_id'         => $tenant->id,
                    'amount'            => $amount,
                    'points'            => $points,
                    'status'            => 'pending',
                    'midtrans_order_id' => $orderId,   // dipakai sebagai trxId DOKU
                ]);
            });

            $doku = new \App\Services\Doku\DokuSnap();
            $res = $doku->createVa([
                'trx_id'             => $topup->midtrans_order_id,
                'customer_no'        => substr(str_pad((string) $tenant->id, 4, '0', STR_PAD_LEFT) . str_pad((string) $topup->id, 10, '0', STR_PAD_LEFT), 0, 20),
                'amount'             => $amount,
                'name'               => Auth::user()->name,
                'email'              => Auth::user()->email,
                'phone'              => $tenant->phone,
                'channel'            => $channel->channel,
                'partner_service_id' => $channel->partner_service_id,
                'expiry_minutes'     => 60 * 24,
            ]);

            if (($res['responseCode'] ?? null) !== '2002700') {
                $topup->update(['status' => 'failed']);
                Log::error('DOKU deposit createVa gagal', ['res' => $res]);
                return response()->json(['status' => 'error', 'message' => 'Gagal membuat Virtual Account DOKU: ' . ($res['responseMessage'] ?? 'unknown')], 500);
            }

            $va = $res['virtualAccountData'] ?? [];
            $topup->update(['payment_type' => 'doku_va']);

            return response()->json([
                'status'       => 'success',
                'driver'       => 'doku',
                'order_id'     => $topup->midtrans_order_id,
                'va_number'    => trim($va['virtualAccountNo'] ?? ''),
                'amount'       => $amount,
                'channel'      => $channel->channel,
                'bank_name'    => $channel->name,
                'expired_date' => $va['expiredDate'] ?? null,
                'how_to_pay'   => $va['additionalInfo']['howToPayPage'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('DOKU deposit checkout failed: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal memproses top-up DOKU: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Checkout top-up deposit via Tripay (Closed Payment). Membuat DepositTopup pending,
     * meminta transaksi ke Tripay dgn channel pilihan customer, kembalikan checkout_url.
     */
    private function checkoutTripay($tenant, int $amount, int $points, string $method)
    {
        try {
            $tripay = new Tripay();
            if (! $tripay->isConfigured()) {
                return response()->json(['status' => 'error', 'message' => 'Tripay belum dikonfigurasi.'], 500);
            }
            if ($method === '' || ! \App\Models\TripayChannel::where('code', $method)->where('is_active', true)->exists()) {
                return response()->json(['status' => 'error', 'message' => 'Metode pembayaran tidak valid atau belum aktif.'], 422);
            }

            $topup = DB::transaction(function () use ($tenant, $amount, $points) {
                $orderId = 'STK-DEP-' . strtoupper(Str::random(6)) . '-' . $tenant->id . '-' . substr((string) Str::uuid(), 0, 8);
                return DepositTopup::create([
                    'tenant_id'         => $tenant->id,
                    'amount'            => $amount,
                    'points'            => $points,
                    'status'            => 'pending',
                    'midtrans_order_id' => $orderId,   // dipakai sebagai merchant_ref Tripay
                ]);
            });

            $res = $tripay->createClosedTransaction([
                'method'         => $method,
                'merchant_ref'   => $topup->midtrans_order_id,
                'amount'         => $amount,
                'customer_name'  => Auth::user()->name,
                'customer_email' => Auth::user()->email ?: ('tenant' . $tenant->id . '@mooda.id'),
                'customer_phone' => $tenant->phone,
                'order_items'    => [[
                    'sku'      => 'deposit-' . $amount,
                    'name'     => 'Top-up Deposit (' . number_format($points, 0, ',', '.') . ' saldo)',
                    'price'    => $amount,
                    'quantity' => 1,
                ]],
                'callback_url'   => url('/api/tripay-webhook'),
                'return_url'     => route('deposit.index'),
            ]);

            if (! ($res['success'] ?? false) || empty($res['data']['checkout_url'])) {
                $topup->update(['status' => 'failed']);
                Log::error('Tripay deposit checkout gagal', ['res' => $res]);
                return response()->json(['status' => 'error', 'message' => 'Gagal membuat transaksi Tripay: ' . ($res['message'] ?? 'unknown')], 500);
            }

            $topup->update(['payment_type' => 'tripay:' . $method]);

            $d = $res['data'];
            return response()->json([
                'status'       => 'success',
                'driver'       => 'tripay',
                'order_id'     => $topup->midtrans_order_id,
                'reference'    => $d['reference'] ?? null,
                'method'       => $method,
                'payment_name' => $d['payment_name'] ?? $method,
                'pay_code'     => $d['pay_code'] ?? null,   // nomor VA (bila VA)
                'qr_url'       => $d['qr_url'] ?? null,      // gambar QR (bila QRIS)
                'amount'       => (int) ($d['amount'] ?? $amount),
                'expired_time' => $d['expired_time'] ?? null, // unix timestamp
                'checkout_url' => $d['checkout_url'],        // fallback halaman Tripay
            ]);
        } catch (\Throwable $e) {
            Log::error('Tripay deposit checkout failed: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal memproses top-up Tripay. Silakan coba lagi.'], 500);
        }
    }
}
