@php
    $title = 'Status '.$order->order_number.' | LaundryHub';
    $description = 'Pantau pesanan '.$order->order_number.' dari '.$order->branch->name.'. Status saat ini: '.$order->status->label().'.';
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
                <h1 class="mt-2 font-display text-2xl font-semibold">Status pesanan</h1>
                <p class="mt-1 text-sm text-white/80">{{ $order->order_number }}</p>
            </header>

            <div class="space-y-6 p-6">
                <section class="rounded-2xl bg-canvas p-5 text-center">
                    <p class="text-xs font-semibold tracking-[0.16em] text-ink-500 uppercase">Status saat ini</p>
                    <p @class([
                        'mt-2 font-display text-2xl font-semibold',
                        'text-mint-500' => $order->status === \App\Enums\OrderStatus::Completed,
                        'text-red-600' => $order->status === \App\Enums\OrderStatus::Cancelled,
                        'text-brand-700' => ! in_array($order->status, [\App\Enums\OrderStatus::Completed, \App\Enums\OrderStatus::Cancelled], true),
                    ])>
                        {{ $order->status->label() }}
                    </p>
                    <p class="mt-2 text-sm text-ink-500">{{ $order->branch->name }}</p>
                </section>

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

                <dl class="border-t border-dashed border-line pt-4 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-500">Pesanan dibuat</dt>
                        <dd class="text-right font-semibold">{{ $order->created_at->translatedFormat('d F Y, H:i') }}</dd>
                    </div>
                </dl>

                <p class="text-center text-xs leading-5 text-ink-500">
                    Simpan tautan ini untuk memantau perubahan status pesanan tanpa perlu masuk ke aplikasi.
                </p>
            </div>
        </main>
    </body>
</html>
