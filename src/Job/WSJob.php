<?php

namespace Kitty\WebSocket\Job;

use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;
use Kitty\WebSocket\Socket\WebSocket;

class WSJob extends Job implements JobContract
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
        $frame = $this->frame;
        if ('in' == $frame->type) {
            $this->console('用户id '. $frame->key.' : 进入频道');
           $this->broadcast('用户id '. $frame->key.': 进入频道');
        } elseif ('out' == $frame->type) {
            $this->console('用户id '. $frame->key.' : 退出频道');
           $this->broadcast('用户id '. $frame->key.': 退出频道');
        } elseif ('msg' == $frame->type) {
            $this->broadcast('用户id '. $frame->key. ' 消息: ' . $frame->message);
            $this->console('用户id '. $frame->key. ' 消息: ' . $frame->message);
        }
    }



    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
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
    public function send($scoket,$msg)
    {
        $this->socket->write($scoket,$msg);
    }

    //通过key，向某连接用户发送一条消息
    public function sendBykey($key,$msg)
    {
        $this->socket->idwrite($key,$msg);
    }

    public function console($msg)
    {
        $this->socket->console($msg);
    }

}
