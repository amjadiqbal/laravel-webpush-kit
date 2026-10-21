<?php

namespace AmjadIqbal\WebPushKit\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    use HasPushSubscriptions;
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];
}
