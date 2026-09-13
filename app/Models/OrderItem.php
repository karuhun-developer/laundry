<?php

namespace App\Models;

use App\Enums\PriceType;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $service_id
 * @property int|null $price_option_id
 * @property string $service_name_snapshot
 * @property string $service_code_snapshot
 * @property string $price_option_name_snapshot
 * @property PriceType $price_type_snapshot
 * @property int|null $package_weight_grams_snapshot
 * @property int|null $base_price_rupiah_snapshot
 * @property int|null $price_per_kilogram_rupiah_snapshot
 * @property int|null $minimum_chargeable_grams_snapshot
 * @property int $requested_weight_grams
 * @property int $chargeable_weight_grams
 * @property int $calculated_base_price_rupiah
 * @property int $excess_weight_grams
 * @property int $excess_price_rupiah
 * @property int $subtotal_rupiah
 */
#[Fillable([
    'service_id',
    'price_option_id',
    'service_name_snapshot',
    'service_code_snapshot',
    'price_option_name_snapshot',
    'price_type_snapshot',
    'package_weight_grams_snapshot',
    'base_price_rupiah_snapshot',
    'price_per_kilogram_rupiah_snapshot',
    'minimum_chargeable_grams_snapshot',
    'requested_weight_grams',
    'chargeable_weight_grams',
    'calculated_base_price_rupiah',
    'excess_weight_grams',
    'excess_price_rupiah',
    'subtotal_rupiah',
])]

class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<PriceOption, $this> */
    public function priceOption(): BelongsTo
    {
        return $this->belongsTo(PriceOption::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price_type_snapshot' => PriceType::class,
            'package_weight_grams_snapshot' => 'integer',
            'base_price_rupiah_snapshot' => 'integer',
            'price_per_kilogram_rupiah_snapshot' => 'integer',
            'minimum_chargeable_grams_snapshot' => 'integer',
            'requested_weight_grams' => 'integer',
            'chargeable_weight_grams' => 'integer',
            'calculated_base_price_rupiah' => 'integer',
            'excess_weight_grams' => 'integer',
            'excess_price_rupiah' => 'integer',
            'subtotal_rupiah' => 'integer',
        ];
    }
}
