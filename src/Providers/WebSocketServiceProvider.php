<?php

namespace Kitty\WebSocket\Providers;

use Illuminate\Support\Facades\Route;
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
        require(__DIR__ . '/../Helper/functions.php');
        $manager = $this->app['queue'];
        $this->registerWebSocketConnector($manager);
        $this->registerRoute();
        $config=array_merge(config('websocket',[]),[
            'driver' => 'websocket',
            'queue' => 'default',
            'connection' => 'default',
        ]);
        config(['queue.connections.websocket'=>$config]);
        $this->publishes([
            __DIR__ . '/../Config/websocket.php' => config_path('websocket.php'),
        ]);
        $this->publishes([
            __DIR__ . '/../Views/demo.blade.php' => resource_path('views/websocket/demo.blade.php'),
        ]);
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
    protected function registerWebSocketConnector($manager)
    {
        $manager->addConnector('websocket', function () {
            return new WebSocketConnector();
        });
    }

    protected function registerRoute()
    {
        Route::get('kitty/websocket/demo', function ()  {
            return view('websocket.demo');
        });
    }

}
