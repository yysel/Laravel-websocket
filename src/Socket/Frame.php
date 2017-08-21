<?php

namespace Kitty\WebSocket\Socket;


class Frame
{
    public $type;
    public $key;
    public $socket;
    public $message;

    function __construct($type, $key, $socket, $message=null)
    {
        $this->type = $type;
        $this->key = $key;
        $this->socket = $socket;
        $this->message = $message;
    }
}