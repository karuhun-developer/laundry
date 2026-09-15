<?php

namespace App\Jobs;

use App\Enums\PushNotificationEvent;
use App\Enums\RoleName;
use App\Models\Order;
use App\Models\PushNotificationDevice;
use App\Services\FirebaseCloudMessaging;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;

class SendOrderPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $orderId,
        public PushNotificationEvent $event,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(FirebaseCloudMessaging $firebaseCloudMessaging): void
    {
        if (! $firebaseCloudMessaging->isConfigured()) {
            return;
        }

        $order = Order::query()->with('branch')->find($this->orderId);

        if ($order === null) {
            return;
        }

        [$title, $body] = $this->message($order);

        $this->devices($order)->each(function (PushNotificationDevice $device) use (
            $firebaseCloudMessaging,
            $title,
            $body,
            $order,
        ): void {
            $delivered = $firebaseCloudMessaging->send($device, $title, $body, [
                'type' => match ($this->event) {
                    PushNotificationEvent::PickupRequested => 'pickup_requested',
                    PushNotificationEvent::PricingConfirmed => 'pricing_confirmed',
                    PushNotificationEvent::StatusUpdated => 'status_updated',
                },
                'order_id' => (string) $order->id,
                'order_number' => (string) $order->order_number,
                'url' => '/app',
            ]);

            if (! $delivered) {
                $device->delete();
            }
        });
    }

    /** @return Builder<PushNotificationDevice> */
    private function devices(Order $order): Builder
    {
        return PushNotificationDevice::query()->whereHas(
            'user',
            function (Builder $query) use ($order): void {
                if ($this->event === PushNotificationEvent::PickupRequested) {
                    $query
                        ->whereHas('roles', fn (Builder $roleQuery): Builder => $roleQuery
                            ->where('name', RoleName::BranchAdmin->value))
                        ->whereHas('branches', fn (Builder $branchQuery): Builder => $branchQuery
                            ->whereKey($order->branch_id));

                    return;
                }

                $query->whereKey($order->customer_id);
            },
        );
    }

    /** @return array{string, string} */
    private function message(Order $order): array
    {
        return match ($this->event) {
            PushNotificationEvent::PickupRequested => [
                'Permintaan penjemputan baru',
                "{$order->customer_name_snapshot} meminta penjemputan pesanan {$order->order_number}.",
            ],
            PushNotificationEvent::PricingConfirmed => [
                'Harga pesanan dikonfirmasi',
                "Harga pesanan {$order->order_number} sudah dikonfirmasi cabang.",
            ],
            PushNotificationEvent::StatusUpdated => [
                'Status pesanan diperbarui',
                "Pesanan {$order->order_number} sekarang {$order->status->label()}.",
            ],
        };
    }
}
