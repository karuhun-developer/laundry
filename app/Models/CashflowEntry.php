<?php

namespace App\Models;

use App\Enums\CashflowType;
use Database\Factories\CashflowEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $branch_id
 * @property int $cashflow_category_id
 * @property int|null $payment_id
 * @property int|null $created_by_id
 * @property CashflowType $type
 * @property int $amount_rupiah
 * @property string $description
 * @property Carbon $entry_date
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'organization_id',
    'branch_id',
    'cashflow_category_id',
    'payment_id',
    'created_by_id',
    'type',
    'amount_rupiah',
    'description',
    'entry_date',
])]
class CashflowEntry extends Model
{
    /** @use HasFactory<CashflowEntryFactory> */
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

    /** @return BelongsTo<CashflowCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CashflowCategory::class, 'cashflow_category_id');
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => CashflowType::class,
            'amount_rupiah' => 'integer',
            'entry_date' => 'date:Y-m-d',
        ];
    }
}
