<?php

namespace App\Models;

use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $city_id
 * @property string $name
 * @property string $code
 * @property string|null $phone
 * @property string $address
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int $free_delivery_radius_meters
 * @property int $delivery_fee_per_km_rupiah
 * @property bool $qris_enabled
 * @property string|null $qris_static_payload
 * @property bool $is_active
 * @property-read City $city
 */
#[Fillable([
    'organization_id',
    'city_id',
    'name',
    'code',
    'phone',
    'address',
    'latitude',
    'longitude',
    'free_delivery_radius_meters',
    'delivery_fee_per_km_rupiah',
    'qris_enabled',
    'qris_static_payload',
    'is_active',
])]
class Branch extends Model implements HasMedia
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory, InteractsWithMedia, LogsActivity;

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /** @return HasMany<PriceOption, $this> */
    public function priceOptions(): HasMany
    {
        return $this->hasMany(PriceOption::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<MessageTemplate, $this> */
    public function messageTemplates(): HasMany
    {
        return $this->hasMany(MessageTemplate::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'free_delivery_radius_meters' => 'integer',
            'delivery_fee_per_km_rupiah' => 'integer',
            'qris_enabled' => 'boolean',
            'qris_static_payload' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }
}
