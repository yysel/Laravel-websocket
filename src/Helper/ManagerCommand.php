<?php

namespace Kitty\WebSocket\Helper;

use Illuminate\Console\Command as Com;
use Kitty\WebSocket\Socket\SocketManager;


class ManagerCommand extends Com
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'socket:manager';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make an App!';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $manager = new SocketManager();
        echo $this->out("正在连接。。。");
        $manager->connect();
        $notice="===================WebSocket进程控制台=========================";
        $status=true;
        while (true) {
            $switch = $this->ask($this->out($notice));
            if ($switch == '\q') break;
            if(!$status) break;
            $manager->send($this->in($switch));
            $status=$this->sayToSocket($manager);
        }
    }

    public function sayToSocket($manager)
    {
        if($buffer=$manager->read()){
            $res= json_decode($buffer,true);
            switch ($res['type']){
                case 'show':
                    $arr=['key','ip','type'];
                    $this->table($arr, $res['data']);
                    break;
                default :
                    continue;
                    break;
            }
            if($buffer=='close')return false;
            return true;
        }
    }

    public function out($str)
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') return $str;
        return iconv("UTF-8", "GBK", $str);

    }

    public function in($str)
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') return $str;
        return iconv("GBK","UTF-8", $str);
    }
}