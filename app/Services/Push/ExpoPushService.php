<?php

namespace App\Services\Push;

use App\Repositories\Push\UserPushTokenRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushService
{
    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    private const BATCH_SIZE = 100;

    public function __construct(
        private UserPushTokenRepository $tokenRepository,
    ) {
    }

    /**
     * @param  list<string>  $tokens
     * @param  array{title: string, body: string, data: array<string, mixed>}  $message
     */
    public function sendToTokens(array $tokens, array $message): void
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        if ($tokens === []) {
            return;
        }

        foreach (array_chunk($tokens, self::BATCH_SIZE) as $chunk) {
            $this->sendChunk($chunk, $message);
        }
    }

    /**
     * @param  list<string>  $tokens
     * @param  array{title: string, body: string, data: array<string, mixed>}  $message
     */
    private function sendChunk(array $tokens, array $message): void
    {
        $payload = array_map(
            fn (string $token) => [
                'to' => $token,
                'title' => $message['title'],
                'body' => $message['body'],
                'data' => $message['data'],
                'sound' => 'default',
                'channelId' => 'invitations',
            ],
            $tokens,
        );

        $request = Http::acceptJson()
            ->asJson()
            ->timeout(15);

        $accessToken = config('services.expo.access_token');
        if (is_string($accessToken) && $accessToken !== '') {
            $request = $request->withToken($accessToken);
        }

        try {
            $response = $request->post(self::EXPO_PUSH_URL, $payload);
        } catch (\Throwable $e) {
            Log::warning('Expo push request failed', [
                'error' => $e->getMessage(),
                'token_count' => count($tokens),
            ]);

            return;
        }

        if (! $response->successful()) {
            Log::warning('Expo push HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return;
        }

        $this->cleanupInvalidTokens($tokens, $response->json('data') ?? []);
    }

    /**
     * @param  list<string>  $tokens
     * @param  list<array<string, mixed>>  $tickets
     */
    private function cleanupInvalidTokens(array $tokens, array $tickets): void
    {
        $invalid = [];

        foreach ($tickets as $index => $ticket) {
            if (($ticket['status'] ?? null) !== 'error') {
                continue;
            }

            $error = $ticket['details']['error'] ?? null;
            if ($error !== 'DeviceNotRegistered') {
                continue;
            }

            $token = $tokens[$index] ?? null;
            if (is_string($token) && $token !== '') {
                $invalid[] = $token;
            }
        }

        if ($invalid !== []) {
            $this->tokenRepository->deleteByTokens($invalid);
        }
    }
}
