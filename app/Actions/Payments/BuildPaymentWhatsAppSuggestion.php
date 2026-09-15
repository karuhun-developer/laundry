<?php

namespace App\Actions\Payments;

use App\Data\WhatsAppSuggestionData;
use App\Models\Order;
use App\Models\Payment;
use App\Services\WhatsAppPhoneNormalizer;
use LogicException;

final readonly class BuildPaymentWhatsAppSuggestion
{
    public function __construct(private WhatsAppPhoneNormalizer $phoneNormalizer) {}

    public function handle(Order $order, Payment $payment): WhatsAppSuggestionData
    {
        if ($payment->order_id !== $order->id) {
            throw new LogicException('Pembayaran tidak terkait dengan pesanan ini.');
        }

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
}
