<?php

namespace App\Models;

use App\Enums\OrderComplaintStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $category
 * @property string $description
 * @property OrderComplaintStatus $status
 * @property string|null $resolution_note
 * @property Carbon|null $resolved_at
 * @property-read Collection<int, OrderOperationEvent> $events
 */
#[Fillable(['category', 'description', 'status', 'created_by_id', 'resolution_note', 'resolved_at'])]
class OrderComplaint extends Model implements HasMedia
{
    use InteractsWithMedia;

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<OrderOperationEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(OrderOperationEvent::class)->oldest('id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('evidence')->useDisk('local')->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => OrderComplaintStatus::class, 'resolved_at' => 'datetime'];
    }
}
