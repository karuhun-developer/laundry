<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\View\View;

class PublicOrderReceiptController extends Controller
{
    public function __invoke(Order $order): View
    {
        $order->load(['branch.city', 'items', 'payments.statusEvents', 'statusEvents']);

        $paidRupiah = $order->payments
            ->filter(static fn (Payment $payment): bool => $payment->status === PaymentStatus::Paid)
            ->sum('amount_rupiah');

        return view('receipts.show', [
            'order' => $order,
            'paidRupiah' => $paidRupiah,
            'outstandingRupiah' => max(0, $order->total_rupiah - $paidRupiah),
            'canonicalUrl' => request()->fullUrl(),
        ]);
    }
}
