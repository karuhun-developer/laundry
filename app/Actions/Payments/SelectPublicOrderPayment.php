<?php

namespace App\Actions\Payments;

use App\Data\CreatePaymentData;
use App\Enums\PaymentGatewayDriver;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentCannotBeCreated;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

final readonly class SelectPublicOrderPayment
{
    public function __construct(private CreatePayment $createPayment) {}

    public function handle(
        Order $order,
        PaymentMethod $method,
        ?PaymentGatewayDriver $gatewayDriver = null,
        ?string $gatewayMethod = null,
    ): Payment {
        return DB::transaction(function () use ($order, $method, $gatewayDriver, $gatewayMethod): Payment {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->priced_at === null) {
                throw PaymentCannotBeCreated::pricingNotConfirmed();
            }

            $paidRupiah = (int) $lockedOrder->payments()->where('status', PaymentStatus::Paid)->sum('amount_rupiah');
            $outstandingRupiah = max(0, $lockedOrder->total_rupiah - $paidRupiah);

            if ($outstandingRupiah === 0) {
                throw PaymentCannotBeCreated::orderAlreadyPaid();
            }

            if ($method === PaymentMethod::Cash) {
                throw new PaymentCannotBeCreated('Pembayaran tunai dilakukan di kasir cabang.');
            }

            $gatewayMethod = $gatewayMethod !== null ? strtoupper(trim($gatewayMethod)) : null;
            $pending = $lockedOrder->payments()->where('status', PaymentStatus::Pending)->lockForUpdate()->first();

            if ($pending instanceof Payment) {
                if ($pending->method === $method && ($method !== PaymentMethod::Gateway
                    || ($pending->gateway_driver === $gatewayDriver && $pending->gateway_method === $gatewayMethod))) {
                    return $pending;
                }

                if ($pending->gateway_driver !== null || $pending->method === PaymentMethod::Gateway) {
                    throw new PaymentCannotBeCreated('Pembayaran gateway masih aktif. Selesaikan pembayaran atau tunggu konfirmasi gagal/batal dari penyedia sebelum mengganti metode.');
                }

                $pending->update(['status' => PaymentStatus::Cancelled]);
                $pending->statusEvents()->create([
                    'actor_id' => null,
                    'actor_name_snapshot' => $lockedOrder->customer_name_snapshot ?? 'Pelanggan',
                    'status' => PaymentStatus::Cancelled,
                    'note' => 'Metode pembayaran diganti oleh pelanggan.',
                ]);
            }

            $payment = $this->createPayment->handle(
                order: $lockedOrder,
                branch: $lockedOrder->branch,
                actor: null,
                data: new CreatePaymentData(
                    method: $method,
                    amountRupiah: $outstandingRupiah,
                    note: 'Dibuat oleh pelanggan melalui tautan publik pesanan.',
                    gatewayDriver: $gatewayDriver,
                    gatewayMethod: $gatewayMethod,
                ),
            );

            if (in_array($payment->status, [PaymentStatus::Failed, PaymentStatus::Cancelled], true)) {
                throw new PaymentCannotBeCreated('Penyedia tidak berhasil membuat pembayaran. Metode sebelumnya tetap tersedia.');
            }

            return $payment;
        });
    }
}
