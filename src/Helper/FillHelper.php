<?php


namespace Kitty\WebSocket\Helper;


class  FillHelper
{
    const WebSocketJob = <<<CON
<?php

namespace App\Jobs;

use Kitty\WebSocket\Job\WebSocketJob as Job;

class WebSocketJob extends Job
{

    /**
     *响应用户进入事件
     */
    public function login()
    {
        \$this->console('用户id ' . \$this->client->id . ' : 进入频道');
        \$this->broadcast('用户id ' . \$this->client->id . ': 进入频道');
    }

    /**
     *响应用户断开事件
     */
    public function logout()
    {
        \$this->console('用户id ' . \$this->client->id . ' : 退出频道');
        \$this->broadcast('用户id ' . \$this->client->id . ': 退出频道');
    }

    /**
     *响应用户消息事件
     */
    public function massage()
    {
        \$this->broadcast('用户id ' . \$this->client->id . ' 消息: ' . \$this->frame->message);
        \$this->console('用户id ' . \$this->client->id . ' 消息: ' . \$this->frame->message);
    }

    /**
     *响应管理进程事件
     */
    public function manager()
    {

    }
    
    /**
     *响应定时任务事件
     */
    public function timer()
    {

    }

}

CON;

    public function makeJob()
    {
        $path_dir=base_path('app/Jobs');
        if(!is_dir($path_dir)) mkdir($path_dir,0777);
        $path = base_path('app/Jobs/WebSocketJob.php');
        $file = fopen($path, $mode = 'w');
        return fwrite($file, self::WebSocketJob);
    }
}