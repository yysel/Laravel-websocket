<?php

namespace Kitty\WebSocket\Providers;

use Illuminate\Support\ServiceProvider;
use Kitty\WebSocket\Helper\MakeJobCommand;
use Kitty\WebSocket\Helper\ManagerCommand;
use Kitty\WebSocket\Helper\RunCommand;
use Kitty\WebSocket\Servers\WebSocketConnector;

class WebSocketServiceProvider extends ServiceProvider
{

    protected $defer = false;


    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $manager = $this->app['queue'];
        $this->registerStompConnector($manager);
    }


    public function provides()
    {
        return [];
    }


    public function register()
    {
        $this->app->singleton('command.websocket.manager', function () {
            return new ManagerCommand();
        });
        $this->app->singleton('command.websocket.run', function () {
            return new RunCommand();
        });
        $this->app->singleton('command.websocket.make', function () {
            return new MakeJobCommand();
        });
        $this->commands('command.websocket.manager');
        $this->commands('command.websocket.run');
        $this->commands('command.websocket.make');
    }

    /**
     * Register the Stomp queue connector.
     *
     * @param \Illuminate\Queue\QueueManager $manager
     *
     * @return void
     */
    protected function registerStompConnector($manager)
    {
        $manager->addConnector('websocket', function () {
            return new WebSocketConnector();
        });
    }

}
