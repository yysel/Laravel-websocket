<?php

namespace Kitty\WebSocket\Servers;

use Illuminate\Queue\Connectors\ConnectorInterface;
use Kitty\WebSocket\Socket\WebSocket;


class WebSocketConnector implements ConnectorInterface
{

    public function connect(array $config)
    {
        $web=new WebSocket($config);
        return new WebSocketQueue($web, $config['queue']);
    }


}
