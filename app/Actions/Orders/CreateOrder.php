<?php

namespace App\Actions\Orders;

use App\Actions\Catalog\QuotePriceOption;
use App\Data\CreateOrderData;
use App\Data\DeliveryQuoteData;
use App\Data\PriceQuoteData;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Exceptions\OrderCannotBeCreated;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PriceOption;
use App\Models\User;
use App\Services\DeliveryRouteCalculator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final readonly class CreateOrder
{
    public function __construct(
        private QuotePriceOption $quotePriceOption,
        private DeliveryRouteCalculator $deliveryRouteCalculator,
    ) {}

    public function handle(
        Organization $organization,
        Branch $branch,
        User $actor,
        CreateOrderData $data,
    ): Order {
        return DB::transaction(function () use ($organization, $branch, $actor, $data): Order {
            $this->ensureActiveTenant($organization, $branch);

            if ($actor->hasRole(RoleName::Customer->value) && $actor->id !== $data->customerId) {
                throw new AuthorizationException;
            }

            $customer = User::query()
                ->whereKey($data->customerId)
                ->whereHas('roles', fn (Builder $query): Builder => $query
                    ->where('name', RoleName::Customer->value))
                ->where(fn (Builder $query): Builder => $query
                    ->whereNull('organization_id')
                    ->orWhere('organization_id', $organization->id))
                ->firstOrFail();

            /** @var list<PriceQuoteData> $quotedItems */
            $quotedItems = [];

            foreach ($data->items as $item) {
                $priceOption = PriceOption::query()->findOrFail($item->priceOptionId);
                $quotedItems[] = $this->quotePriceOption->handle(
                    organization: $organization,
                    branch: $branch,
                    priceOption: $priceOption,
                    weightGrams: $item->weightGrams,
                );
            }

            $deliveryQuote = $this->deliveryQuote($branch, $data);
            $itemsSubtotalRupiah = array_sum(array_map(
                static fn (PriceQuoteData $quote): int => $quote->totalRupiah,
                $quotedItems,
            ));
            $deliveryFeeRupiah = $deliveryQuote === null ? 0 : $deliveryQuote->deliveryFeeRupiah;

            $order = Order::query()->create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'created_by_id' => $actor->id,
                'status' => OrderStatus::Received,
                'customer_name_snapshot' => $customer->name,
                'customer_whatsapp_phone_snapshot' => $customer->whatsapp_phone,
                'customer_latitude' => $data->customerLatitude,
                'customer_longitude' => $data->customerLongitude,
                'delivery_distance_meters' => $deliveryQuote?->distanceMeters,
                'delivery_free_radius_meters_snapshot' => $deliveryQuote === null
                    ? null
                    : $branch->free_delivery_radius_meters,
                'delivery_fee_per_km_rupiah_snapshot' => $deliveryQuote === null
                    ? null
                    : $branch->delivery_fee_per_km_rupiah,
                'items_subtotal_rupiah' => $itemsSubtotalRupiah,
                'delivery_fee_rupiah' => $deliveryFeeRupiah,
                'total_rupiah' => $itemsSubtotalRupiah + $deliveryFeeRupiah,
            ]);

            $order->forceFill([
                'order_number' => sprintf(
                    'LDR-%s-%08d',
                    $order->created_at->format('Ymd'),
                    $order->id,
                ),
            ])->save();

            foreach ($quotedItems as $quote) {
                $priceOption = $quote->priceOption;

                $order->items()->create([
                    'service_id' => $priceOption->service_id,
                    'price_option_id' => $priceOption->id,
                    'service_name_snapshot' => $priceOption->service->name,
                    'service_code_snapshot' => $priceOption->service->code,
                    'price_option_name_snapshot' => $priceOption->name,
                    'price_type_snapshot' => $priceOption->type,
                    'package_weight_grams_snapshot' => $priceOption->package_weight_grams,
                    'base_price_rupiah_snapshot' => $priceOption->base_price_rupiah,
                    'price_per_kilogram_rupiah_snapshot' => $priceOption->price_per_kilogram_rupiah,
                    'minimum_chargeable_grams_snapshot' => $priceOption->minimum_chargeable_grams,
                    'requested_weight_grams' => $quote->requestedWeightGrams,
                    'chargeable_weight_grams' => $quote->chargeableWeightGrams,
                    'calculated_base_price_rupiah' => $quote->basePriceRupiah,
                    'excess_weight_grams' => $quote->excessWeightGrams,
                    'excess_price_rupiah' => $quote->excessPriceRupiah,
                    'subtotal_rupiah' => $quote->totalRupiah,
                ]);
            }

            $order->statusEvents()->create([
                'actor_id' => $actor->id,
                'actor_name_snapshot' => $actor->name,
                'status' => OrderStatus::Received,
            ]);

            activity('orders')
                ->causedBy($actor)
                ->performedOn($order)
                ->withProperties([
                    'order_number' => $order->order_number,
                    'branch_id' => $branch->id,
                    'customer_id' => $customer->id,
                    'total_rupiah' => $order->total_rupiah,
                ])
                ->log('order created');

            return $order->load(['branch', 'items', 'statusEvents', 'payments.statusEvents']);
        });
    }

    private function ensureActiveTenant(Organization $organization, Branch $branch): void
    {
        if (! $organization->is_active
            || ! $branch->is_active
            || $branch->organization_id !== $organization->id) {
            throw (new ModelNotFoundException)->setModel(Branch::class, [$branch->id]);
        }
    }

    private function deliveryQuote(Branch $branch, CreateOrderData $data): ?DeliveryQuoteData
    {
        if ($data->customerLatitude === null || $data->customerLongitude === null) {
            return null;
        }

        if ($branch->latitude === null || $branch->longitude === null) {
            throw OrderCannotBeCreated::branchCoordinatesRequired();
        }

        return $this->deliveryRouteCalculator->quote(
            $branch,
            $data->customerLatitude,
            $data->customerLongitude,
        );
    }
}
