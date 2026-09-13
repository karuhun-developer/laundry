<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
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
 * @property int $order_id
 * @property int|null $recorded_by_id
 * @property string|null $payment_number
 * @property PaymentMethod $method
 * @property PaymentStatus $status
 * @property int $amount_rupiah
 * @property string|null $qris_payload
 * @property string|null $note
 * @property Carbon|null $settled_at
 * @property Carbon $created_at
 * @property-read Order $order
 * @property-read Branch $branch
 */
#[Fillable([
    'organization_id',
    'branch_id',
    'order_id',
    'recorded_by_id',
    'payment_number',
    'method',
    'status',
    'amount_rupiah',
    'qris_payload',
    'note',
    'settled_at',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
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

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    /** @return HasMany<PaymentStatusEvent, $this> */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(PaymentStatusEvent::class)->oldest('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount_rupiah' => 'integer',
            'settled_at' => 'datetime',
        ];
    }
}
