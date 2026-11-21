<?php

namespace App\Services\Push;

use App\Jobs\SendAdminPushJob;
use App\Models\Push\AdminPushDispatch;
use App\Models\Users\User;
use App\Repositories\Push\AdminPushDispatchRepository;
use App\Repositories\Push\UserPushTokenRepository;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AdminPushService
{
    public const AUDIENCE_ALL_WITH_TOKEN = 'all_with_token';

    public const AUDIENCE_SELF_TEST = 'self_test';

    public function __construct(
        private UserPushTokenRepository $tokens,
        private AdminPushDispatchRepository $dispatches,
    ) {}

    /**
     * @return Collection<int, AdminPushDispatch>
     */
    public function history(): Collection
    {
        return $this->dispatches->latest();
    }

    /**
     * @return array{dispatch: AdminPushDispatch, recipientCount: int}
     */
    public function send(User $admin, string $title, string $body, bool $testOnly): array
    {
        $title = trim($title);
        $body = trim($body);
        if ($title === '' || $body === '') {
            throw ValidationException::withMessages([
                'title' => 'Podaj tytuł i treść.',
            ]);
        }

        if ($testOnly) {
            $tokenList = $this->tokens->getTokensForUser((int) $admin->id)->all();
            $audience = self::AUDIENCE_SELF_TEST;
        } else {
            $tokenList = $this->tokens->allTokens();
            $audience = self::AUDIENCE_ALL_WITH_TOKEN;
        }

        $dispatch = $this->dispatches->create(
            (int) $admin->id,
            $title,
            $body,
            $audience,
            count($tokenList),
            $testOnly,
        );

        if ($tokenList !== []) {
            SendAdminPushJob::dispatch($tokenList, $title, $body);
        }

        return [
            'dispatch' => $dispatch,
            'recipientCount' => count($tokenList),
        ];
    }
}
