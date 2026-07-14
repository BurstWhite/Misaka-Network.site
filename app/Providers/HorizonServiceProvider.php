<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Console\WorkCommand;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;
use Symfony\Component\Console\Input\InputOption;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WorkCommand::class, function ($app): WorkCommand {
            $command = new WorkCommand($app['queue.worker'], $app['cache.store']);
            if (!$command->getDefinition()->hasOption('stop-when-empty-for')) {
                $command->getDefinition()->addOption(new InputOption(
                    'stop-when-empty-for',
                    null,
                    InputOption::VALUE_REQUIRED,
                    'Stop when no jobs have been processed for the given number of seconds',
                    0,
                ));
            }

            return $command;
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');

        // Horizon::night();
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     *
     * @return void
     */
    protected function gate()
    {
        Gate::define('viewHorizon', function ($user) {
            return in_array($user->email, [
                //
            ]);
        });
    }
}
