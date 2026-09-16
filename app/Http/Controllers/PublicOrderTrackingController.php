<?php

namespace App\Http\Controllers;

use App\Actions\Payments\CreatePayment;
use App\Data\CreatePaymentData;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Qris\QrisImageGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\URL;

class PublicOrderTrackingController extends Controller
{
    public function __invoke(
        Order $order,
        CreatePayment $createPayment,
        PaymentGatewayManager $gatewayManager,
    ): View {
        $order->load(['branch.city', 'items', 'statusEvents', 'payments.statusEvents']);

        $paidRupiah = (int) $order->payments
            ->filter(static fn (Payment $payment): bool => $payment->status === PaymentStatus::Paid)
            ->sum('amount_rupiah');
        $outstandingRupiah = max(0, $order->total_rupiah - $paidRupiah);

        /** @var Payment|null $pendingPayment */
        $pendingPayment = $order->payments
            ->filter(static fn (Payment $payment): bool => $payment->status === PaymentStatus::Pending)
            ->last();

        $hasCancelledPayment = $order->payments
            ->contains(static fn (Payment $payment): bool => $payment->status === PaymentStatus::Cancelled);

        if (
            $pendingPayment === null
            && ! $hasCancelledPayment
            && $outstandingRupiah > 0
            && $order->priced_at !== null
            && $order->branch->qris_enabled
            && $order->branch->qris_static_payload !== null
        ) {
            $pendingPayment = $createPayment->handle(
                order: $order,
                branch: $order->branch,
                actor: null,
                data: new CreatePaymentData(
                    method: PaymentMethod::Qris,
                    amountRupiah: $outstandingRupiah,
                    note: 'Dibuat otomatis untuk tautan publik pesanan.',
                    gatewayDriver: null,
                    gatewayMethod: null,
                ),
            );
        }

        $qrisImageDataUri = $pendingPayment !== null && $pendingPayment->qris_payload !== null
            ? (new QrisImageGenerator)->dataUri($pendingPayment->qris_payload)
            : null;

        $gatewayMethods = [];
        $gatewayUnavailable = false;

        if ($order->priced_at !== null && $outstandingRupiah > 0 && $pendingPayment?->gateway_driver === null && $pendingPayment?->method !== PaymentMethod::Gateway) {
            try {
                $gatewayMethods = $gatewayManager->availableMethods();
            } catch (PaymentGatewayException $exception) {
                report($exception);
                $gatewayUnavailable = true;
            }
        }

        $settledPayments = $order->payments
            ->filter(static fn (Payment $payment): bool => $payment->status === PaymentStatus::Paid);

        return view('orders.track', [
            'order' => $order,
            'paidRupiah' => $paidRupiah,
            'outstandingRupiah' => $outstandingRupiah,
            'pendingPayment' => $pendingPayment,
            'settledPayments' => $settledPayments,
            'qrisImageDataUri' => $qrisImageDataUri,
            'gatewayMethods' => $gatewayMethods,
            'gatewayUnavailable' => $gatewayUnavailable,
            'canonicalUrl' => request()->fullUrl(),
            'paymentActionUrl' => URL::signedRoute('orders.public.payment.store', ['order' => $order->order_number]),
        ]);
    }
}
