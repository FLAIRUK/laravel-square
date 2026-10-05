<?php

namespace FLAIRUK\Square;

use FLAIRUK\Square\Http\Controllers\WebhookController;
use FLAIRUK\Square\Http\Middleware\VerifyWebhookSignature;
use FLAIRUK\Square\Webhooks\WebhookSignature;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Square\SquareClient;

class SquareServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/square.php', 'square');

        $this->app->singleton(ClientFactory::class, fn (Application $app) => new ClientFactory(
            $app->make(Http::class),
            $app['config']->get('square'),
        ));

        $this->app->singleton(Square::class, fn (Application $app) => new Square(
            $app->make(ClientFactory::class),
            $app['config']->get('square'),
        ));

        $this->app->alias(Square::class, 'square');

        $this->app->singleton(SquareClient::class, fn (Application $app) => $app->make(Square::class)->client());

        $this->app->bind(OAuth::class, fn (Application $app) => $app->make(Square::class)->oauth());

        $this->app->bind(WebhookSignature::class, fn (Application $app) => $app->make(Square::class)->webhookSignature());
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'square');

        Blade::componentNamespace('FLAIRUK\\Square\\View\\Components', 'square');

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('square.webhook', VerifyWebhookSignature::class);

        if (filled($path = $this->app['config']->get('square.webhooks.path')) && ! $this->app->routesAreCached()) {
            $router->post($path, WebhookController::class)
                ->middleware(VerifyWebhookSignature::class)
                ->name('square.webhook');
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/square.php' => config_path('square.php'),
        ], 'square-config');

        $this->commands([
            Console\InstallCommand::class,
            Console\StatusCommand::class,
        ]);
    }
}
