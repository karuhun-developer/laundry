<?php

namespace App\Services;

use App\Models\Order;

class WhatsAppMessageRenderer
{
    public function render(Order $order, string $template, string $trackingUrl): string
    {
        $order->loadMissing('items:id,order_id,requested_weight_grams');
        $weightGrams = (int) $order->items->sum('requested_weight_grams');

        return strtr($template, [
            '{customer_name}' => $order->customer_name_snapshot,
            '{name}' => $order->customer_name_snapshot,
            '{order_number}' => $order->order_number ?? '-',
            '{status}' => $order->status->label(),
            '{branch_name}' => $order->branch->name,
            '{weight}' => $this->formatWeight($weightGrams),
            '{total}' => 'Rp'.number_format($order->total_rupiah, 0, ',', '.'),
            '{tracking_url}' => $trackingUrl,
        ]);
    }

    private function formatWeight(int $grams): string
    {
        $kilograms = number_format($grams / 1000, 2, ',', '.');

        return rtrim(rtrim($kilograms, '0'), ',').' kg';
    }
}
