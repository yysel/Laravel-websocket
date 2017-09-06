<?php
/**
 * Created by PhpStorm.
 * User: jim
 * Date: 2017/9/6
 * Time: 15:40
 */

namespace App\Jobs;




class Timer
{
    protected $websocket;
    public $timers=[];

    public function boot()
    {
      $this->register('1504686920',function ($socket){
          $socket->dd();
      });
    }


    public function getTimer()
    {
        $this->boot();
        return $this->timers;
    }
    public function register($time,$func)
    {
        $this->timers[] = [
            'time' => $time,
            'func' => $func
        ];
    }
}