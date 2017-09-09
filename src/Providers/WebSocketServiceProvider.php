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
    //protected $package_path = 'vendor/kitty/websocket';
    protected $package_path = 'package';


    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        require(base_path($this->package_path . '/src/Helper/functions.php'));
        $manager = $this->app['queue'];
        $this->registerWebSocketConnector($manager);
        $this->registerRoute();
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
        $web=<<<ddd
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test WebSocket</title>
    <script type="text/javascript">
        //显示信息  
        var log = function(s) {
            if (document.readyState !== "complete") {
                log.buffer.push(s);
            } else {
                document.getElementById("output").textContent += (s + "\n");
                document.getElementById("outputdiv").scrollTop = document.getElementById("outputdiv").scrollHeight;
            }
        }
        log.buffer = [];
        //显示连接状态  
        function setConnected(status) {
            document.getElementById("socketstatus").innerHTML = status;
        }
        var ws = null;

        //连接  
        function connect() {
            if (ws != null) {
                log("现已连接");
                return ;
            }
            url = "ws://192.168.1.116:2000";
            if ('WebSocket' in window) {
                ws = new WebSocket(url);
            } else if ('MozWebSocket' in window) {
                ws = new MozWebSocket(url);
            } else {
                alert("您的浏览器不支持WebSocket。");
                return ;
            }
            ws.onopen = function() {
                log("open");
                setConnected("已连接");
                //设置发信息送类型为：ArrayBuffer
                ws.binaryType = "arraybuffer";

            }
            ws.onmessage = function(e) {
                log(e.data.toString());
            }
            ws.onclose = function(e) {
                ws=null;
                log("closed");
            }
            ws.onerror = function(e) {
                log("error");
            }
        }

        //断开连接  
        function disconnect() {
            if (ws != null) {
                ws.close();
                ws = null;
                setConnected("已断开");
            }
        }

        window.onload = function() {
            connect();
            log(log.buffer.join("\n"));
            //发送页面上输入框的信息  
            document.getElementById("sendButton").onclick = function() {
                alert('111');
                if (ws != null) {
                    ws.send(document.getElementById("inputMessage").value);
                }
            }
        }
    </script>
</head>
<body onunload="disconnect();">
<div>连接状态：<span id="socketstatus"></span></div>
<div>
    <input type="text" id="inputMessage" value="Hello, WebSocket!">
    <button id="sendButton">发送</button>
</div>
<div>
    <button id="connect" onclick="connect();">连接</button>
    <button id="disconnect" onclick="disconnect();">断开</button>
</div>
<div style="height:300px; overflow:auto;" id="outputdiv">
    <pre id="output"></pre>
</div>
</body>
</html>
ddd;

        Route::get('kitty/websocket/demo', function ()use($web) {
            return $web;
        });
    }

}
