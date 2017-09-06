<?php

namespace Kitty\WebSocket\Job;

use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;
use Kitty\WebSocket\Socket\WebSocket;

class WebSocketJob extends Job implements JobContract
{
    protected $frame;
    protected $socket;


    public function __construct(WebSocket $socket, $frame)
    {
        $this->socket = $socket;
        $this->frame = $frame;
    }

    /**
     * Fire the job.
     *
     * @return void
     */

    public function fire()
    {
        switch ($this->frame->type) {
            case 'in':
                return $this->login();
                break;
            case 'out':
                return $this->logout();
                break;
            case 'msg':
                return $this->massage();
                break;
            case 'admin':
                return $this->manager();
                break;
        }
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function login()
    {
        $this->console('用户id ' . $this->frame->key . ' : 进入频道');
        $this->broadcast('用户id ' . $this->frame->key . ': 进入频道');
    }

    public function logout()
    {
        $this->console('用户id ' . $this->frame->key . ' : 退出频道');
        $this->broadcast('用户id ' . $this->frame->key . ': 退出频道');
    }

    public function massage()
    {
        $this->broadcast('用户id ' . $this->frame->key . ' 消息: ' . $this->frame->message);
        $this->console('用户id ' . $this->frame->key . ' 消息: ' . $this->frame->message);
    }

    public function manager()
    {

    }

    public function attempts()
    {

    }


    public function getRawBody()
    {

    }

    // 向所有频道广播
    public function broadcast($msg)
    {
        $this->socket->broadcast($msg);
    }

    //关闭一个连接
    public function close($socket)
    {
        $this->socket->close($socket);
    }

    //通过key获取一个连接的用户
    public function user($key)
    {
        $this->socket->user($key);
    }

    //通过socket标识，向某个连接用户发送一条消息
    public function send($scoket, $msg)
    {
        $this->socket->write($scoket, $msg);
    }

    //通过key，向某连接用户发送一条消息
    public function sendBykey($key, $msg)
    {
        $this->socket->idwrite($key, $msg);
    }

    public function console($msg)
    {
        $this->socket->console($msg);
    }

    public function registerTimer($time,$func)
    {
        $this->socket->registerTimer($time,$func);
    }
}
