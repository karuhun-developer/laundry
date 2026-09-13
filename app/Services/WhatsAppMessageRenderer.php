<?php

namespace App\Services;

use App\Models\Order;

class WhatsAppMessageRenderer
{
    public function render(Order $order, string $template, string $trackingUrl): string
    {
        return strtr($template, [
            '{customer_name}' => $order->customer_name_snapshot,
            '{name}' => $order->customer_name_snapshot,
            '{order_number}' => $order->order_number ?? '-',
            '{status}' => $order->status->label(),
            '{branch_name}' => $order->branch->name,
            '{tracking_url}' => $trackingUrl,
        ]);
    }
}
