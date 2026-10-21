<?php

declare(strict_types=1);

namespace AmjadIqbal\WebPushKit\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LogicException;
use NotificationChannels\WebPush\HasPushSubscriptions;
use NotificationChannels\WebPush\PushSubscription;

final class SubscriptionController
{
    /**
     * Save (or move to this user) the browser's subscription from PushSubscription.toJSON().
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->subscriptionRules());
        $user = $this->subscriber($request);

        $user->updatePushSubscription($data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth'], $data['content_encoding'] ?? 'aes128gcm');

        return response()->json(['subscribed' => true], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:'.$this->maxEndpointLength()]]);

        $this->subscriber($request)->deletePushSubscription($data['endpoint']);

        return response()->json(['subscribed' => false]);
    }

    /**
     * Called by the service worker on pushsubscriptionchange, where no CSRF token is
     * available: only same-origin requests are accepted, and the old endpoint is only
     * removed when it belongs to the signed-in user.
     */
    public function rotate(Request $request): JsonResponse
    {
        abort_unless($this->isSameOrigin($request), 403);

        $data = $request->validate([
            'old_endpoint' => ['nullable', 'string', 'max:'.$this->maxEndpointLength()],
            ...collect($this->subscriptionRules())->mapWithKeys(fn ($rules, $key) => ["subscription.{$key}" => $rules])->all(),
        ]);
        $user = $this->subscriber($request);
        $new = $data['subscription'];

        DB::transaction(function () use ($user, $data, $new): void {
            if (! empty($data['old_endpoint']) && $data['old_endpoint'] !== $new['endpoint']) {
                $user->deletePushSubscription($data['old_endpoint']);
            }

            $user->updatePushSubscription($new['endpoint'], $new['keys']['p256dh'], $new['keys']['auth'], $new['content_encoding'] ?? 'aes128gcm');
        });

        return response()->json(['subscribed' => true]);
    }

    /**
     * @return array<string, list<string>>
     */
    private function subscriptionRules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'url:https', 'max:'.$this->maxEndpointLength()],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'string', 'in:aes128gcm,aesgcm'],
        ];
    }

    private function maxEndpointLength(): int
    {
        return defined(PushSubscription::class.'::ENDPOINT_MAX_LENGTH') ? PushSubscription::ENDPOINT_MAX_LENGTH : 500;
    }

    /**
     * The signed-in user, which must use HasPushSubscriptions.
     */
    private function subscriber(Request $request): mixed
    {
        $user = $request->user();

        abort_if($user === null, 401);

        if (! in_array(HasPushSubscriptions::class, class_uses_recursive($user), true)) {
            throw new LogicException(sprintf('Add the %s trait to %s to save push subscriptions.', HasPushSubscriptions::class, $user::class));
        }

        return $user;
    }

    private function isSameOrigin(Request $request): bool
    {
        $site = $request->headers->get('Sec-Fetch-Site');

        if ($site !== null) {
            return $site === 'same-origin';
        }

        $origin = $request->headers->get('Origin');

        return $origin !== null && rtrim($origin, '/') === $request->getSchemeAndHttpHost();
    }
}
