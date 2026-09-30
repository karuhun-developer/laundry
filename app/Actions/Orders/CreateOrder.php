<?php

namespace App\Actions\Orders;

use App\Actions\Catalog\QuotePriceOption;
use App\Actions\PushNotifications\DispatchOrderPushNotification;
use App\Data\CreateOrderData;
use App\Data\DeliveryQuoteData;
use App\Data\PriceQuoteData;
use App\Enums\OrderStatus;
use App\Enums\PushNotificationEvent;
use App\Enums\RoleName;
use App\Exceptions\OrderCannotBeCreated;
use App\Models\Branch;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PriceOption;
use App\Models\User;
use App\Services\DeliveryRouteCalculator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class CreateOrder
{
    public function __construct(
        private QuotePriceOption $quotePriceOption,
        private DeliveryRouteCalculator $deliveryRouteCalculator,
        private DispatchOrderPushNotification $dispatchOrderPushNotification,
    ) {}

    public function handle(
        Organization $organization,
        Branch $branch,
        User $actor,
        CreateOrderData $data,
    ): Order {
        $isCustomer = $actor->hasRole(RoleName::Customer->value);
        $order = DB::transaction(function () use ($organization, $branch, $actor, $data, $isCustomer): Order {
            $this->ensureActiveTenant($organization, $branch);

            if ($isCustomer && $actor->id !== $data->customerId) {
                throw new AuthorizationException;
            }

            $customer = User::query()
                ->customerOnly()
                ->whereKey($data->customerId)
                ->when(! $isCustomer, fn (Builder $query): Builder => $query
                    ->whereHas('customerOrganizations', fn (Builder $organizationQuery): Builder => $organizationQuery
                        ->whereKey($organization->id)))
                ->lockForUpdate()
                ->firstOrFail();

            if ($isCustomer) {
                $pendingPickup = Order::query()
                    ->where('customer_id', $customer->id)
                    ->where('status', OrderStatus::PickupRequested)
                    ->latest('id')
                    ->first();

                if ($pendingPickup !== null) {
                    throw OrderCannotBeCreated::pickupAlreadyRequested($pendingPickup);
                }
            }

            $pickupAddress = null;
            $customerLatitude = $data->customerLatitude;
            $customerLongitude = $data->customerLongitude;

            if ($isCustomer) {
                $pickupAddress = $this->pickupAddress($customer, $data);
                $customerLatitude = $pickupAddress->latitude;
                $customerLongitude = $pickupAddress->longitude;
            }

            $customer->customerOrganizations()->syncWithoutDetaching([$organization->id]);

            /** @var list<PriceQuoteData> $quotedItems */
            $quotedItems = [];
            /** @var list<PriceOption> $selectedPriceOptions */
            $selectedPriceOptions = [];

            foreach ($data->items as $item) {
                $priceOption = PriceOption::query()->findOrFail($item->priceOptionId);

                if ($isCustomer) {
                    $selectedPriceOptions[] = $this->availablePriceOption(
                        $organization,
                        $branch,
                        $priceOption,
                    );

                    continue;
                }

                if ($item->weightGrams === null) {
                    throw new LogicException('A staff-created order requires an item weight.');
                }

                $quotedItems[] = $this->quotePriceOption->handle(
                    organization: $organization,
                    branch: $branch,
                    priceOption: $priceOption,
                    weightGrams: $item->weightGrams,
                    customPricePerKilogramRupiah: $item->customPricePerKilogramRupiah,
                );
            }

            $deliveryQuote = $this->deliveryQuote($branch, $customerLatitude, $customerLongitude);
            $itemsSubtotalRupiah = array_sum(array_map(
                static fn (PriceQuoteData $quote): int => $quote->totalRupiah,
                $quotedItems,
            ));
            $deliveryFeeRupiah = $deliveryQuote === null ? 0 : $deliveryQuote->deliveryFeeRupiah;
            $initialStatus = $isCustomer ? OrderStatus::PickupRequested : OrderStatus::Received;
            $completionDurationHours = $isCustomer ? null : max(array_map(
                static fn (PriceQuoteData $quote): int => $quote->priceOption->service->completion_duration_hours ?? 72,
                $quotedItems,
            ));

            $order = Order::query()->create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'customer_address_id' => $pickupAddress?->id,
                'created_by_id' => $actor->id,
                'status' => $initialStatus,
                'customer_name_snapshot' => $customer->name,
                'customer_whatsapp_phone_snapshot' => $customer->whatsapp_phone,
                'customer_latitude' => $customerLatitude,
                'customer_longitude' => $customerLongitude,
                'pickup_label_snapshot' => $pickupAddress?->label,
                'pickup_recipient_name_snapshot' => $pickupAddress?->recipient_name,
                'pickup_whatsapp_phone_snapshot' => $pickupAddress?->whatsapp_phone,
                'pickup_address_snapshot' => $pickupAddress?->address,
                'pickup_notes_snapshot' => $pickupAddress?->notes,
                'delivery_distance_meters' => $deliveryQuote?->distanceMeters,
                'delivery_free_radius_meters_snapshot' => $deliveryQuote === null
                    ? null
                    : $branch->free_delivery_radius_meters,
                'delivery_fee_per_km_rupiah_snapshot' => $deliveryQuote === null
                    ? null
                    : $branch->delivery_fee_per_km_rupiah,
                'items_subtotal_rupiah' => $itemsSubtotalRupiah,
                'delivery_fee_rupiah' => $deliveryFeeRupiah,
                'total_rupiah' => $isCustomer ? 0 : $itemsSubtotalRupiah + $deliveryFeeRupiah,
                'priced_at' => $isCustomer ? null : now(),
                'completion_duration_hours_snapshot' => $completionDurationHours,
                'estimated_ready_at' => $isCustomer ? null : now()->addHours($completionDurationHours),
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
                    'price_per_kilogram_rupiah_snapshot' => $quote->effectivePricePerKilogramRupiah ?? $priceOption->price_per_kilogram_rupiah,
                    'min_price_per_kilogram_rupiah_snapshot' => $priceOption->effectiveMinPricePerKilogram(),
                    'minimum_chargeable_grams_snapshot' => $priceOption->minimum_chargeable_grams,
                    'requested_weight_grams' => $quote->requestedWeightGrams,
                    'chargeable_weight_grams' => $quote->chargeableWeightGrams,
                    'calculated_base_price_rupiah' => $quote->basePriceRupiah,
                    'excess_weight_grams' => $quote->excessWeightGrams,
                    'excess_price_rupiah' => $quote->excessPriceRupiah,
                    'subtotal_rupiah' => $quote->totalRupiah,
                ]);
            }

            foreach ($selectedPriceOptions as $priceOption) {
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
                    'min_price_per_kilogram_rupiah_snapshot' => $priceOption->effectiveMinPricePerKilogram(),
                    'minimum_chargeable_grams_snapshot' => $priceOption->minimum_chargeable_grams,
                    'requested_weight_grams' => 0,
                    'chargeable_weight_grams' => 0,
                    'calculated_base_price_rupiah' => 0,
                    'excess_weight_grams' => 0,
                    'excess_price_rupiah' => 0,
                    'subtotal_rupiah' => 0,
                ]);
            }

            $order->statusEvents()->create([
                'actor_id' => $actor->id,
                'actor_name_snapshot' => $actor->name,
                'status' => $initialStatus,
            ]);

            if ($isCustomer) {
                $task = $order->tasks()->create(['kind' => 'pickup', 'status' => 'waiting', 'address' => $pickupAddress->address]);
                $task->events()->create(['order_id' => $order->id, 'actor_id' => $actor->id,
                    'actor_name_snapshot' => $actor->name, 'status' => 'waiting', 'note' => 'Permintaan penjemputan dibuat.']);
            }

            activity('orders')
                ->causedBy($actor)
                ->performedOn($order)
                ->withProperties([
                    'order_number' => $order->order_number,
                    'branch_id' => $branch->id,
                    'customer_id' => $customer->id,
                    'total_rupiah' => $order->total_rupiah,
                    'pricing_confirmed' => ! $isCustomer,
                ])
                ->log('order created');

            return $order->loadWorkspace();
        });

        if ($isCustomer) {
            $this->dispatchOrderPushNotification->handle(
                $order,
                PushNotificationEvent::PickupRequested,
            );
        }

        return $order;
    }

    private function availablePriceOption(
        Organization $organization,
        Branch $branch,
        PriceOption $priceOption,
    ): PriceOption {
        return PriceOption::query()
            ->with('service')
            ->whereKey($priceOption->id)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('branch_id')
                ->orWhere('branch_id', $branch->id))
            ->where('is_active', true)
            ->whereHas('service', fn (Builder $query): Builder => $query
                ->where('organization_id', $organization->id)
                ->where('is_active', true))
            ->firstOrFail();
    }

    private function ensureActiveTenant(Organization $organization, Branch $branch): void
    {
        if (! $organization->is_active
            || ! $branch->is_active
            || $branch->organization_id !== $organization->id) {
            throw (new ModelNotFoundException)->setModel(Branch::class, [$branch->id]);
        }
    }

    private function pickupAddress(User $customer, CreateOrderData $data): CustomerAddress
    {
        return CustomerAddress::query()
            ->where('customer_id', $customer->id)
            ->whereKey($data->customerAddressId)
            ->firstOrFail();
    }

    private function deliveryQuote(Branch $branch, ?float $latitude, ?float $longitude): ?DeliveryQuoteData
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        if ($branch->latitude === null || $branch->longitude === null) {
            throw OrderCannotBeCreated::branchCoordinatesRequired();
        }

        return $this->deliveryRouteCalculator->quote(
            $branch,
            $latitude,
            $longitude,
        );
    }
}
