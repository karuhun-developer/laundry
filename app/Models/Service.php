<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Traits\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property bool $is_active
 */
#[Fillable(['organization_id', 'name', 'code', 'description', 'is_active'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, LogsActivity;

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<PriceOption, $this> */
    public function priceOptions(): HasMany
    {
        return $this->hasMany(PriceOption::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
