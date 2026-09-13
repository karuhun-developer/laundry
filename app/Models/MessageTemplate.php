<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\MessageTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $branch_id
 * @property string $scope_key
 * @property OrderStatus $order_status
 * @property string $body
 * @property bool $is_active
 * @property-read Organization $organization
 * @property-read Branch|null $branch
 */
#[Fillable([
    'organization_id',
    'branch_id',
    'scope_key',
    'order_status',
    'body',
    'is_active',
])]
class MessageTemplate extends Model
{
    /** @use HasFactory<MessageTemplateFactory> */
    use HasFactory, LogsActivity;

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

    public function deletionConfirmation(): string
    {
        return sprintf('%s:%s', $this->scope_key, $this->order_status->value);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'order_status' => OrderStatus::class,
            'is_active' => 'boolean',
        ];
    }
}
