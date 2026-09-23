<?php

namespace App\Actions\MessageTemplates;

use App\Enums\OrderStatus;
use App\Models\MessageTemplate;
use App\Models\Organization;

final class GenerateDefaultMessageTemplate
{
    public function handle(Organization $organization): void
    {
        foreach (OrderStatus::cases() as $status) {
            MessageTemplate::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'scope_key' => 'organization',
                    'order_status' => $status,
                ],
                [
                    'branch_id' => null,
                    'body' => $this->bodyFor($status),
                    'is_active' => true,
                ],
            );
        }
    }

    private function bodyFor(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::PickupRequested => 'Halo {name}, pesanan {order_number} sudah diterima dan menunggu dijemput oleh {branch_name}. Pantau statusnya di {tracking_url}',
            OrderStatus::Received => 'Halo {name}, pesanan {order_number} sudah diterima di {branch_name}.',
            OrderStatus::Weighed => 'Halo {name}, laundry {order_number} sudah ditimbang {weight}. Total pembayaran {total}. Silakan bayar melalui QRIS yang ada di halaman {tracking_url}.',
            OrderStatus::Washing => 'Halo {name}, pesanan {order_number} sedang dicuci di {branch_name}.',
            OrderStatus::Drying => 'Halo {name}, pesanan {order_number} sedang dikeringkan.',
            OrderStatus::Ironing => 'Halo {name}, pesanan {order_number} sedang disetrika.',
            OrderStatus::ReadyForPickup => 'Halo {name}, pesanan {order_number} sudah siap diambil atau diantar. Pantau di {tracking_url}',
            OrderStatus::Completed => 'Halo {name}, pesanan {order_number} telah selesai. Terima kasih sudah menggunakan layanan kami.',
            OrderStatus::Cancelled => 'Halo {name}, pesanan {order_number} telah dibatalkan. Silakan hubungi {branch_name} untuk informasi lebih lanjut.',
        };
    }
}
