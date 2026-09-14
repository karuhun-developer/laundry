<?php

namespace App\Models;

use App\Enums\PriceType;
use Database\Factories\PriceOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $service_id
 * @property int $branch_id
 * @property PriceType $type
 * @property string $name
 * @property int|null $package_weight_grams
 * @property int|null $base_price_rupiah
 * @property int|null $price_per_kilogram_rupiah
 * @property int|null $minimum_chargeable_grams
 * @property bool $is_active
 * @property-read Service $service
 */
#[Fillable([
    'service_id',
    'branch_id',
    'type',
    'name',
    'package_weight_grams',
    'base_price_rupiah',
    'price_per_kilogram_rupiah',
    'minimum_chargeable_grams',
    'is_active',
])]
class PriceOption extends Model
{
    /** @use HasFactory<PriceOptionFactory> */
    use HasFactory, LogsActivity;

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => PriceType::class,
            'package_weight_grams' => 'integer',
            'base_price_rupiah' => 'integer',
            'price_per_kilogram_rupiah' => 'integer',
            'minimum_chargeable_grams' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
