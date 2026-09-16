<?php

namespace App\Http\Controllers;

use App\Actions\Payments\CreatePayment;
use App\Data\CreatePaymentData;
use App\Enums\PaymentGatewayDriver;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class PublicOrderPaymentController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        Order $order,
        CreatePayment $createPayment,
        PaymentGatewayManager $gatewayManager,
    ): RedirectResponse {
        $validated = $request->validate([
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'gateway_driver' => ['nullable', Rule::enum(PaymentGatewayDriver::class)],
            'gateway_method' => ['nullable', 'string', 'max:40', Rule::notIn(['VA', 'ALL'])],
        ]);

        $trackingUrl = URL::signedRoute('orders.public.show', ['order' => $order->order_number]);

        if ($order->priced_at === null) {
            return redirect()->to($trackingUrl)->with('error', 'Harga pesanan belum dikonfirmasi.');
        }

        $paidRupiah = (int) $order->payments()
            ->where('status', PaymentStatus::Paid)
            ->sum('amount_rupiah');
        $outstandingRupiah = max(0, $order->total_rupiah - $paidRupiah);

        if ($outstandingRupiah === 0) {
            return redirect()->to($trackingUrl)->with('status', 'Pesanan ini sudah lunas.');
        }

        $method = PaymentMethod::from((string) $validated['method']);

        if ($method === PaymentMethod::Cash) {
            return redirect()->to($trackingUrl)->with('error', 'Pembayaran tunai dilakukan di kasir cabang.');
        }

        $order->loadMissing('branch');

        if ($method === PaymentMethod::Qris && (! $order->branch->qris_enabled || $order->branch->qris_static_payload === null)) {
            return redirect()->to($trackingUrl)->with('error', 'Metode pembayaran QRIS belum tersedia untuk cabang ini.');
        }

        $gatewayDriver = null;
        $gatewayMethod = null;

        if ($method === PaymentMethod::Gateway) {
            if (empty($validated['gateway_method'])) {
                return redirect()->to($trackingUrl)->with('error', 'Metode payment gateway wajib dipilih.');
            }

            $rawDriver = $validated['gateway_driver'] ?? null;
            $gatewayDriver = is_string($rawDriver) && $rawDriver !== ''
                ? PaymentGatewayDriver::from($rawDriver)
                : PaymentGatewayDriver::Paywuz;
            $gatewayMethod = (string) $validated['gateway_method'];
        }

        /** @var Payment|null $existingPending */
        $existingPending = $order->payments()->where('status', PaymentStatus::Pending)->first();

        if ($existingPending instanceof Payment) {
            if (
                $existingPending->method === $method
                && ($method !== PaymentMethod::Gateway || $existingPending->gateway_method === $gatewayMethod)
            ) {
                return redirect()->to($trackingUrl);
            }

            $existingPending->update(['status' => PaymentStatus::Cancelled]);
            $existingPending->statusEvents()->create([
                'actor_id' => null,
                'actor_name_snapshot' => $order->customer_name_snapshot ?? 'Pelanggan',
                'status' => PaymentStatus::Cancelled,
                'note' => 'Metode pembayaran diganti oleh pelanggan.',
            ]);
        }

        $createPayment->handle(
            order: $order,
            branch: $order->branch,
            actor: null,
            data: new CreatePaymentData(
                method: $method,
                amountRupiah: $outstandingRupiah,
                note: 'Dibuat oleh pelanggan melalui tautan publik pesanan.',
                gatewayDriver: $gatewayDriver,
                gatewayMethod: $gatewayMethod,
            ),
        );

        return redirect()->to($trackingUrl);
    }
}
