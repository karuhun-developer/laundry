<?php

declare(strict_types=1);

namespace App\Services\Qris;

use App\DTOs\Qris\QrisConfigData;
use App\Models\Branch;

final class QrisConfigurationResolver
{
    public function resolve(Branch $branch): ?QrisConfigData
    {
        if (! $branch->qris_enabled) {
            return null;
        }

        // 1. If branch has its own API key, use it
        if (! empty($branch->qris_api_key)) {
            $baseUrl = ! empty($branch->qris_base_url)
                ? $branch->qris_base_url
                : ($branch->organization->qris_base_url ?? 'https://qris.karuhundeveloper.com');

            return new QrisConfigData(
                apiKey: $branch->qris_api_key,
                baseUrl: rtrim($baseUrl, '/'),
            );
        }

        // 2. Fallback to organization settings
        $organization = $branch->relationLoaded('organization')
            ? $branch->organization
            : $branch->organization()->first();

        if ($organization && $organization->qris_enabled && ! empty($organization->qris_api_key)) {
            $baseUrl = ! empty($organization->qris_base_url)
                ? $organization->qris_base_url
                : 'https://qris.karuhundeveloper.com';

            return new QrisConfigData(
                apiKey: $organization->qris_api_key,
                baseUrl: rtrim($baseUrl, '/'),
            );
        }

        return null;
    }
}
