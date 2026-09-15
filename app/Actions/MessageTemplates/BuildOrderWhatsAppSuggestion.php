<?php

namespace App\Actions\MessageTemplates;

use App\Data\WhatsAppSuggestionData;
use App\Models\MessageTemplate;
use App\Models\Order;
use App\Services\WhatsAppMessageRenderer;
use App\Services\WhatsAppPhoneNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;

final readonly class BuildOrderWhatsAppSuggestion
{
    public function __construct(
        private WhatsAppMessageRenderer $renderer,
        private WhatsAppPhoneNormalizer $phoneNormalizer,
    ) {}

    public function handle(Order $order): WhatsAppSuggestionData
    {
        $template = MessageTemplate::query()
            ->where('organization_id', $order->organization_id)
            ->where('order_status', $order->status)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($order): void {
                $query->where('scope_key', 'branch:'.$order->branch_id)
                    ->orWhere('scope_key', 'organization');
            })
            ->orderByRaw('CASE WHEN branch_id = ? THEN 0 ELSE 1 END', [$order->branch_id])
            ->first();

        if ($template === null) {
            return WhatsAppSuggestionData::unavailable(
                'Template WhatsApp aktif belum tersedia untuk status ini.',
            );
        }

        $phone = $this->phoneNormalizer->normalize($order->customer_whatsapp_phone_snapshot);

        if ($phone === null) {
            return WhatsAppSuggestionData::unavailable(
                'Nomor WhatsApp pelanggan belum tersedia atau tidak valid.',
            );
        }

        $order->loadMissing('branch:id,name');
        $trackingUrl = URL::signedRoute('orders.public.show', [
            'order' => $order->order_number,
        ]);
        $message = $this->renderer->render($order, $template->body, $trackingUrl);
        $query = http_build_query(['text' => $message], '', '&', PHP_QUERY_RFC3986);

        return new WhatsAppSuggestionData(
            available: true,
            phone: $phone,
            message: $message,
            url: 'https://wa.me/'.$phone.'?'.$query,
            templateId: $template->id,
            unavailableReason: null,
        );
    }
}
