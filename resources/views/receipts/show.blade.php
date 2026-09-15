@php
    $title = 'Struk '.$order->order_number.' | LaundryHub';
    $description = 'Struk digital '.$order->order_number.' untuk '.$order->customer_name_snapshot.' dari '.$order->branch->name.', total Rp'.number_format($order->total_rupiah, 0, ',', '.').'.';
    $socialImageUrl = asset('apple-touch-icon.png');
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
    </head>
    <body class="min-h-screen bg-canvas px-4 py-8 font-sans text-ink-950">
        <main class="mx-auto max-w-lg overflow-hidden rounded-3xl border border-line bg-surface shadow-raised">
            <header class="bg-brand-700 px-6 py-7 text-center text-white">
                <p class="text-sm font-semibold tracking-[0.2em] text-white/75 uppercase">LaundryHub</p>
                <h1 class="mt-2 font-display text-2xl font-semibold">Struk digital</h1>
                <p class="mt-1 text-sm text-white/80">{{ $order->order_number }}</p>
            </header>

            <div class="space-y-5 p-6">
                <section>
                    <h2 class="font-display text-lg font-semibold">{{ $order->branch->name }}</h2>
                    <p class="mt-1 text-sm leading-6 text-ink-500">
                        {{ $order->branch->address }}, {{ $order->branch->city->name }}
                    </p>
                </section>

                <dl class="space-y-3 border-y border-dashed border-line py-4 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-500">Pelanggan</dt>
                        <dd class="text-right font-semibold">{{ $order->customer_name_snapshot }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-500">Tanggal pesanan</dt>
                        <dd class="text-right font-semibold">{{ $order->created_at->translatedFormat('d F Y, H:i') }}</dd>
                    </div>
                </dl>

                <section aria-labelledby="receipt-items-title">
                    <h2 id="receipt-items-title" class="text-sm font-semibold">Rincian laundry</h2>
                    <ul class="mt-3 space-y-3">
                        @foreach ($order->items as $item)
                            <li class="flex justify-between gap-4 text-sm">
                                <span>
                                    <span class="font-medium">{{ $item->service_name_snapshot }}</span>
                                    <span class="block text-xs text-ink-500">{{ number_format($item->requested_weight_grams / 1000, 2, ',', '.') }} kg</span>
                                </span>
                                <span class="shrink-0 font-semibold">Rp{{ number_format($item->subtotal_rupiah, 0, ',', '.') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <dl class="space-y-3 rounded-2xl bg-canvas p-4 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-500">Total</dt>
                        <dd class="font-display font-semibold text-brand-700">Rp{{ number_format($order->total_rupiah, 0, ',', '.') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-500">Sudah dibayar</dt>
                        <dd class="font-semibold">Rp{{ number_format($paidRupiah, 0, ',', '.') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-t border-line pt-3">
                        <dt class="font-semibold">Status pembayaran</dt>
                        <dd @class(['font-semibold', 'text-mint-500' => $outstandingRupiah === 0, 'text-amber-700' => $outstandingRupiah > 0])>
                            {{ $outstandingRupiah === 0 ? 'Lunas' : 'Sisa Rp'.number_format($outstandingRupiah, 0, ',', '.') }}
                        </dd>
                    </div>
                </dl>

                <p class="text-center text-xs leading-5 text-ink-500">
                    Simpan tautan ini untuk membuka kembali struk digital tanpa perlu masuk ke aplikasi.
                </p>
            </div>
        </main>
    </body>
</html>
