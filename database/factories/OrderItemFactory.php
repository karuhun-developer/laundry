<?php

namespace Database\Factories;

use App\Enums\PriceType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PriceOption;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'service_id' => Service::factory(),
            'price_option_id' => PriceOption::factory(),
            'service_name_snapshot' => 'Cuci Kering Lipat',
            'service_code_snapshot' => 'CKL',
            'price_option_name_snapshot' => 'Kiloan reguler',
            'price_type_snapshot' => PriceType::PerKilogram,
            'package_weight_grams_snapshot' => null,
            'base_price_rupiah_snapshot' => null,
            'price_per_kilogram_rupiah_snapshot' => 12000,
            'minimum_chargeable_grams_snapshot' => 1000,
            'requested_weight_grams' => 1000,
            'chargeable_weight_grams' => 1000,
            'calculated_base_price_rupiah' => 0,
            'excess_weight_grams' => 0,
            'excess_price_rupiah' => 0,
            'subtotal_rupiah' => 12000,
        ];
    }
}
