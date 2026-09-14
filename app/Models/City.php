<?php

namespace App\Models;

use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Traits\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $name
 * @property string $province
 */
#[Fillable(['name', 'province'])]
class City extends Model
{
    /** @use HasFactory<CityFactory> */
    use HasFactory, LogsActivity;

    /** @return HasMany<Branch, $this> */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
