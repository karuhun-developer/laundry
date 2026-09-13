<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $branch_id
 * @property int|null $customer_id
 * @property int|null $created_by_id
 * @property string|null $order_number
 * @property OrderStatus $status
 * @property string $customer_name_snapshot
 * @property float|null $customer_latitude
 * @property float|null $customer_longitude
 * @property int|null $delivery_distance_meters
 * @property int|null $delivery_free_radius_meters_snapshot
 * @property int|null $delivery_fee_per_km_rupiah_snapshot
 * @property int $items_subtotal_rupiah
 * @property int $delivery_fee_rupiah
 * @property int $total_rupiah
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Branch $branch
 */
#[Fillable([
    'organization_id',
    'branch_id',
    'customer_id',
    'created_by_id',
    'order_number',
    'status',
    'customer_name_snapshot',
    'customer_latitude',
    'customer_longitude',
    'delivery_distance_meters',
    'delivery_free_radius_meters_snapshot',
    'delivery_fee_per_km_rupiah_snapshot',
    'items_subtotal_rupiah',
    'delivery_fee_rupiah',
    'total_rupiah',
])]

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<OrderStatusEvent, $this> */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(OrderStatusEvent::class)->oldest('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'customer_latitude' => 'float',
            'customer_longitude' => 'float',
            'delivery_distance_meters' => 'integer',
            'delivery_free_radius_meters_snapshot' => 'integer',
            'delivery_fee_per_km_rupiah_snapshot' => 'integer',
            'items_subtotal_rupiah' => 'integer',
            'delivery_fee_rupiah' => 'integer',
            'total_rupiah' => 'integer',
        ];
    }
}
