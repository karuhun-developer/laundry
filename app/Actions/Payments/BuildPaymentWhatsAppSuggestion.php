<?php

namespace App\Actions\Payments;

use App\Data\WhatsAppSuggestionData;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\WhatsAppPhoneNormalizer;
use Illuminate\Support\Facades\URL;
use LogicException;

final readonly class BuildPaymentWhatsAppSuggestion
{
    public function __construct(private WhatsAppPhoneNormalizer $phoneNormalizer) {}

    public function handle(Order $order, Payment $payment, ?User $actor = null): WhatsAppSuggestionData
    {
        if ($payment->order_id !== $order->id) {
            throw new LogicException('Pembayaran tidak terkait dengan pesanan ini.');
        }

        if ($actor !== null && ! $actor->hasRole(RoleName::Customer->value)) {
            return $this->buildCustomerPaymentShareSuggestion($order, $payment);
        }

        return $this->buildBranchVerificationSuggestion($order, $payment);
    }

    private function buildBranchVerificationSuggestion(Order $order, Payment $payment): WhatsAppSuggestionData
    {
        $order->loadMissing('branch:id,name,phone');
        $phone = $this->phoneNormalizer->normalize($order->branch->phone);

        if ($phone === null) {
            return WhatsAppSuggestionData::unavailable(
                'Nomor WhatsApp cabang belum tersedia atau tidak valid.',
            );
        }

        $paymentNumber = $payment->payment_number ?? 'Pembayaran #'.$payment->id;
        $amount = 'Rp'.number_format($payment->amount_rupiah, 0, ',', '.');
        $message = implode("\n", [
            'Halo '.$order->branch->name.', saya ingin meminta verifikasi pembayaran.',
            'Nomor pesanan: '.$order->order_number,
            'Nomor pembayaran: '.$paymentNumber,
            'Metode pembayaran: '.$payment->method->label(),
            'Nominal: '.$amount,
            'Mohon bantu verifikasi pembayaran ini. Terima kasih.',
        ]);
        $query = http_build_query(['text' => $message], '', '&', PHP_QUERY_RFC3986);

        return new WhatsAppSuggestionData(
            available: true,
            phone: $phone,
            message: $message,
            url: 'https://wa.me/'.$phone.'?'.$query,
            templateId: null,
            unavailableReason: null,
        );
    }

    private function buildCustomerPaymentShareSuggestion(Order $order, Payment $payment): WhatsAppSuggestionData
    {
        $order->loadMissing(['branch:id,name', 'customer:id,whatsapp_phone']);

        $rawPhone = $order->customer_whatsapp_phone_snapshot
            ?? $order->customer?->whatsapp_phone;
        $phone = $this->phoneNormalizer->normalize($rawPhone);

        if ($phone === null) {
            return WhatsAppSuggestionData::unavailable(
                'Nomor WhatsApp pelanggan belum tersedia atau tidak valid.',
            );
        }

        $customerName = $order->customer_name_snapshot ?? 'Pelanggan';
        $paymentNumber = $payment->payment_number ?? 'Pembayaran #'.$payment->id;
        $amount = 'Rp'.number_format($payment->amount_rupiah, 0, ',', '.');
        $trackingUrl = URL::signedRoute('orders.public.show', [
            'order' => $order->order_number,
        ]);

        $lines = [
            'Halo '.$customerName.', berikut rincian pembayaran untuk pesanan '.$order->order_number.' di '.$order->branch->name.':',
            'Nomor pembayaran: '.$paymentNumber,
            'Metode pembayaran: '.$payment->method->label(),
            'Nominal: '.$amount,
        ];

        if ($payment->method === PaymentMethod::Qris) {
            $lines[] = 'Silakan selesaikan pembayaran melalui QRIS.';
        } elseif ($payment->gateway_payment_number !== null) {
            $lines[] = 'Nomor pembayaran: '.$payment->gateway_payment_number;
        }

        $lines[] = 'Pantau status pesanan Anda di: '.$trackingUrl;
        $lines[] = 'Terima kasih.';

        $message = implode("\n", $lines);
        $query = http_build_query(['text' => $message], '', '&', PHP_QUERY_RFC3986);

        return new WhatsAppSuggestionData(
            available: true,
            phone: $phone,
            message: $message,
            url: 'https://wa.me/'.$phone.'?'.$query,
            templateId: null,
            unavailableReason: null,
        );
    }
}
