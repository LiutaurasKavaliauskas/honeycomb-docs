<?php

namespace interactivesolutions\honeycombdocs\app\providers;

use Illuminate\Support\ServiceProvider;
use interactivesolutions\honeycombdocs\app\console\commands\HCDocs;

class HCDocsServiceProvider extends ServiceProvider
{
    /**
     * Register commands
     *
     * @var array
     */
    protected $commands = [
        HCDocs::class,
    ];

    protected $namespace = 'interactivesolutions\honeycombdocs\app\http\controllers';

    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        // register artisan commands
        $this->commands($this->commands);

        // loading views
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'HCDocs');

        // loading translations
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'HCDocs');

        // registering elements to publish
        $this->registerPublishElements();

        // registering helpers
        $this->registerHelpers();

        // registering routes
        $this->registerRoutes();

        //register providers
        $this->registerProviders();
    }

    /**
     * Register helper function
     */
    private function registerHelpers()
    {
        $filePath = __DIR__ . '/../http/helpers.php';

        if (file_exists($filePath))
            require_once $filePath;
    }

    /**
     *  Registering all vendor items which needs to be published
     */
    private function registerPublishElements ()
    {
        $directory = __DIR__ . '/../../database/migrations/';

        // Publish your migrations
        if (file_exists ($directory))
            $this->publishes ([
                __DIR__ . '/../../database/migrations/' => database_path ('/migrations'),
            ], 'migrations');

        $directory = __DIR__ . '/../public';

        // Publishing assets
        if (file_exists ($directory))
            $this->publishes ([
                __DIR__ . '/../public' => public_path ('honeycomb'),
            ], 'public');
    }

    /**
     * Registering routes
     */
    private function registerRoutes()
    {
        $filePath = __DIR__ . '/../../app/honeycomb/routes.php';

        if ($filePath)
            \Route::group (['namespace' => $this->namespace], function ($router) use ($filePath) {
                require $filePath;
            });
    }

    /**
     * Registering 3rd party providers which are required for this package to run
     */
    private function registerProviders()
    {

    }
}


