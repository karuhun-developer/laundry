<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Data\Payments\CreateGatewayPaymentData;
use App\Data\Payments\GatewayPaymentData;
use App\Data\Payments\GatewayPaymentMethodData;
use App\Data\Payments\GatewayPaymentStatusData;
use App\Enums\PaymentGatewayDriver;
use App\Exceptions\PaymentGatewayException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

final class PaywuzPaymentGateway implements PaymentGateway
{
    public function driver(): PaymentGatewayDriver
    {
        return PaymentGatewayDriver::Paywuz;
    }

    public function isAvailable(): bool
    {
        return $this->enabled()
            && $this->apiKey() !== null
            && $this->baseUrl() !== null;
    }

    /** @return list<GatewayPaymentMethodData> */
    public function methods(): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        if ($this->methodCacheSeconds() === 0) {
            return $this->fetchMethods();
        }

        $cached = Cache::remember(
            'payment-gateways.paywuz.methods',
            $this->methodCacheSeconds(),
            fn (): array => $this->fetchMethods(),
        );

        return $cached;
    }

    public function createPayment(CreateGatewayPaymentData $data): GatewayPaymentData
    {
        $this->ensureAvailable();

        try {
            $response = $this->request()->post('transactions', [
                'orderId' => $data->orderId,
                'amount' => $data->amountRupiah,
                'paymentMethod' => $data->paymentMethod,
                'metadata' => $data->metadata,
            ])->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw PaymentGatewayException::providerFailure($this->driver()->label(), $exception);
        }

        return $this->paymentFromPayload($this->responseData($response->json()));
    }

    public function getPaymentStatus(string $gatewayOrderId): GatewayPaymentStatusData
    {
        $this->ensureAvailable();

        try {
            $response = $this->request()
                ->get('transactions/'.rawurlencode($gatewayOrderId))
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw PaymentGatewayException::providerFailure($this->driver()->label(), $exception);
        }

        $payload = $this->responseData($response->json());

        return new GatewayPaymentStatusData(
            id: $this->requiredString($payload, 'id'),
            orderId: $this->requiredString($payload, 'orderId'),
            status: $this->requiredString($payload, 'status'),
            paidAt: $this->optionalDate($payload, 'paidAt'),
            payload: $payload,
        );
    }

    public function verifyWebhook(string $rawBody, string $signature): bool
    {
        $apiKey = $this->apiKey();

        if (! $this->enabled() || $apiKey === null || $signature === '') {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $apiKey);

        return hash_equals($expected, $signature);
    }

    /** @return list<GatewayPaymentMethodData> */
    private function fetchMethods(): array
    {
        try {
            $response = $this->request()->get('payment-methods')->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw PaymentGatewayException::providerFailure($this->driver()->label(), $exception);
        }

        $payload = $response->json('data');

        if (! is_array($payload)) {
            throw PaymentGatewayException::invalidProviderResponse($this->driver()->label());
        }

        $methods = [];

        foreach ($payload as $entry) {
            if (! is_array($entry)) {
                throw PaymentGatewayException::invalidProviderResponse($this->driver()->label());
            }

            $code = strtoupper($this->requiredString($entry, 'code'));
            $type = $this->requiredString($entry, 'type');

            if ($type === 'meta' || in_array($code, ['VA', 'ALL'], true)) {
                continue;
            }

            $fee = $this->requiredArray($entry, 'fee');
            $limits = $this->requiredArray($entry, 'limits');

            $methods[] = new GatewayPaymentMethodData(
                driver: $this->driver(),
                code: $code,
                name: $this->requiredString($entry, 'name'),
                type: $type,
                feeRupiah: $this->requiredInt($fee, 'totalIdr'),
                minimumRupiah: $this->requiredInt($limits, 'minIdr'),
                maximumRupiah: $this->requiredInt($limits, 'maxIdr'),
                isDefault: $code === 'QRIS',
            );
        }

        return $methods;
    }

    /** @param array<string, mixed> $payload */
    private function paymentFromPayload(array $payload): GatewayPaymentData
    {
        return new GatewayPaymentData(
            id: $this->requiredString($payload, 'id'),
            orderId: $this->requiredString($payload, 'orderId'),
            amountRupiah: $this->requiredInt($payload, 'amount'),
            totalPaymentRupiah: $this->requiredInt($payload, 'totalPayment'),
            paymentMethod: strtoupper($this->requiredString($payload, 'paymentMethod')),
            paymentNumber: $this->requiredString($payload, 'paymentNumber'),
            paymentUrl: $this->optionalString($payload, 'paymentUrl'),
            status: $this->requiredString($payload, 'status'),
            expiresAt: $this->optionalDate($payload, 'expiresAt'),
            payload: $payload,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function responseData(mixed $response): array
    {
        if (! is_array($response) || ! isset($response['data']) || ! is_array($response['data'])) {
            throw PaymentGatewayException::invalidProviderResponse($this->driver()->label());
        }

        return $response['data'];
    }

    private function request(): PendingRequest
    {
        $apiKey = $this->apiKey();
        $baseUrl = $this->baseUrl();

        if ($apiKey === null || $baseUrl === null) {
            throw PaymentGatewayException::notConfigured($this->driver()->label());
        }

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->connectTimeout($this->configInt('connect_timeout_seconds', 5))
            ->timeout($this->configInt('timeout_seconds', 15));
    }

    private function ensureAvailable(): void
    {
        if (! $this->isAvailable()) {
            throw PaymentGatewayException::notConfigured($this->driver()->label());
        }
    }

    private function enabled(): bool
    {
        return config('payment-gateways.drivers.paywuz.enabled') === true;
    }

    private function apiKey(): ?string
    {
        return $this->configString('api_key');
    }

    private function baseUrl(): ?string
    {
        $baseUrl = $this->configString('base_url');

        return $baseUrl === null ? null : rtrim($baseUrl, '/');
    }

    private function methodCacheSeconds(): int
    {
        return max(0, $this->configInt('method_cache_seconds', 300));
    }

    private function configString(string $key): ?string
    {
        $value = config("payment-gateways.drivers.paywuz.{$key}");

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function configInt(string $key, int $default): int
    {
        $value = config("payment-gateways.drivers.paywuz.{$key}", $default);

        return is_int($value) ? $value : $default;
    }

    /** @param array<string, mixed> $payload */
    private function requiredString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw PaymentGatewayException::invalidProviderResponse($this->driver()->label());
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private function optionalString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw PaymentGatewayException::invalidProviderResponse($this->driver()->label());
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private function requiredInt(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;

        if (! is_int($value)) {
            throw PaymentGatewayException::invalidProviderResponse($this->driver()->label());
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function requiredArray(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;

        if (! is_array($value)) {
            throw PaymentGatewayException::invalidProviderResponse($this->driver()->label());
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private function optionalDate(array $payload, string $key): ?CarbonImmutable
    {
        $value = $payload[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw PaymentGatewayException::invalidProviderResponse($this->driver()->label());
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            throw PaymentGatewayException::invalidProviderResponse($this->driver()->label());
        }
    }
}
