<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentStatusEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payment_id
 * @property int|null $actor_id
 * @property string $actor_name_snapshot
 * @property PaymentStatus $status
 * @property string|null $note
 * @property Carbon $created_at
 */
#[Fillable(['actor_id', 'actor_name_snapshot', 'status', 'note'])]
class PaymentStatusEvent extends Model
{
    /** @use HasFactory<PaymentStatusEventFactory> */
    use HasFactory;

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => PaymentStatus::class];
    }
}
