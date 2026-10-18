<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentCannotBeCreated;
use App\Exceptions\QrisApiException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Qris\QrisApiClient;
use App\Services\Qris\QrisConfigurationResolver;
use Illuminate\Support\Facades\DB;

final readonly class RegenerateQrisPaymentAction
{
    public function __construct(
        private QrisConfigurationResolver $qrisResolver,
        private QrisApiClient $qrisApiClient,
    ) {}

    public function handle(Payment $payment, ?string $actorName = null): Payment
    {
        return DB::transaction(function () use ($payment, $actorName): Payment {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status !== PaymentStatus::Pending) {
                throw new PaymentCannotBeCreated('Pembayaran ini sudah tidak berstatus pending.');
            }

            if ($lockedPayment->method !== PaymentMethod::Qris) {
                throw new PaymentCannotBeCreated('Hanya pembayaran QRIS yang dapat digenerate ulang.');
            }

            $order = Order::query()->whereKey($lockedPayment->order_id)->lockForUpdate()->firstOrFail();
            $branch = $order->branch;

            $config = $this->qrisResolver->resolve($branch);
            if ($config === null) {
                throw PaymentCannotBeCreated::qrisUnavailable();
            }

            $idempotencyKey = sprintf('ORD-%s-%d-%s', $order->order_number ?? $order->id, $lockedPayment->amount_rupiah, substr(uniqid(), -6));

            try {
                $qrisRes = $this->qrisApiClient->create(
                    $config,
                    $lockedPayment->amount_rupiah,
                    $idempotencyKey,
                );
            } catch (QrisApiException $e) {
                throw new PaymentCannotBeCreated('Gagal membuat QRIS baru: '.$e->getMessage(), previous: $e);
            }

            $lockedPayment->update([
                'qris_payload' => $qrisRes->qrisString,
                'qris_token' => $qrisRes->token,
                'qris_unique_code' => $qrisRes->uniqueCode,
                'qris_expires_at' => $qrisRes->expiresAt,
            ]);

            $lockedPayment->statusEvents()->create([
                'actor_id' => null,
                'actor_name_snapshot' => $actorName ?? 'Pelanggan',
                'status' => PaymentStatus::Pending,
                'note' => 'QRIS dinamis diperbarui (masa aktif 15 menit).',
            ]);

            return $lockedPayment->fresh(['order', 'branch']);
        });
    }
}
