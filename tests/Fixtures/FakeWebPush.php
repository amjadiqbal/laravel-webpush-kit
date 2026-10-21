<?php

namespace AmjadIqbal\WebPushKit\Tests\Fixtures;

use Generator;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\SubscriptionInterface;
use Minishlink\WebPush\WebPush;

/**
 * Answers each queued push with the status code mapped to its endpoint (201 by default).
 */
class FakeWebPush extends WebPush
{
    /** @var list<array{endpoint: string, payload: ?string}> */
    public array $sent = [];

    /** @param array<string, int> $statuses */
    public function __construct(public array $statuses = []) {}

    public function queueNotification(SubscriptionInterface $subscription, ?string $payload = null, array $options = [], array $auth = []): void
    {
        $this->sent[] = ['endpoint' => $subscription->getEndpoint(), 'payload' => $payload];
    }

    public function flush(?int $batchSize = null): Generator
    {
        foreach ($this->sent as $push) {
            $status = $this->statuses[$push['endpoint']] ?? 201;
            yield new MessageSentReport(new Request('POST', $push['endpoint']), new Response($status), $status < 300, $status < 300 ? 'OK' : 'Gone');
        }
    }
}
