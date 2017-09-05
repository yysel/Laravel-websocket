<?php

namespace Kitty\WebSocket\Helper;

use Illuminate\Console\Command as Com;
use \Kitty\WebSocket\Helper\FillHelper;


class MakeJobCommand extends Com
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'socket:make {job}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the websocket server!';

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
        $maker= new FillHelper();
        $maker->makeJob();
    }

}