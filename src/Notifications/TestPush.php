<?php

declare(strict_types=1);

namespace AmjadIqbal\WebPushKit\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\DeclarativeWebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushMessageInterface;

/**
 * The notification push:test sends. A normal message carries its click target in
 * data.url (the kit's service worker opens it); a declarative one uses navigate.
 */
final class TestPush extends Notification
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $url,
        public readonly bool $declarative = false,
    ) {}

    /**
     * @return list<class-string>
     */
    public function via(mixed $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(mixed $notifiable, mixed $notification = null): WebPushMessageInterface
    {
        if ($this->declarative) {
            return (new DeclarativeWebPushMessage)
                ->title($this->title)
                ->body($this->body)
                ->navigate($this->url)
                ->tag('webpush-kit-test');
        }

        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->tag('webpush-kit-test')
            ->data(['url' => $this->url]);
    }
}
