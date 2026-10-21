<?php

namespace AmjadIqbal\WebPushKit\Tests;

use AmjadIqbal\WebPushKit\Tests\Fixtures\User;
use AmjadIqbal\WebPushKit\WebPushKitServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NotificationChannels\WebPush\WebPushServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [WebPushServiceProvider::class, WebPushKitServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('app.url', 'https://example.com');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('webpush.database_connection', 'testing');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('webpush.vapid.public_key', 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM');
        $app['config']->set('webpush.vapid.private_key', 'test-private-key');
        $app['config']->set('webpush.vapid.subject', 'mailto:push@example.com');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password')->default('');
            $table->timestamps();
        });

        $stub = __DIR__.'/../vendor/laravel-notification-channels/webpush/migrations/create_push_subscriptions_table.php.stub';
        (require $stub)->up();
    }

    protected function user(string $name = 'Ada'): User
    {
        return User::query()->create(['name' => $name, 'email' => strtolower($name).'@example.com']);
    }
}
