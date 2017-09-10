<?php

namespace Kitty\WebSocket\Socket;


class Frame
{
    public $type;
    //public $key;
    //public $socket;
    public $user;
    public $message;

    function __construct($type, $user, $message = null)
    {
        $this->type = $type;
        $this->user = (object)$user;
        //$this->key = $key;
        //$this->socket = $socket;
        $this->message = $message;
    }
}