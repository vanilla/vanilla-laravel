<?php
namespace Vanilla\Laravel\Providers;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Vanilla\Laravel\Commands\ValidateConfigCommand;
use Vanilla\Laravel\Http\RequestContextLogMiddleware;
use Vanilla\Laravel\Logging\VanillaLogFormatter;

/**
 * Service provider wiring up the logging middleware and configuration.
 */
class VanillaServiceProvider extends ServiceProvider
{
    /**
     * Configure DI.
     */
    public function register()
    {
        $this->app->singleton(VanillaLogFormatter::class, function () {
            $formatter = new VanillaLogFormatter();
            $formatter->setApplicationBasePath(base_path());
            return $formatter;
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        ///
        ///  Prepare logging.
        ///

        // Add logging middleware everywhere.
        /** @var Router $router */
        $router = $this->app->get(Router::class);
        foreach ($router->getMiddlewareGroups() as $group => $_) {
            $router->pushMiddlewareToGroup($group, RequestContextLogMiddleware::class);
        }

        ///
        /// Command Validation
        ///

        // Register our cache command
        Artisan::registerCommand(new ValidateConfigCommand());
        Event::listen(function (CommandFinished $event) {
            if ($event->command === "config:cache") {
                // Validate the config first.
                $result = Artisan::call("config:validate", [], $event->output);
                if ($result !== 0) {
                    die($result);
                }
            }
        });
    }
}
