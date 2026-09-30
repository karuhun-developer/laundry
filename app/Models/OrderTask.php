<?php

namespace App\Models;

use App\Enums\OrderTaskStatus;
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
 * @property string $kind
 * @property OrderTaskStatus $status
 * @property string|null $operator_name
 * @property Carbon|null $scheduled_at
 * @property string|null $address
 * @property string|null $recipient_name
 * @property string|null $note
 * @property Carbon|null $completed_at
 * @property-read Collection<int, OrderOperationEvent> $events
 */
#[Fillable(['kind', 'status', 'operator_name', 'scheduled_at', 'address', 'recipient_name', 'note', 'completed_at'])]
class OrderTask extends Model implements HasMedia
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
        return ['status' => OrderTaskStatus::class, 'scheduled_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
