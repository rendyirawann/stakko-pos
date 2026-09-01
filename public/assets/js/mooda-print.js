/*!
 * Mooda — unified receipt printing engine.
 * Metode: auto | native(APK) | browser | qztray | webbluetooth | rawbt
 * Config: window.MOODA_PRINT = { method, paper_width, store_name }
 * Referensi ESC/POS & UUID diverifikasi (58mm=32 kolom, 80mm=48 kolom;
 * BLE service 0x18F0 / char 0x2AF1; RawBT scheme rawbt:base64,<data>).
 */
window.MoodaPrint = (function () {
    'use strict';

    const CFG = window.MOODA_PRINT || { method: 'auto', paper_width: 58, store_name: 'Mooda' };
    // URL qz-tray.js di-inject dari server (asset()) agar benar di localhost maupun subfolder.
    const QZ_JS = CFG.qz_url || 'assets/plugins/custom/qz/qz-tray.js';
    const BLE_SERVICE = 0x18f0;
    const BLE_SERVICE_UUID = '000018f0-0000-1000-8000-00805f9b34fb';
    const BLE_WRITE_CHAR = 0x2af1;
    // Service BLE umum printer thermal (EcoPrint & sejenisnya). WAJIB dideklarasikan di
    // optionalServices agar getPrimaryServices() bisa mengaksesnya setelah connect.
    const BLE_PRINTER_SERVICES = [
        BLE_SERVICE, BLE_SERVICE_UUID,                          // 0x18F0 — paling umum (EcoPrint dll.)
        0xff00, '0000ff00-0000-1000-8000-00805f9b34fb',
        0xffe0, '0000ffe0-0000-1000-8000-00805f9b34fb',        // modul serial BLE (HM-10 style)
        0xfee7, '0000fee7-0000-1000-8000-00805f9b34fb',
        '49535343-fe7d-4ae5-8fa9-9fafd205e455',                // ISSC/Microchip transparent UART
    ];

    /**
     * Kelompokkan item per NOTA ASAL (it.merged_from) untuk pesanan hasil MERGE TABLE.
     * Mengembalikan satu kelompok tanpa label bila tak ada item gabungan, sehingga
     * struk pesanan biasa sama sekali tidak berubah.
     */
    function groupByOrigin(r) {
        const items = r.items || [];
        const map = new Map();
        items.forEach(it => {
            const k = it.merged_from || '';
            if (!map.has(k)) map.set(k, []);
            map.get(k).push(it);
        });
        if (map.size <= 1) return [{ label: null, items: items, subtotal: 0, qty: 0 }];

        const keys = [...map.keys()].sort((a, b) =>
            a === '' ? -1 : b === '' ? 1 : String(a).localeCompare(String(b), 'id', { numeric: true }));
        return keys.map(k => {
            const list = map.get(k);
            return {
                label: k === '' ? 'No. ' + (r.queue_number ?? '-') : 'No. ' + k + ' (gabung)',
                // Label pendek untuk baris subtotal: struk 32 kolom, label panjang bikin baris meluber.
                short: 'No. ' + (k === '' ? (r.queue_number ?? '-') : k),
                items: list,
                subtotal: list.reduce((a, b) => a + Number(b.subtotal || 0), 0),
                qty: list.reduce((a, b) => a + Number(b.qty || 0), 0),
            };
        });
    }


    const ESC = 0x1b, GS = 0x1d;
    const cols = () => (Number(CFG.paper_width) >= 80 ? 48 : 32);
    const sleep = (ms) => new Promise(r => setTimeout(r, ms));
    const money = (n) => 'Rp' + Number(n || 0).toLocaleString('id-ID');

    function toast(icon, title) {
        if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 2500 });
    }
    function alertErr(msg) { if (window.Swal) Swal.fire('Gagal Cetak', msg, 'error'); else alert(msg); }

    // Pesan error yang lebih membantu (Brave/HTTP/batal). Return null = jangan tampilkan (mis. user batal).
    function friendlyErr(e) {
        const msg = (e && e.message) ? e.message : String(e || '');
        if (/globally disabled|not allowed|securityerror|secure context|permissions policy/i.test(msg)) {
            return 'Web Bluetooth diblokir/dimatikan di browser ini (mis. Brave), atau situs belum HTTPS. ' +
                'Gunakan Chrome/Edge & akses lewat HTTPS, atau aktifkan "Web Bluetooth" di pengaturan browser.';
        }
        if (/cancel|dismiss|no devices|chooser|user gesture/i.test(msg)) return null; // user batal/tak pilih
        if (/gatt|disconnect|network error|operation failed|unreachable|no such/i.test(msg)) {
            return 'Printer Bluetooth sempat terputus. Sistem menyambung ulang otomatis — tunggu beberapa detik lalu coba cetak lagi. ' +
                'Pastikan printer menyala & dekat. (Cetak Bluetooth tidak butuh internet.)';
        }
        return msg;
    }

    // -------- helpers --------
    function b64(bytes) {
        let s = ''; const CH = 0x8000;
        for (let i = 0; i < bytes.length; i += CH) s += String.fromCharCode.apply(null, bytes.subarray(i, i + CH));
        return btoa(s);
    }
    function loadScript(src) {
        return new Promise((resolve, reject) => {
            if (document.querySelector('script[data-mooda="qz"]')) return resolve();
            const s = document.createElement('script');
            s.src = src; s.dataset.mooda = 'qz';
            s.onload = resolve; s.onerror = () => reject(new Error('Gagal memuat qz-tray.js'));
            document.head.appendChild(s);
        });
    }

    // -------- ESC/POS byte builder (raw) --------
    function bytesFromReceipt(r) {
        const W = cols();
        const buf = [];
        const push = (...b) => { for (const x of b) Array.isArray(x) ? buf.push(...x) : buf.push(x); };
        const enc = (s) => { for (const ch of String(s)) { const c = ch.codePointAt(0); buf.push(c <= 0xff ? c : 0x3f); } };
        const line = (s = '') => { enc(s); push(0x0a); };
        const rule = () => line('-'.repeat(W));
        const row = (l, rr) => { l = String(l); rr = String(rr); const gap = Math.max(1, W - l.length - rr.length); line(l + ' '.repeat(gap) + rr); };
        const lines = (v) => String(v == null ? '' : v).split('\n').map(s => s.trim()).filter(Boolean);

        push(ESC, 0x40);            // init
        push(ESC, 0x74, 0x00);      // codepage CP437
        push(ESC, 0x61, 0x01);      // center
        push(ESC, 0x45, 0x01);      // bold on
        push(GS, 0x21, 0x11);       // double height+width
        line((r.store_name || CFG.store_name || 'Mooda').toUpperCase());
        push(GS, 0x21, 0x00); push(ESC, 0x45, 0x00);
        lines(r.receipt_header ?? CFG.receipt_header).forEach(l => line(l));
        lines(r.store_address ?? CFG.store_address).forEach(l => line(l));
        const phone = r.store_phone ?? CFG.store_phone;
        if (phone) line('Telp: ' + phone);
        rule();
        push(ESC, 0x61, 0x01); push(GS, 0x21, 0x01);   // center, double height
        line('No. Antrian ' + (r.queue_number ?? '-'));
        push(GS, 0x21, 0x00); push(ESC, 0x61, 0x00);
        rule();
        if (r.invoice_no) row('No', r.invoice_no);
        if (r.datetime) row('Tgl', r.datetime);
        if (r.table_no) line('Meja ' + r.table_no + ' - ' + (r.customer_name || 'Pelanggan'));
        else if (r.customer_name) row('Plg', r.customer_name);
        rule();
        groupByOrigin(r).forEach(g => {
            if (g.label) { line('-- ' + g.label + ' --'); }
            g.items.forEach(it => {
                line(String(it.name));
                if (it.addons && it.addons.length) it.addons.forEach(a => line('  + ' + (a.name || '') + ((a.qty > 1) ? ' x' + a.qty : '')));
                row('  ' + it.qty + ' x ' + money(it.price), money(it.subtotal));
                if (it.notes) line('  * ' + it.notes);
            });
            if (g.label) { row('  Subtotal ' + g.short, money(g.subtotal)); }
        });
        rule();
        row('Subtotal', money(r.subtotal));
        if (Number(r.discount_amount) > 0) row('Diskon', '-' + money(r.discount_amount));
        const taxRate = r.tax_rate ?? CFG.tax_rate;
        row('Pajak' + (taxRate != null ? ' (' + taxRate + '%)' : ''), money(r.tax));
        push(ESC, 0x45, 0x01); row('TOTAL', money(r.grand_total)); push(ESC, 0x45, 0x00);
        row('Metode', (r.payment_method || '-').toUpperCase());
        if (r.payment_method === 'cash' && r.cash_received != null) {
            row('Tunai', money(r.cash_received));
            row('Kembali', money(r.change_amount));
        }
        rule();
        push(ESC, 0x61, 0x01);
        line(r.payment_status === 'paid' ? '*** LUNAS ***' : '** BELUM LUNAS **');
        lines(r.receipt_footer ?? CFG.receipt_footer ?? 'Terima kasih!').forEach(l => line(l));
        push(0x0a, 0x0a, 0x0a);
        // Buka laci kasir (ESC/POS drawer kick, pin 2). Otomatis; diabaikan printer yang tak
        // terhubung laci -> tanpa error. Bisa dimatikan dgn CFG.open_drawer = false.
        if (CFG.open_drawer !== false) push(ESC, 0x70, 0x00, 0x19, 0xFA);
        push(GS, 0x56, 0x42, 0x03); // feed + partial cut (Function B; diabaikan printer tanpa cutter)
        return new Uint8Array(buf);
    }

    // -------- plain text (untuk bridge APK yang menambah ESC/POS sendiri) --------
    function plainText(r) {
        const W = cols();
        const center = (s) => { s = String(s); return s.length >= W ? s.slice(0, W) : ' '.repeat(Math.floor((W - s.length) / 2)) + s; };
        const row = (l, rr) => { l = String(l); rr = String(rr); const gap = Math.max(1, W - l.length - rr.length); return l + ' '.repeat(gap) + rr; };
        const sep = '-'.repeat(W); const o = [];
        const lines = (v) => String(v == null ? '' : v).split('\n').map(s => s.trim()).filter(Boolean);
        o.push(center((r.store_name || CFG.store_name || 'Mooda').toUpperCase()));
        lines(r.receipt_header ?? CFG.receipt_header).forEach(l => o.push(center(l)));
        lines(r.store_address ?? CFG.store_address).forEach(l => o.push(center(l)));
        const phone = r.store_phone ?? CFG.store_phone;
        if (phone) o.push(center('Telp: ' + phone));
        o.push(sep); o.push(center('NO. ANTRIAN ' + (r.queue_number ?? '-'))); o.push(sep);
        if (r.invoice_no) o.push(row('No', r.invoice_no));
        if (r.datetime) o.push(row('Tgl', r.datetime));
        if (r.table_no) o.push('Meja ' + r.table_no + ' - ' + (r.customer_name || 'Pelanggan'));
        else if (r.customer_name) o.push(row('Plg', r.customer_name));
        o.push(sep);
        groupByOrigin(r).forEach(g => {
            if (g.label) o.push('-- ' + g.label + ' --');
            g.items.forEach(it => {
                o.push(String(it.name));
                if (it.addons && it.addons.length) it.addons.forEach(a => o.push('  + ' + (a.name || '') + ((a.qty > 1) ? ' x' + a.qty : '')));
                o.push(row('  ' + it.qty + ' x ' + money(it.price), money(it.subtotal)));
                if (it.notes) o.push('  * ' + it.notes);
            });
            if (g.label) o.push(row('  Subtotal ' + g.short, money(g.subtotal)));
        });
        o.push(sep);
        o.push(row('Subtotal', money(r.subtotal)));
        if (Number(r.discount_amount) > 0) o.push(row('Diskon', '-' + money(r.discount_amount)));
        const taxRate = r.tax_rate ?? CFG.tax_rate;
        o.push(row('Pajak' + (taxRate != null ? ' (' + taxRate + '%)' : ''), money(r.tax)));
        o.push(row('TOTAL', money(r.grand_total)));
        o.push(row('Metode', (r.payment_method || '-').toUpperCase()));
        if (r.payment_method === 'cash' && r.cash_received != null) {
            o.push(row('Tunai', money(r.cash_received))); o.push(row('Kembali', money(r.change_amount)));
        }
        o.push(sep); o.push(center(r.payment_status === 'paid' ? '*** LUNAS ***' : '** BELUM LUNAS **'));
        lines(r.receipt_footer ?? CFG.receipt_footer ?? 'Terima kasih!').forEach(l => o.push(center(l)));
        return o.join('\n');
    }

    // -------- preview (dipakai halaman Setelan utk pratinjau struk) --------
    function preview(r) { return plainText(r); }

    // -------- method resolution --------
    const hasNative = () => !!(window.AndroidPrinter && typeof window.AndroidPrinter.printReceipt === 'function');
    function resolveMethod() {
        let m = CFG.method || 'auto';
        // Di dalam APK (bridge native tersedia) SELALU pakai native. Metode browser/qztray/
        // webbluetooth/rawbt tidak berfungsi di WebView & memicu dialog cetak -> PDF. Setelan
        // tenant (mis. 'Dialog Browser/OS') hanya berlaku untuk PC/browser, bukan APK.
        if (hasNative()) return 'native';
        if (m === 'auto') return 'browser';
        if (m === 'native') return 'browser'; // native diminta tapi tak ada bridge (bukan APK)
        return m;
    }

    // -------- QZ Tray --------
    async function ensureQz() {
        if (!window.qz) await loadScript(QZ_JS);
        if (!window.qz) throw new Error('qz-tray.js tidak termuat.');
        if (!qz.websocket.isActive()) await qz.websocket.connect();
    }
    async function qzPrinters() { await ensureQz(); return await qz.printers.find(); }
    async function printQz(r) {
        await ensureQz();
        let printer = localStorage.getItem('mooda_qz_printer');
        if (!printer) printer = await qz.printers.getDefault();
        const cfg = qz.configs.create(printer);
        const data = [{ type: 'raw', format: 'command', flavor: 'base64', data: b64(bytesFromReceipt(r)) }];
        await qz.print(cfg, data);
    }

    // -------- Web Bluetooth (auto-reconnect tahan-banting) --------
    let bleChar = null, bleDevice = null, bleReconnecting = false;
    let bleWatchdog = null, bleWantConnected = false;
    let bleKeepAlive = null, blePrinting = false, bleVisBound = false;

    async function discoverWritable(server) {
        const svcs = await server.getPrimaryServices();
        for (const s of svcs) {
            const chs = await s.getCharacteristics();
            for (const c of chs) if (c.properties.write || c.properties.writeWithoutResponse) return c;
        }
        throw new Error('Karakteristik tulis tidak ditemukan di printer ini.');
    }

    // Ambil karakteristik tulis: coba service printer standar (0x18F0/0x2AF1), fallback scan.
    async function acquireChar(server) {
        try { const svc = await server.getPrimaryService(BLE_SERVICE); return await svc.getCharacteristic(BLE_WRITE_CHAR); }
        catch (e) { return await discoverWritable(server); }
    }

    // Sambungkan GATT dengan beberapa percobaan (printer thermal sering lambat bangun dari mode hemat daya).
    async function connectGatt(dev, tries = 4) {
        let err;
        for (let i = 0; i < tries; i++) {
            try {
                if (dev.gatt.connected) return dev.gatt;
                return await dev.gatt.connect();
            } catch (e) { err = e; await sleep(350 * (i + 1)); }
        }
        throw err || new Error('Gagal menyambung ke printer Bluetooth.');
    }

    function bindDisconnect(dev) {
        try { dev.removeEventListener('gattserverdisconnected', onBleDisconnect); } catch (e) {}
        dev.addEventListener('gattserverdisconnected', onBleDisconnect);
    }

    // Saat printer memutus (mis. hemat daya), sambung ulang otomatis di latar belakang
    // agar tidak perlu klik "Hubungkan" lagi saat mau cetak.
    async function onBleDisconnect() {
        bleChar = null;
        if (bleReconnecting || !bleDevice || !bleWantConnected) return;
        bleReconnecting = true;
        try {
            const server = await connectGatt(bleDevice, 3);
            bleChar = await acquireChar(server);
        } catch (e) { /* gagal; watchdog akan mencoba lagi otomatis */ }
        finally { bleReconnecting = false; }
    }

    // Denyut anti-idle: printer thermal menjatuhkan link BLE bila lama tak ada lalu lintas.
    // ESC @ (inisialisasi printer) TIDAK memakan kertas dan tidak mencetak apa pun -- byte
    // yang sama sudah dipakai sebagai pembuka tiap struk, jadi aman dikirim berkala.
    // Tujuannya MENCEGAH putus, bukan sekadar menyembuhkan setelah putus seperti watchdog.
    async function bleKeepAliveTick() {
        if (!bleWantConnected || blePrinting || bleReconnecting) return;
        if (document.hidden) return;                       // tab tersembunyi: tak perlu dibangunkan
        if (!bleChar || !bleDevice || !bleDevice.gatt || !bleDevice.gatt.connected) return;
        try {
            const ping = new Uint8Array([ESC, 0x40]);
            if (bleChar.writeValueWithoutResponse) await bleChar.writeValueWithoutResponse(ping);
            else await bleChar.writeValue(ping);
        } catch (e) { bleChar = null; }                    // gagal -> watchdog yang menyambung ulang
    }

    // Watchdog: cek berkala & sambung ulang otomatis bila printer terputus saat idle,
    // supaya koneksi "diingat" dan tetap siap cetak tanpa perlu klik Hubungkan lagi.
    function startBleWatchdog() {
        if (bleWatchdog) return;
        bleWatchdog = setInterval(function () {
            if (!bleWantConnected || !bleDevice || bleReconnecting) return;
            const connected = bleDevice.gatt && bleDevice.gatt.connected;
            if (!connected) { onBleDisconnect(); }
        }, 5000);
        bleKeepAlive = setInterval(bleKeepAliveTick, 20000);

        // Saat kasir kembali ke tab, jangan menunggu siklus watchdog berikutnya.
        if (!bleVisBound) {
            bleVisBound = true;
            document.addEventListener('visibilitychange', function () {
                if (document.hidden || !bleWantConnected || !bleDevice || bleReconnecting) return;
                if (!(bleDevice.gatt && bleDevice.gatt.connected)) onBleDisconnect();
            });
        }
    }

    // Pastikan terhubung + karakteristik siap sebelum menulis.
    async function ensureBle() {
        if (bleChar && bleDevice && bleDevice.gatt && bleDevice.gatt.connected) return bleChar;
        if (!bleDevice) throw new Error('Printer Bluetooth belum dipilih. Klik "Hubungkan Printer BT" dulu.');
        const server = await connectGatt(bleDevice);
        bleChar = await acquireChar(server);
        return bleChar;
    }

    async function writeChunks(bytes) {
        for (let i = 0; i < bytes.length; i += 180) {
            const chunk = bytes.slice(i, i + 180);
            if (bleChar.writeValueWithoutResponse) await bleChar.writeValueWithoutResponse(chunk);
            else await bleChar.writeValue(chunk);
            await sleep(20);
        }
    }

    async function connectBle() {
        if (!navigator.bluetooth) throw new Error('Browser ini tidak mendukung Web Bluetooth (pakai Chrome/Edge).');
        const dev = await navigator.bluetooth.requestDevice({
            acceptAllDevices: true,
            optionalServices: BLE_PRINTER_SERVICES,
        });
        bleDevice = dev;
        bleWantConnected = true;
        // Simpan id printer pilihan: bila nanti ada >1 perangkat yang pernah diizinkan,
        // restoreBle() memulihkan yang INI, bukan yang kebetulan pertama di daftar.
        try { localStorage.setItem('mooda_ble_id', dev.id || ''); } catch (e) {}
        bindDisconnect(dev);
        startBleWatchdog();
        const server = await connectGatt(dev);
        bleChar = await acquireChar(server);
        return dev.name || 'Printer BT';
    }

    async function printBle(r) {
        const bytes = bytesFromReceipt(r);
        blePrinting = true;   // tahan denyut keep-alive agar tidak menyisip di tengah struk
        try {
            // Tulis; jika putus di tengah, sambung ulang lalu ulangi (beberapa kali) sebelum menyerah.
            for (let attempt = 0; attempt < 3; attempt++) {
                try {
                    await ensureBle();
                    await writeChunks(bytes);
                    return;
                } catch (e) {
                    bleChar = null;
                    if (attempt === 2) throw e;
                    await sleep(400 * (attempt + 1));
                }
            }
        } finally { blePrinting = false; }
    }

    // Pulihkan printer yang sudah pernah diizinkan tanpa dialog pemilihan (Chrome getDevices()),
    // agar reconnect mulus setelah pindah halaman. Aman dipanggil kapan saja (no-op bila tak didukung).
    // Petunjuk sekali-tampil: tanpa getDevices(), printer TIDAK BISA diingat lintas halaman.
    // Itu batas platform Chrome (flag eksperimental), bukan kegagalan pairing -- dan sebelumnya
    // restoreBle() gagal tanpa suara sehingga kasir mengira aplikasinya yang rusak.
    function hintPersistentPermission() {
        try { if (localStorage.getItem('mooda_ble_flag_hint')) return; } catch (e) {}
        try { localStorage.setItem('mooda_ble_flag_hint', '1'); } catch (e) {}
        if (!window.Swal) return;
        Swal.fire({
            icon: 'info',
            title: 'Agar printer diingat',
            html: 'Browser ini belum bisa mengingat printer Bluetooth antar halaman, jadi tombol ' +
                  '<b>Hubungkan</b> muncul terus.<br><br>Sekali saja, di HP/tablet kasir:<br>' +
                  '1. Buka <b>chrome://flags</b><br>' +
                  '2. Cari <b>web-bluetooth-new-permissions-backend</b><br>' +
                  '3. Ubah ke <b>Enabled</b>, lalu restart Chrome<br>' +
                  '4. Hubungkan printer sekali lagi<br><br>Setelah itu printer nyambung sendiri.',
            confirmButtonText: 'Mengerti',
        });
    }

    async function restoreBle() {
        try {
            if (!navigator.bluetooth) return false;
            if (bleDevice) return true;
            if (!navigator.bluetooth.getDevices) { hintPersistentPermission(); return false; }
            const devs = await navigator.bluetooth.getDevices();
            if (!devs || !devs.length) return false;
            let want = null; try { want = localStorage.getItem('mooda_ble_id'); } catch (e) {}
            bleDevice = (want && devs.find(d => d.id === want)) || devs[0];
            bleWantConnected = true;
            bindDisconnect(bleDevice);
            startBleWatchdog();
            connectGatt(bleDevice, 2).then(s => acquireChar(s)).then(c => { bleChar = c; }).catch(() => {});
            return true;
        } catch (e) { return false; }
    }

    // -------- RawBT (Android) --------
    function printRawbt(r) {
        window.location.href = 'rawbt:base64,' + b64(bytesFromReceipt(r));
    }

    // -------- Browser / OS dialog (mendukung mode kiosk --kiosk-printing) --------
    function escapeHtml(s) { return String(s == null ? '' : s).replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c])); }
    function receiptHtml(r) {
        const paperMm = (Number(CFG.paper_width) >= 80 ? '80mm' : '58mm');
        return '<!doctype html><html><head><meta charset="utf-8"><style>'
            + '@page{size:' + paperMm + ' auto;margin:0;}'
            + 'html,body{margin:0;padding:0;}'
            + 'pre{font-family:"Courier New",monospace;font-size:12px;line-height:1.25;white-space:pre;margin:0;padding:6px 6px 24px;width:' + paperMm + ';}'
            + '</style></head><body><pre>' + escapeHtml(plainText(r)) + '</pre></body></html>';
    }
    let _printFrame = null;
    function printBrowser(r, printUrl) {
        // Cetak lewat iframe tersembunyi: tanpa tab baru, dan JIKA browser dijalankan
        // dengan flag --kiosk-printing maka langsung tercetak ke printer default (senyap).
        if (r) {
            try {
                if (_printFrame) { try { _printFrame.remove(); } catch (e) {} }
                const f = document.createElement('iframe');
                f.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
                document.body.appendChild(f);
                _printFrame = f;
                const doc = f.contentWindow.document;
                doc.open(); doc.write(receiptHtml(r)); doc.close();
                setTimeout(function () {
                    try { f.contentWindow.focus(); f.contentWindow.print(); } catch (e) {}
                    setTimeout(function () { try { f.remove(); } catch (e) {} _printFrame = null; }, 3000);
                }, 350);
                return;
            } catch (e) { /* fallback ke bawah */ }
        }
        if (printUrl) window.open(printUrl, '_blank'); else window.print();
    }

    // -------- public: print --------
    function print(receipt, printUrl) {
        const m = resolveMethod();
        try {
            if (m === 'native') { window.AndroidPrinter.printReceipt(plainText(receipt)); return; }
            if (m === 'qztray') { printQz(receipt).catch(e => alertErr('QZ Tray: ' + e.message + '. Pastikan aplikasi QZ Tray berjalan.')); return; }
            if (m === 'webbluetooth') { printBle(receipt).catch(e => { const f = friendlyErr(e); if (f) alertErr(f); }); return; }
            if (m === 'rawbt') { printRawbt(receipt); return; }
            printBrowser(receipt, printUrl);
        } catch (e) { printBrowser(receipt, printUrl); }
    }

    // -------- public: quickConnect (dipakai tombol di Kasir/Setelan) --------
    function needsButton() { return ['native', 'webbluetooth', 'qztray'].includes(resolveMethod()); }
    function buttonLabel() {
        const m = resolveMethod();
        if (m === 'webbluetooth') return 'Hubungkan Printer BT';
        if (m === 'qztray') return 'Pilih Printer';
        return 'Printer';
    }
    async function quickConnect() {
        const m = resolveMethod();
        try {
            if (m === 'native') {
                let list = []; try { list = JSON.parse(window.AndroidPrinter.getPrinters() || '[]'); } catch (e) {}
                if (!list.length) return Swal.fire('Belum ada printer', 'Pasangkan printer Bluetooth di Setelan Android, lalu coba lagi.', 'info');
                const opts = {}; list.forEach(p => opts[p.address] = p.name);
                const res = await Swal.fire({ title: 'Pilih Printer', input: 'select', inputOptions: opts, showCancelButton: true });
                if (res.isConfirmed && res.value) { window.AndroidPrinter.setPrinter(res.value); toast('success', 'Printer dipilih'); }
            } else if (m === 'webbluetooth') {
                const name = await connectBle(); toast('success', 'Terhubung: ' + name);
            } else if (m === 'qztray') {
                const printers = await qzPrinters();
                if (!printers || !printers.length) return Swal.fire('Tidak ada printer', 'QZ Tray tidak menemukan printer terpasang.', 'info');
                const opts = {}; printers.forEach(p => opts[p] = p);
                const res = await Swal.fire({ title: 'Pilih Printer (QZ Tray)', input: 'select', inputOptions: opts, showCancelButton: true });
                if (res.isConfirmed && res.value) { localStorage.setItem('mooda_qz_printer', res.value); toast('success', 'Printer disimpan'); }
            }
        } catch (e) { const f = friendlyErr(e); if (f) alertErr(f); }
    }

    // -------- public: test print --------
    function test() {
        const sample = {
            store_name: CFG.store_name, invoice_no: 'TEST-0001', queue_number: 1, customer_name: 'Uji Cetak',
            datetime: new Date().toLocaleString('id-ID'),
            items: [
                { name: 'Kopi Susu', qty: 2, price: 18000, subtotal: 36000, addons: [{ name: 'Extra Shot' }] },
                { name: 'Roti Bakar', qty: 1, price: 15000, subtotal: 15000 },
            ],
            subtotal: 51000, discount_amount: 0, tax: 5100, tax_rate: 10, grand_total: 56100,
            payment_method: 'cash', payment_status: 'paid', cash_received: 60000, change_amount: 3900,
        };
        print(sample, null);
    }

    // -------- public: autoSetup (APK) — pilih + SAMBUNGKAN printer BT saat pertama masuk --------
    // Hanya di APK (native). Belum ada printer tersimpan: 0 ter-pair -> arahkan pair; 1 -> pakai;
    // banyak -> pilih. Setelah itu (atau bila sudah tersimpan) langsung connect (buka koneksi).
    async function autoSetup() {
        if (resolveMethod() !== 'native' || !hasNative()) return;

        let current = '';
        try { current = (window.AndroidPrinter.getSelectedPrinter && window.AndroidPrinter.getSelectedPrinter()) || ''; } catch (e) {}

        if (!current) {
            if (!window.Swal) return;
            let list = [];
            try { list = JSON.parse(window.AndroidPrinter.getPrinters() || '[]'); } catch (e) {}
            if (!list.length) {
                await Swal.fire({
                    icon: 'info', title: 'Sambungkan Printer Dulu',
                    html: 'Belum ada printer Bluetooth yang ter-<i>pair</i>.<br>Pasangkan printer (mis. IWARE / EcoPrint) di <b>Setelan Bluetooth Android</b>, lalu buka aplikasi lagi.',
                    confirmButtonText: 'Mengerti',
                });
                return;
            }
            if (list.length === 1) {
                try { window.AndroidPrinter.setPrinter(list[0].address); } catch (e) {}
            } else {
                const opts = {}; list.forEach(function (p) { opts[p.address] = p.name || p.address; });
                const res = await Swal.fire({
                    title: 'Pilih Printer', input: 'select', inputOptions: opts,
                    inputPlaceholder: 'Pilih printer thermal', showCancelButton: true,
                    confirmButtonText: 'Pakai printer ini', cancelButtonText: 'Nanti',
                });
                if (!(res.isConfirmed && res.value)) return; // ditunda user
                try { window.AndroidPrinter.setPrinter(res.value); } catch (e) {}
            }
        }

        // Printer terpilih & siap. TIDAK memaksa buka koneksi di sini: memaksa connect saat login
        // (via bridge native) sempat menahan proses hingga terasa "loading terus" di sebagian
        // perangkat. Koneksi dibuka otomatis & cepat saat cetak pertama (socket keep-alive).
        toast('success', 'Printer siap.');
    }

    // -------- public: connect (APK) — buka koneksi ke printer terpilih tanpa mencetak --------
    // Tidak dipanggil otomatis saat login (bisa menahan proses). Tersedia untuk pemicu manual.
    // APK lama tanpa bridge connect() -> koneksi terbuka saat cetak pertama.
    function connect() {
        if (!hasNative() || !window.AndroidPrinter.connect) return;
        try { window.AndroidPrinter.connect(); } catch (e) {}
    }

    return { print, quickConnect, needsButton, buttonLabel, test, preview, cols, resolveMethod, hasNative, connectBle, restoreBle, autoSetup, connect };
})();
