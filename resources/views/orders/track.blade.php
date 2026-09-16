@php
    $title = 'Detail & Struk '.$order->order_number.' | LaundryHub';
    $description = 'Struk dan status pesanan '.$order->order_number.' dari '.$order->branch->name.'. Total: Rp'.number_format($order->total_rupiah, 0, ',', '.').', Status: '.$order->status->label().'.';
    $socialImageUrl = asset('apple-touch-icon.png');
    $isPaid = $order->priced_at !== null && $outstandingRupiah === 0;
    $isGateway = $pendingPayment !== null && ($pendingPayment->gateway_driver !== null || $pendingPayment->method === \App\Enums\PaymentMethod::Gateway);
    $isQris = $pendingPayment !== null && $pendingPayment->method === \App\Enums\PaymentMethod::Qris;
@endphp
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }}</title>
        <meta name="description" content="{{ $description }}">
        <meta name="robots" content="noindex,nofollow,noarchive">
        <link rel="canonical" href="{{ $canonicalUrl }}">
        <link rel="icon" href="/favicon.ico" sizes="32x32">
        <meta name="theme-color" content="#40513b">

        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ $canonicalUrl }}">
        <meta property="og:image" content="{{ $socialImageUrl }}">
        <meta property="og:image:width" content="180">
        <meta property="og:image:height" content="180">
        <meta property="og:image:alt" content="Ikon mesin cuci LaundryHub">
        <meta property="og:locale" content="id_ID">
        <meta property="og:site_name" content="LaundryHub">

        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="{{ $title }}">
        <meta name="twitter:description" content="{{ $description }}">
        <meta name="twitter:image" content="{{ $socialImageUrl }}">

        @vite('resources/css/app.css')
        <style>
            @media print {
                body { background-color: #ffffff !important; padding: 0 !important; color: #000000 !important; }
                main { border: none !important; box-shadow: none !important; max-width: 100% !important; border-radius: 0 !important; }
                .no-print { display: none !important; }
                header { background-color: #2e3b2b !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            }
        </style>
    </head>
    <body class="min-h-screen bg-canvas px-4 py-8 font-sans text-ink-950 antialiased">
        <main class="mx-auto max-w-lg overflow-hidden rounded-3xl border border-line bg-surface shadow-raised">
            {{-- Header Struk & Pesanan --}}
            <header class="bg-brand-700 px-6 py-7 text-center text-white">
                <p class="text-xs font-semibold tracking-[0.24em] text-white/80 uppercase">LaundryHub &middot; Struk &amp; Detail</p>
                <h1 class="mt-2 font-display text-2xl font-bold tracking-tight">Detail &amp; Struk Pesanan</h1>
                <p class="mt-1 font-mono text-sm tracking-wide text-white/90">{{ $order->order_number }}</p>
            </header>

            <div class="space-y-6 p-6">
                @if (session('status'))
                    <div class="rounded-2xl border border-mint-500/20 bg-mint-500/10 p-4 text-sm font-medium text-mint-700 no-print">
                        {{ session('status') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="rounded-2xl border border-red-500/20 bg-red-500/10 p-4 text-sm font-medium text-red-600 no-print">
                        {{ session('error') }}
                    </div>
                @endif

                {{-- Identitas Outlet & Pelanggan --}}
                <section class="rounded-2xl bg-canvas p-5">
                    <div class="text-center">
                        <h2 class="font-display text-xl font-bold text-ink-950">{{ $order->branch->name }}</h2>
                        @if ($order->branch->address)
                            <p class="mt-1 text-xs leading-relaxed text-ink-500">
                                {{ $order->branch->address }}{{ $order->branch->city ? ', '.$order->branch->city->name : '' }}
                            </p>
                        @endif
                        @if ($order->branch->phone)
                            <p class="mt-0.5 text-xs text-ink-400">Telp/WA: {{ $order->branch->phone }}</p>
                        @endif
                    </div>

                    <dl class="mt-4 space-y-2 border-t border-dashed border-line pt-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-500">Pelanggan</dt>
                            <dd class="font-semibold text-ink-950">{{ $order->customer_name_snapshot ?? 'Pelanggan' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-500">Waktu Pesanan</dt>
                            <dd class="font-medium text-ink-700">{{ $order->created_at->translatedFormat('d F Y, H:i') }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-500">Status Pengerjaan</dt>
                            <dd class="font-semibold text-brand-700">{{ $order->status->label() }}</dd>
                        </div>
                    </dl>
                </section>

                {{-- KOTAK UTAMA: TOTAL YANG HARUS DIBAYAR & STATUS PEMBAYARAN --}}
                <section aria-labelledby="billing-summary-title" class="rounded-2xl border-2 border-line bg-surface p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-semibold tracking-wider text-ink-500 uppercase">Status Pembayaran</span>
                        <span @class([
                            'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1 text-xs font-bold uppercase tracking-wider',
                            'bg-mint-500/15 text-mint-700 border border-mint-500/30' => $isPaid,
                            'bg-amber-500/15 text-amber-800 border border-amber-500/30' => ! $isPaid,
                        ])>
                            @if ($isPaid)
                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                </svg>
                                Lunas
                            @else
                                <span class="size-1.5 rounded-full bg-amber-600 animate-pulse"></span>
                                Belum Lunas
                            @endif
                        </span>
                    </div>

                    <div class="mt-4 rounded-xl bg-canvas p-4 text-center">
                        <p class="text-xs font-semibold tracking-wide text-ink-500 uppercase">Total Yang Harus Dibayar</p>
                        <p class="mt-1 font-display text-3xl font-extrabold text-brand-700">
                            Rp{{ number_format($order->total_rupiah, 0, ',', '.') }}
                        </p>
                    </div>

                    <dl class="mt-4 space-y-2 border-t border-dashed border-line pt-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-500">Jumlah Sudah Dibayar</dt>
                            <dd @class(['font-semibold', 'text-mint-600' => $paidRupiah > 0, 'text-ink-700' => $paidRupiah === 0])>
                                Rp{{ number_format($paidRupiah, 0, ',', '.') }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4 text-base font-bold">
                            <dt class="text-ink-950">Sisa Yang Harus Dibayar</dt>
                            <dd @class([
                                'font-display',
                                'text-mint-600' => $isPaid,
                                'text-red-600' => ! $isPaid,
                            ])>
                                {{ $isPaid ? 'Rp0 (Lunas)' : 'Rp'.number_format($outstandingRupiah, 0, ',', '.') }}
                            </dd>
                        </div>
                    </dl>
                </section>

                {{-- Rincian Layanan Cucian --}}
                @if ($order->items->isNotEmpty())
                    <section aria-labelledby="receipt-items-title">
                        <h2 id="receipt-items-title" class="text-sm font-semibold tracking-wide text-ink-900 uppercase">
                            Rincian pesanan
                        </h2>
                        <ul class="mt-3 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-surface">
                            @foreach ($order->items as $item)
                                <li class="flex items-center justify-between gap-4 p-4 text-sm">
                                    <div>
                                        <p class="font-medium text-ink-950">{{ $item->service_name_snapshot }}</p>
                                        <p class="text-xs text-ink-500">
                                            @if ($item->requested_weight_grams > 0)
                                                {{ number_format($item->requested_weight_grams / 1000, 2, ',', '.') }} kg
                                            @elseif ($item->quantity !== null)
                                                {{ $item->quantity }} pcs
                                            @endif
                                            @if ($item->unit_price_rupiah > 0)
                                                &middot; @ Rp{{ number_format($item->unit_price_rupiah, 0, ',', '.') }}
                                            @endif
                                        </p>
                                    </div>
                                    <p class="font-semibold text-ink-950">Rp{{ number_format($item->subtotal_rupiah, 0, ',', '.') }}</p>
                                </li>
                            @endforeach
                        </ul>

                        @if ($order->delivery_fee_rupiah > 0)
                            <div class="mt-2 flex justify-between rounded-xl bg-canvas px-4 py-2.5 text-xs text-ink-600">
                                <span>Ongkos kirim antar-jemput</span>
                                <span class="font-semibold text-ink-950">Rp{{ number_format($order->delivery_fee_rupiah, 0, ',', '.') }}</span>
                            </div>
                        @endif
                    </section>
                @endif

                {{-- AREA PEMBAYARAN: JIKA BELUM LUNAS TAMPILKAN QRIS / GATEWAY, JIKA LUNAS TAMPILKAN STRUK TANDA LUNAS --}}
                @if ($isPaid)
                    {{-- Struk Tanda Lunas --}}
                    <section class="rounded-2xl border border-mint-500/25 bg-mint-500/5 p-5 text-center">
                        <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-mint-500/15 text-mint-600">
                            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h2 class="mt-3 font-display text-lg font-bold text-mint-800">Struk Pembayaran Lunas</h2>
                        <p class="mt-1 text-xs text-ink-600">Seluruh pembayaran pesanan ini telah selesai dan diverifikasi.</p>

                        @if ($settledPayments->isNotEmpty())
                            <div class="mt-4 rounded-xl border border-line bg-surface p-3 text-left text-xs">
                                <p class="font-semibold text-ink-800">Riwayat Pembayaran:</p>
                                <ul class="mt-2 divide-y divide-line">
                                    @foreach ($settledPayments as $settled)
                                        <li class="flex items-center justify-between py-1.5 text-ink-600">
                                            <div>
                                                <span class="font-medium text-ink-900">{{ $settled->method->label() }}</span>
                                                @if ($settled->payment_number)
                                                    <span class="text-[11px] text-ink-400">({{ $settled->payment_number }})</span>
                                                @endif
                                                @if ($settled->settled_at)
                                                    <p class="text-[10px] text-ink-400">{{ $settled->settled_at->translatedFormat('d M Y, H:i') }}</p>
                                                @endif
                                            </div>
                                            <span class="font-semibold text-mint-600">Rp{{ number_format($settled->amount_rupiah, 0, ',', '.') }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="mt-4 no-print">
                            <button
                                type="button"
                                onclick="window.print()"
                                class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
                            >
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                Cetak / Simpan Struk
                            </button>
                        </div>
                    </section>
                @elseif ($order->priced_at === null)
                    <section class="rounded-2xl border border-line bg-canvas p-5 text-center">
                        <p class="text-sm font-medium text-ink-700">Penentuan harga sedang diproses oleh pihak laundry.</p>
                        <p class="mt-1 text-xs text-ink-500">QRIS dan pilihan pembayaran akan aktif setelah pesanan selesai ditimbang.</p>
                    </section>
                @else
                    {{-- Belum Lunas: QRIS Dinamis & Pilihan Payment Gateway --}}
                    <section aria-labelledby="payment-action-title" class="rounded-2xl border border-line bg-canvas p-5 no-print">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold tracking-[0.14em] text-brand-600 uppercase">
                                    {{ $isGateway ? 'Payment Gateway Aktif' : ($isQris ? 'QRIS Dinamis Aktif' : 'Pilih Metode Pembayaran') }}
                                </p>
                                <h2 id="payment-action-title" class="mt-1 font-display text-xl font-bold text-ink-950">
                                    Rp{{ number_format($pendingPayment?->amount_rupiah ?? $outstandingRupiah, 0, ',', '.') }}
                                </h2>
                                @if ($pendingPayment?->payment_number)
                                    <p class="text-xs font-mono text-ink-500">{{ $pendingPayment->payment_number }}</p>
                                @endif
                            </div>
                            <span class="rounded-full bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-700">
                                {{ $pendingPayment !== null ? 'Menunggu Pembayaran' : 'Belum Dipilih' }}
                            </span>
                        </div>

                        {{-- Tampilan QRIS Dinamis --}}
                        @if ($qrisImageDataUri && (! $isGateway || $pendingPayment?->gateway_method === 'QRIS'))
                            <div class="mx-auto mt-4 max-w-72 rounded-2xl bg-white p-4 text-center shadow-low">
                                <img
                                    src="{{ $qrisImageDataUri }}"
                                    alt="QRIS {{ $pendingPayment?->payment_number ?? $order->order_number }}"
                                    class="aspect-square w-full"
                                />
                                <p class="mt-2 text-[11px] font-semibold tracking-wider text-ink-400 uppercase">
                                    QRIS Standar Pembayaran Nasional
                                </p>
                            </div>

                            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                <a
                                    href="{{ $qrisImageDataUri }}"
                                    download="QRIS-{{ $pendingPayment?->payment_number ?? $order->order_number }}.svg"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-brand-600/30 bg-surface px-4 py-2.5 text-sm font-semibold text-brand-700 shadow-sm transition hover:bg-brand-50"
                                >
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    Unduh QRIS
                                </a>
                                <a
                                    href="{{ $canonicalUrl }}"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
                                >
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Cek Pembayaran
                                </a>
                            </div>

                            <div class="mt-4 rounded-xl bg-surface p-3.5 text-xs leading-5 text-ink-600">
                                <p class="font-semibold text-ink-950">Cara Pembayaran QRIS:</p>
                                <ol class="mt-1 list-decimal pl-4 space-y-1">
                                    <li>Buka aplikasi m-Banking (BCA, Mandiri, BRI, BNI) atau e-Wallet (GoPay, OVO, DANA, ShopeePay).</li>
                                    <li>Pindai (scan) kode QRIS di atas, atau pilih dari galeri foto HP Anda.</li>
                                    <li>Periksa nama merchant dan nominal Rp{{ number_format($pendingPayment?->amount_rupiah ?? $outstandingRupiah, 0, ',', '.') }}.</li>
                                    <li>Selesaikan pembayaran, lalu tekan tombol <em>Cek Pembayaran</em>.</li>
                                </ol>
                            </div>
                        @elseif ($isGateway && $pendingPayment?->gateway_payment_number)
                            {{-- Tampilan Virtual Account / Nomor Pembayaran Gateway --}}
                            <div class="mt-4 rounded-2xl border border-line bg-surface p-4">
                                <p class="text-xs font-semibold tracking-wider text-ink-500 uppercase">
                                    Nomor Pembayaran {{ $pendingPayment->gateway_method }}
                                </p>
                                <p id="va-number" class="mt-2 font-mono text-xl font-bold tracking-wider text-ink-950 select-all">
                                    {{ $pendingPayment->gateway_payment_number }}
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        onclick="copyPaymentNumber()"
                                        id="copy-btn"
                                        class="inline-flex items-center gap-2 rounded-xl border border-brand-600/30 bg-surface px-4 py-2 text-sm font-semibold text-brand-700 shadow-sm transition hover:bg-brand-50"
                                    >
                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                        </svg>
                                        Salin Nomor
                                    </button>
                                    @if ($pendingPayment->gateway_payment_url)
                                        <a
                                            href="{{ $pendingPayment->gateway_payment_url }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
                                        >
                                            Buka Halaman Pembayaran
                                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                            </svg>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @elseif ($isGateway && $pendingPayment?->gateway_payment_url)
                            <div class="mt-4 rounded-2xl border border-line bg-surface p-4 text-center">
                                <p class="text-sm font-medium text-ink-700">Pembayaran melalui {{ $pendingPayment->gateway_method ?? 'Payment Gateway' }} siap dilakukan.</p>
                                <div class="mt-3">
                                    <a
                                        href="{{ $pendingPayment->gateway_payment_url }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
                                    >
                                        Lanjutkan ke Halaman Pembayaran
                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        @endif

                        {{-- Opsi Ganti Metode Pembayaran --}}
                        @if ($isGateway)
                            <p class="mt-6 border-t border-line pt-4 text-sm text-ink-600">
                                Pembayaran gateway masih aktif. Selesaikan pembayaran atau tunggu konfirmasi gagal/batal dari penyedia sebelum mengganti metode.
                            </p>
                        @else
                            <div class="mt-6 border-t border-line pt-4">
                                @if ($gatewayUnavailable)
                                    <p class="mb-3 text-sm text-ink-600" role="status">Gateway sementara tidak tersedia. Gunakan QRIS cabang atau hubungi kasir.</p>
                                @endif
                                <details class="group">
                                    <summary class="flex cursor-pointer items-center justify-between text-xs font-semibold text-brand-700 uppercase hover:underline">
                                        <span>Pilih metode pembayaran lain</span>
                                        <span class="text-ink-400 transition group-open:rotate-180">&#9662;</span>
                                    </summary>
                                    <div class="mt-3 space-y-3 pt-2">
                                        {{-- Form ganti ke QRIS --}}
                                        @if ($pendingPayment === null && $order->branch->qris_enabled && $order->branch->qris_static_payload !== null)
                                            <form action="{{ $paymentActionUrl }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="method" value="qris">
                                                <button
                                                    type="submit"
                                                    class="w-full rounded-xl border border-brand-600/30 bg-surface px-4 py-2.5 text-left text-sm font-semibold text-brand-700 hover:bg-brand-50"
                                                >
                                                    &larr; Ganti ke QRIS Dinamis (Instan)
                                                </button>
                                            </form>
                                        @endif

                                        {{-- Form ganti ke Payment Gateway --}}
                                        @if (! empty($gatewayMethods))
                                            <form action="{{ $paymentActionUrl }}" method="POST" class="space-y-3">
                                                @csrf
                                                <input type="hidden" name="method" value="gateway">
                                                <div>
                                                    <label for="gateway_method" class="block text-xs font-medium text-ink-600">
                                                        Pilih Metode Payment Gateway (Virtual Account & E-Wallet):
                                                    </label>
                                                    <select
                                                        id="gateway_method"
                                                        name="gateway_method"
                                                        class="mt-1 block w-full rounded-xl border border-line bg-surface px-3 py-2.5 text-sm text-ink-950 focus:border-brand-600 focus:outline-none focus:ring-1 focus:ring-brand-600"
                                                        required
                                                    >
                                                        <option value="" disabled {{ ! $isGateway ? 'selected' : '' }}>-- Pilih metode pembayaran --</option>
                                                        @foreach ($gatewayMethods as $gm)
                                                            @continue(in_array($gm->code, ['VA', 'ALL'], true))
                                                            <option
                                                                value="{{ $gm->code }}"
                                                                {{ $isGateway && $pendingPayment?->gateway_method === $gm->code ? 'selected' : '' }}
                                                            >
                                                                {{ $gm->name }} (Rp{{ number_format($gm->minimumRupiah, 0, ',', '.') }} - Rp{{ number_format($gm->maximumRupiah, 0, ',', '.') }})
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <button
                                                    type="submit"
                                                    class="w-full rounded-xl bg-brand-600 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
                                                >
                                                    {{ $isGateway ? 'Ganti ke Metode Gateway Terpilih' : 'Bayar via Payment Gateway' }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </details>
                            </div>
                        @endif
                    </section>
                @endif

                {{-- Perjalanan Laundry (Timeline) --}}
                <section aria-labelledby="tracking-history-title">
                    <h2 id="tracking-history-title" class="font-display text-lg font-semibold">Perjalanan laundry</h2>

                    @if ($order->statusEvents->isEmpty())
                        <p class="mt-3 rounded-xl border border-line px-4 py-3 text-sm text-ink-500">
                            Status terbaru adalah {{ $order->status->label() }}.
                        </p>
                    @else
                        <ol class="mt-4 space-y-4">
                            @foreach ($order->statusEvents as $event)
                                <li class="relative border-l-2 border-brand-100 pl-4">
                                    <span class="absolute top-1 -left-[7px] size-3 rounded-full border-2 border-surface bg-brand-600"></span>
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold">{{ $event->status->label() }}</p>
                                            @if ($event->note)
                                                <p class="mt-1 text-sm leading-6 text-ink-700">{{ $event->note }}</p>
                                            @endif
                                        </div>
                                        <time class="shrink-0 text-right text-xs text-ink-500" datetime="{{ $event->created_at->toISOString() }}">
                                            {{ $event->created_at->translatedFormat('d M Y, H:i') }}
                                        </time>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>

                <p class="text-center text-xs leading-5 text-ink-500 no-print">
                    Simpan tautan ini untuk memantau status pesanan, melihat struk digital, dan melakukan pembayaran tanpa perlu masuk ke aplikasi.
                </p>
            </div>
        </main>

        <script>
            function copyPaymentNumber() {
                const el = document.getElementById('va-number');
                const btn = document.getElementById('copy-btn');
                if (!el || !btn) return;
                navigator.clipboard.writeText(el.innerText.trim()).then(() => {
                    const originalText = btn.innerHTML;
                    btn.innerText = 'Tersalin!';
                    setTimeout(() => { btn.innerHTML = originalText; }, 2000);
                });
            }
        </script>
    </body>
</html>
