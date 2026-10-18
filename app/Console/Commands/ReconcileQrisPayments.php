<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payments\SettleQrisPaymentAction;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\QrisApiException;
use App\Models\Payment;
use App\Services\Qris\QrisApiClient;
use App\Services\Qris\QrisConfigurationResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class ReconcileQrisPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'qris:reconcile';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile pending dynamic QRIS payments against the qris.karuhundeveloper.com API';

    public function handle(
        QrisConfigurationResolver $qrisResolver,
        QrisApiClient $qrisApiClient,
        SettleQrisPaymentAction $settleAction,
    ): int {
        $pendingPayments = Payment::query()
            ->with(['order.branch.organization'])
            ->where('method', PaymentMethod::Qris)
            ->where('status', PaymentStatus::Pending)
            ->whereNotNull('qris_token')
            ->where('created_at', '>=', now()->subHours(24))
            ->get();

        if ($pendingPayments->isEmpty()) {
            $this->info('No pending QRIS payments to reconcile.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Reconciling %d pending QRIS payment(s)...', $pendingPayments->count()));
        $settledCount = 0;

        foreach ($pendingPayments as $payment) {
            $branch = $payment->order?->branch;
            if ($branch === null) {
                continue;
            }

            $config = $qrisResolver->resolve($branch);
            if ($config === null) {
                continue;
            }

            try {
                $check = $qrisApiClient->check($config, (string) $payment->qris_token);

                if ($check->paid) {
                    $settleAction->handle(
                        $payment,
                        $check->settledAt,
                        'Pembayaran QRIS otomatis diverifikasi oleh rekonsiliasi berkala.',
                    );
                    $settledCount++;
                    $this->info(sprintf('Payment #%s settled successfully.', $payment->payment_number ?? $payment->id));
                }
            } catch (QrisApiException $e) {
                $this->warn(sprintf('Error checking payment #%s: %s', $payment->id, $e->getMessage()));
                Log::warning('QRIS reconciliation check failed', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info(sprintf('Reconciliation completed. %d payment(s) settled.', $settledCount));

        return self::SUCCESS;
    }
}
