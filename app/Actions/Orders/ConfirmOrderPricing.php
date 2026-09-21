<?php

namespace App\Actions\Orders;

use App\Actions\Catalog\QuotePriceOption;
use App\Actions\PushNotifications\DispatchOrderPushNotification;
use App\Data\ConfirmOrderPricingData;
use App\Enums\OrderStatus;
use App\Enums\PushNotificationEvent;
use App\Exceptions\OrderPricingCannotBeConfirmed;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PriceOption;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class ConfirmOrderPricing
{
    public function __construct(
        private QuotePriceOption $quotePriceOption,
        private DispatchOrderPushNotification $dispatchOrderPushNotification,
    ) {}

    public function handle(
        Organization $organization,
        Branch $branch,
        Order $order,
        User $actor,
        ConfirmOrderPricingData $data,
    ): Order {
        $confirmedOrder = DB::transaction(function () use (
            $organization,
            $branch,
            $order,
            $actor,
            $data,
        ): Order {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status !== OrderStatus::PickupRequested
                || $lockedOrder->priced_at !== null) {
                throw OrderPricingCannotBeConfirmed::invalidState();
            }

            $orderItem = $lockedOrder->items()->lockForUpdate()->firstOrFail();

            $priceOption = PriceOption::query()->findOrFail($data->priceOptionId);
            $quote = $this->quotePriceOption->handle(
                organization: $organization,
                branch: $branch,
                priceOption: $priceOption,
                weightGrams: $data->weightGrams,
                customPricePerKilogramRupiah: $data->customPricePerKilogramRupiah,
            );
            $availablePriceOption = $quote->priceOption;
            $effectivePricePerKilogram = $quote->effectivePricePerKilogramRupiah
                ?? $availablePriceOption->price_per_kilogram_rupiah;

            $orderItem->update([
                'service_id' => $availablePriceOption->service_id,
                'price_option_id' => $availablePriceOption->id,
                'service_name_snapshot' => $availablePriceOption->service->name,
                'service_code_snapshot' => $availablePriceOption->service->code,
                'price_option_name_snapshot' => $availablePriceOption->name,
                'price_type_snapshot' => $availablePriceOption->type,
                'package_weight_grams_snapshot' => $availablePriceOption->package_weight_grams,
                'base_price_rupiah_snapshot' => $availablePriceOption->base_price_rupiah,
                'price_per_kilogram_rupiah_snapshot' => $effectivePricePerKilogram,
                'min_price_per_kilogram_rupiah_snapshot' => $availablePriceOption->effectiveMinPricePerKilogram(),
                'minimum_chargeable_grams_snapshot' => $availablePriceOption->minimum_chargeable_grams,
                'requested_weight_grams' => $quote->requestedWeightGrams,
                'chargeable_weight_grams' => $quote->chargeableWeightGrams,
                'calculated_base_price_rupiah' => $quote->basePriceRupiah,
                'excess_weight_grams' => $quote->excessWeightGrams,
                'excess_price_rupiah' => $quote->excessPriceRupiah,
                'subtotal_rupiah' => $quote->totalRupiah,
            ]);

            $previousStatus = $lockedOrder->status;
            $lockedOrder->update([
                'status' => OrderStatus::Weighed,
                'items_subtotal_rupiah' => $quote->totalRupiah,
                'total_rupiah' => $quote->totalRupiah + $lockedOrder->delivery_fee_rupiah,
                'priced_at' => now(),
            ]);
            $lockedOrder->statusEvents()->create([
                'actor_id' => $actor->id,
                'actor_name_snapshot' => $actor->name,
                'status' => OrderStatus::Weighed,
                'note' => 'Berat dan harga dikonfirmasi petugas.',
            ]);

            activity('orders')
                ->causedBy($actor)
                ->performedOn($lockedOrder)
                ->withProperties([
                    'from_status' => $previousStatus->value,
                    'to_status' => OrderStatus::Weighed->value,
                    'price_option_id' => $availablePriceOption->id,
                    'weight_grams' => $quote->requestedWeightGrams,
                    'items_subtotal_rupiah' => $quote->totalRupiah,
                    'delivery_fee_rupiah' => $lockedOrder->delivery_fee_rupiah,
                    'total_rupiah' => $lockedOrder->total_rupiah,
                ])
                ->log('order pricing confirmed');

            return $lockedOrder->load([
                'organization',
                'branch',
                'items',
                'statusEvents',
                'payments.statusEvents',
            ]);
        });

        $this->dispatchOrderPushNotification->handle(
            $confirmedOrder,
            PushNotificationEvent::PricingConfirmed,
        );

        return $confirmedOrder;
    }
}
