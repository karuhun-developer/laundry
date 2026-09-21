<?php

namespace App\Models;

use App\Enums\CashflowGroup;
use App\Enums\CashflowType;
use Database\Factories\CashflowCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property CashflowType $type
 * @property CashflowGroup $group
 * @property bool $is_system
 * @property bool $is_active
 */
#[Fillable([
    'organization_id',
    'name',
    'type',
    'group',
    'is_system',
    'is_active',
])]
class CashflowCategory extends Model
{
    /** @use HasFactory<CashflowCategoryFactory> */
    use HasFactory;

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<CashflowEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(CashflowEntry::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => CashflowType::class,
            'group' => CashflowGroup::class,
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
