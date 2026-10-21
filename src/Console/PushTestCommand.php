<?php

declare(strict_types=1);

namespace AmjadIqbal\WebPushKit\Console;

use AmjadIqbal\WebPushKit\Notifications\TestPush;
use AmjadIqbal\WebPushKit\WebPushKit;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Minishlink\WebPush\MessageSentReport;
use NotificationChannels\WebPush\Events\NotificationFailed;
use NotificationChannels\WebPush\Events\NotificationSent;
use NotificationChannels\WebPush\HasPushSubscriptions;
use NotificationChannels\WebPush\PushSubscription;
use NotificationChannels\WebPush\WebPushChannel;

final class PushTestCommand extends Command
{
    protected $signature = 'push:test
        {id? : ID of the user to send to (default: the owner of the newest subscription)}
        {--model= : The notifiable model class (default: the "users" auth provider model)}
        {--title=Test notification : The notification title}
        {--body=Web push is working. : The notification body}
        {--url=/ : The page a click opens}
        {--declarative : Send a declarative web push message (Safari 18.4+ shows it without the service worker)}';

    protected $description = 'Send a test web push to a user\'s browsers and show what each push service answered';

    public function handle(): int
    {
        if (! WebPushKit::vapidConfigured()) {
            $this->components->error('VAPID keys are missing. Run: php artisan webpush:vapid');

            return self::FAILURE;
        }

        foreach (WebPushKit::subjectProblems() as $problem) {
            $this->components->warn($problem);
        }

        $notifiable = $this->notifiable();

        if ($notifiable === null) {
            return self::FAILURE;
        }

        $count = $this->subscriptionModel()::query()->whereMorphedTo('subscribable', $notifiable)->count();

        if ($count === 0) {
            $this->components->error(class_basename($notifiable).' #'.$notifiable->getKey().' has no push subscriptions. Subscribe with the button first.');

            return self::FAILURE;
        }

        $this->components->info("Sending to {$count} subscription(s) of ".class_basename($notifiable).' #'.$notifiable->getKey().'.');

        // Every channel version reports each push through these events (only 13.x also returns them).
        $reports = [];
        $collect = function (NotificationSent|NotificationFailed $event) use (&$reports): void {
            $reports[] = $event->report;
        };
        Event::listen(NotificationSent::class, $collect);
        Event::listen(NotificationFailed::class, $collect);

        // Resolved only now: building the channel validates the VAPID keys.
        app(WebPushChannel::class)->send($notifiable, new TestPush(
            (string) $this->option('title'),
            (string) $this->option('body'),
            // The service worker resolves a path against its own origin, so APP_URL can't send the
            // click to the wrong host. Declarative messages need an absolute navigate URL.
            $this->option('declarative') ? url((string) $this->option('url')) : (string) $this->option('url'),
            (bool) $this->option('declarative'),
        ));

        $delivered = 0;
        $rows = [];

        foreach ($reports as $report) {
            $status = $report->getResponse()?->getStatusCode();
            $delivered += $report->isSuccess() ? 1 : 0;
            $rows[] = [
                (string) parse_url($report->getEndpoint(), PHP_URL_HOST),
                $status ?? '-',
                match (true) {
                    $report->isSuccess() => 'delivered',
                    $report->isSubscriptionExpired() => 'expired: subscription removed',
                    default => 'failed: '.$this->reason($report),
                },
            ];
        }

        $this->table(['Push service', 'Status', 'Result'], $rows);

        if ($delivered === 0) {
            $this->components->error('No push was delivered.');

            return self::FAILURE;
        }

        $this->components->info("Delivered to {$delivered} of ".count($rows).' subscription(s).');

        return self::SUCCESS;
    }

    private function notifiable(): ?Model
    {
        /** @var class-string<Model>|null $class */
        $class = $this->option('model') ?: config('webpush-kit.notifiable') ?: config('auth.providers.users.model');

        if (! is_string($class) || ! class_exists($class)) {
            $this->components->error('No notifiable model found. Pass --model=App\\Models\\User.');

            return null;
        }

        if (! in_array(HasPushSubscriptions::class, class_uses_recursive($class), true)) {
            $this->components->error("{$class} must use ".HasPushSubscriptions::class.'.');

            return null;
        }

        $id = $this->argument('id');

        if ($id === null) {
            $id = $this->subscriptionModel()::query()
                ->where('subscribable_type', (new $class)->getMorphClass())
                ->latest('id')
                ->value('subscribable_id');

            if ($id === null) {
                $this->components->error('No push subscriptions yet. Subscribe with the button first.');

                return null;
            }
        }

        $notifiable = $class::query()->find($id);

        if ($notifiable === null) {
            $this->components->error(class_basename($class)." #{$id} not found.");
        }

        return $notifiable;
    }

    /**
     * @return class-string<PushSubscription>
     */
    private function subscriptionModel(): string
    {
        /** @var class-string<PushSubscription> $model */
        $model = config('webpush.model', PushSubscription::class);

        return $model;
    }

    private function reason(MessageSentReport $report): string
    {
        $body = trim((string) $report->getResponseContent());

        return mb_strimwidth($body !== '' ? $body : $report->getReason(), 0, 120, '…');
    }
}
