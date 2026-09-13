<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderStatusEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $actor_id
 * @property string $actor_name_snapshot
 * @property OrderStatus $status
 * @property string|null $note
 * @property Carbon $created_at
 */
#[Fillable(['actor_id', 'actor_name_snapshot', 'status', 'note'])]

class OrderStatusEvent extends Model
{
    /** @use HasFactory<OrderStatusEventFactory> */
    use HasFactory;

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => OrderStatus::class];
    }
}
