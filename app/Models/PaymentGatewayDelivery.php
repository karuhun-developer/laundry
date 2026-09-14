<?php

namespace App\Models;

use App\Enums\PaymentGatewayDriver;
use Database\Factories\PaymentGatewayDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $payment_id
 * @property PaymentGatewayDriver $gateway_driver
 * @property string $delivery_id
 * @property string $event
 * @property array<string, mixed> $payload
 * @property Carbon $processed_at
 * @property Carbon $created_at
 */
#[Fillable([
    'payment_id',
    'gateway_driver',
    'delivery_id',
    'event',
    'payload',
    'processed_at',
])]
class PaymentGatewayDelivery extends Model
{
    /** @use HasFactory<PaymentGatewayDeliveryFactory> */
    use HasFactory;

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'gateway_driver' => PaymentGatewayDriver::class,
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
