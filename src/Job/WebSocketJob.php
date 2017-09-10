<?php

namespace Kitty\WebSocket\Job;

use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;
use Kitty\WebSocket\Socket\WebSocket;

class WebSocketJob extends Job implements JobContract
{
    protected $frame;
    protected $socket;
    protected $user;

    public function __construct(WebSocket $socket, $frame)
    {
        $this->socket = $socket;
        $this->frame = $frame;
        $this->user = $frame->user;
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
        $this->console('用户id ' . $this->user->key . ' : 进入频道');
        $this->broadcast('用户id ' . $this->user->key . ': 进入频道');
    }

    public function logout()
    {
        $this->console('用户id ' . $this->user->key . ' : 退出频道');
        $this->broadcast('用户id ' . $this->user->key . ': 退出频道');
    }

    public function massage()
    {
        $this->broadcast('用户id ' . $this->user->key . ' 消息: ' . $this->frame->message);
        $this->console('用户id ' . $this->user->key . ' 消息: ' . $this->frame->message);
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
    public function close()
    {
        $this->socket->close();
    }

    public function send($msg)
    {
        $this->socket->send($msg);
    }

    //通过key获取一个连接的用户
    public function user($key)
    {
        $this->socket->user($key);
    }



    //通过socket标识，向某个连接用户发送一条消息
    public function sendBySocket($scoket, $msg)
    {
        $this->socket->sendBySocket($scoket, $msg);
    }

    //通过key，向某连接用户发送一条消息
    public function sendById($key, $msg)
    {
        $this->socket->sendById($key, $msg);
    }

    public function console($msg)
    {
        $this->socket->console($msg);
    }

    public function registerTimer($time, $func)
    {
        $this->socket->registerTimer($time, $func);
    }

    public function getAllUsers()
    {
        $this->socket->getAllUsers();
    }

    public function getCurrentUsers()
    {
        $this->socket->getCurrentUsers();
    }

    public function addAttributeToUser($id, Array $attr)
    {
        $this->socket->addAttributeToUser($id,$attr);
    }

    public function where($key,$value1,$value2)
    {
        $this->socket->where($key,$value1,$value2);
    }
}
