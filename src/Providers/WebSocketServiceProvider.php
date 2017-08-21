<?php

namespace Kitty\WebSocket\Providers;

use Illuminate\Support\ServiceProvider;
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
