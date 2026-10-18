<?php

declare(strict_types=1);

namespace App\Services\Qris;

use App\DTOs\Qris\QrisCheckResponse;
use App\DTOs\Qris\QrisConfigData;
use App\DTOs\Qris\QrisCreateResponse;
use App\Exceptions\QrisApiException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class QrisApiClient
{
    public function create(QrisConfigData $config, int $amount, string $idempotencyKey): QrisCreateResponse
    {
        try {
            $response = $this->request($config)
                ->withHeader('Idempotency-Key', $idempotencyKey)
                ->post('/v1/qris/create', [
                    'amount' => $amount,
                ]);
        } catch (ConnectionException $e) {
            throw new QrisApiException('Tidak dapat terhubung ke server QRIS: '.$e->getMessage(), previous: $e);
        }

        if ($response->status() === 402) {
            throw new QrisApiException('Saldo akun QRIS tidak mencukupi untuk membuat invoice baru.');
        }

        if (! $response->successful()) {
            $errorMsg = $response->json('error') ?? $response->json('message') ?? 'Gagal membuat QRIS (HTTP '.$response->status().')';
            throw new QrisApiException((string) $errorMsg);
        }

        $payload = $response->json('data') ?? $response->json();

        if (! is_array($payload) || empty($payload['qris_string'])) {
            throw new QrisApiException('Respon QRIS tidak valid: qris_string tidak ditemukan.');
        }

        $token = (string) ($payload['token'] ?? $payload['id'] ?? '');
        $totalAmount = (int) ($payload['amount'] ?? $amount);
        $uniqueCode = isset($payload['unique_code']) ? (int) $payload['unique_code'] : null;
        $qrisString = (string) $payload['qris_string'];
        $expiresAtRaw = $payload['expires_at'] ?? null;
        $expiresAt = $expiresAtRaw ? CarbonImmutable::parse((string) $expiresAtRaw) : CarbonImmutable::now()->addMinutes(15);
        $qrisUrl = isset($payload['qris_url']) ? (string) $payload['qris_url'] : null;

        return new QrisCreateResponse(
            token: $token,
            amount: $totalAmount,
            uniqueCode: $uniqueCode,
            qrisString: $qrisString,
            expiresAt: $expiresAt,
            qrisUrl: $qrisUrl,
        );
    }

    public function check(QrisConfigData $config, string $token): QrisCheckResponse
    {
        try {
            $response = $this->request($config)->post('/v1/payment/check', [
                'token' => $token,
                'wait' => false,
            ]);
        } catch (ConnectionException $e) {
            throw new QrisApiException('Tidak dapat memeriksa status pembayaran QRIS: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            $errorMsg = $response->json('error') ?? $response->json('message') ?? 'Gagal memeriksa status QRIS (HTTP '.$response->status().')';
            throw new QrisApiException((string) $errorMsg);
        }

        $json = $response->json() ?? [];
        $paid = (bool) ($json['paid'] ?? false);
        $statusStr = strtolower((string) ($json['status'] ?? ($paid ? 'paid' : 'pending')));

        $settledAt = null;
        if ($paid || in_array($statusStr, ['paid', 'success'], true)) {
            $paid = true;
            $statusStr = 'paid';
            $timeStr = $response->json('data.transaction.time')
                ?? $response->json('transaction.time')
                ?? $response->json('transaction_time');
            $settledAt = $timeStr ? CarbonImmutable::parse((string) $timeStr) : CarbonImmutable::now();
        } elseif ($statusStr === 'expired') {
            $paid = false;
            $statusStr = 'expired';
        } else {
            $paid = false;
            $statusStr = 'pending';
        }

        return new QrisCheckResponse(
            paid: $paid,
            status: $statusStr,
            settledAt: $settledAt,
            raw: is_array($json) ? $json : [],
        );
    }

    private function request(QrisConfigData $config): PendingRequest
    {
        return Http::baseUrl($config->baseUrl)
            ->withHeader('x-api-key', $config->apiKey)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(12)
            ->withoutRedirecting();
    }
}
