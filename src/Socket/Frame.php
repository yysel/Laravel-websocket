<?php

namespace Kitty\WebSocket\Socket;


class Frame
{
    public $type;
    //public $key;
    //public $socket;
    public $client;
    public $message;

    function __construct($type, $client, $message = null)
    {
        $this->type = $type;
        $this->client = (object)$client;
        //$this->key = $key;
        //$this->socket = $socket;
        $this->message = $message;
    }
}