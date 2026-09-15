<?php

namespace App\Services;

use App\Models\PushNotificationDevice;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class FirebaseCloudMessaging
{
    private const MessagingScope = 'https://www.googleapis.com/auth/firebase.messaging';

    public function isConfigured(): bool
    {
        $credentialsPath = config('services.firebase.credentials');

        return is_string(config('services.firebase.project_id'))
            && config('services.firebase.project_id') !== ''
            && is_string($credentialsPath)
            && $credentialsPath !== ''
            && is_file($credentialsPath);
    }

    /**
     * @param  array<string, string>  $data
     */
    public function send(
        PushNotificationDevice $device,
        string $title,
        string $body,
        array $data,
    ): bool {
        if (! $this->isConfigured()) {
            return true;
        }

        $projectId = (string) config('services.firebase.project_id');
        $response = Http::acceptJson()
            ->asJson()
            ->withToken($this->accessToken())
            ->connectTimeout(5)
            ->timeout(15)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $device->token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $data,
                    'android' => [
                        'notification' => [
                            'channel_id' => 'laundry_updates',
                            'sound' => 'default',
                        ],
                    ],
                ],
            ]);

        if ($this->isInvalidTokenResponse($response)) {
            return false;
        }

        $response->throw();

        return true;
    }

    private function accessToken(): string
    {
        return Cache::remember('firebase-cloud-messaging-access-token', now()->addMinutes(50), function (): string {
            $credentials = new ServiceAccountCredentials(
                self::MessagingScope,
                (string) config('services.firebase.credentials'),
            );
            $token = $credentials->fetchAuthToken()['access_token'] ?? null;

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('Firebase access token could not be created.');
            }

            return $token;
        });
    }

    private function isInvalidTokenResponse(Response $response): bool
    {
        return $response->status() === 404
            || $response->json('error.details.0.errorCode') === 'UNREGISTERED';
    }
}
