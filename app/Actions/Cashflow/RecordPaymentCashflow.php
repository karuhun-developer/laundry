<?php

namespace App\Actions\Cashflow;

use App\Enums\CashflowType;
use App\Models\CashflowCategory;
use App\Models\CashflowEntry;
use App\Models\Payment;

final readonly class RecordPaymentCashflow
{
    public function handle(Payment $payment): ?CashflowEntry
    {
        if (CashflowEntry::where('payment_id', $payment->id)->exists()) {
            return null;
        }

        $category = CashflowCategory::query()
            ->where('organization_id', $payment->organization_id)
            ->where('name', 'Pembayaran Pesanan')
            ->where('type', CashflowType::Debit)
            ->first();

        if ($category === null) {
            return null;
        }

        $payment->loadMissing('order');

        return CashflowEntry::create([
            'organization_id' => $payment->organization_id,
            'branch_id' => $payment->branch_id,
            'cashflow_category_id' => $category->id,
            'payment_id' => $payment->id,
            'created_by_id' => $payment->recorded_by_id,
            'type' => CashflowType::Debit,
            'amount_rupiah' => $payment->amount_rupiah,
            'description' => 'Pembayaran pesanan #'.($payment->order->order_number ?? $payment->order_id),
            'entry_date' => ($payment->settled_at ?? now())->toDateString(),
        ]);
    }
}
