<?php
/**
 * Created by PhpStorm.
 * User: jim
 * Date: 2017-08-23
 * Time: 0:11
 */

namespace Kitty\WebSocket\Socket;


class SocketManager
{
    protected $key;
    protected $address;
    protected $port;
    protected $socket;

    function __construct()
    {
        $this->key = env('APP_KEY', 'base64:4buUjgZDzAwk7y6vJPV6FLpihNOuqDJLocKdRRDHS38=');
        $this->address = config('queue.connections.websocket.address', 'localhost');
        $this->port = config('queue.connections.websocket.port', 2000);
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    }

    public function connect()
    {
        socket_connect($this->socket, $this->address, $this->port);
        return $this->login();
    }

    public function getBuffer()
    {
        return "Order-Master-Key_Handing-|{$this->key}";
    }

    protected function login()
    {
        $buffer = $this->getBuffer();
        $this->send($buffer);
        return $this->handshake();
    }

    public function send($buffer)
    {
        return socket_write($this->socket, $buffer, strlen($buffer));
    }

    protected function handshake()
    {

        while (true) {
            socket_recv($this->socket, $buffer, 2048, 0);
            if($buffer=='OK') break;
        }
        return true;
    }

    public function read()
    {
        while (true) {
            if(socket_last_error($this->socket))return null;
            socket_recv($this->socket, $buffer, 2048, 0);
            if($buffer)return $buffer;
        }
    }

}