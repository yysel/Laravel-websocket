<?php


namespace Kitty\WebSocket\Servers;

use App\Jobs\WebSocketJob;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Kitty\WebSocket\Socket\Frame;
use Kitty\WebSocket\Socket\WebSocket;

class WebSocketQueue implements QueueContract
{


    protected $socket;

    protected $default;

    protected $system;

    protected $credentials;

    protected $container;


    public function __construct(WebSocket $socket, $default, $system = null, array $credentials = [])
    {
        $this->socket = $socket;
        $this->default = $default;
        $this->system = $system;
        $this->credentials = $credentials;
    }

    public function setContainer($container)
    {
        $this->container = $container;
    }

    public function bulk($jobs, $data = '', $queue = null)
    {

    }


    public function size($queue = null)
    {
        return (int)$this->sqs->getQueueAttributes([
            'QueueUrl' => $this->getQueue($queue),
        ])->get('ApproximateNumberOfMessages');
    }



    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue);
    }


    public function pushOn($queue, $job, $data = '')
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue);
    }

    public function laterOn($queue, $delay, $job, $data = '')
    {
        $payload = $this->createPayload($job, $data, $queue);
        return $this->pushRaw($payload, $queue, $this->makeDelayHeader($delay));
    }


    public function pushRaw($payload, $queue = null, array $options = [])
    {

    }


    public function recreate($payload, $queue = null, $delay)
    {
        return $this->pushRaw($payload, $queue, $this->makeDelayHeader($delay));
    }


    public function later($delay, $job, $data = '', $queue = null)
    {
        $payload = $this->createPayload($job, $data, $queue);
        return $this->pushRaw($payload, $queue, $this->makeDelayHeader($delay));
    }

    public function pop($queue = null)
    {
        $job = $this->socket->read();
        if (!is_null($job) && $job instanceof Frame) {
           if(class_exists('\App\Jobs\WebSocketJob',true)) return new \App\Jobs\WebSocketJob($this->socket, $job);
           return new  WebSocketJob($this->socket, $job);
        }
    }


    public function deleteMessage($queue, Frame $message)
    {
        $this->getStomp()->ack($message);
    }


    public function getQueue($queue)
    {
        return $queue ?: $this->default;
    }

    public function getSocket()
    {
        return $this->socket;
    }


    protected function makeDelayHeader($delay)
    {

        $delay = $this->getSeconds($delay);
        if ($this->system == self::SYSTEM_ACTIVEMQ) {
            return ['AMQ_SCHEDULED_DELAY' => $delay * 1000];
        } else {
            return [];
        }
    }

    public function getConnectionName()
    {

    }


    public function setConnectionName($name)
    {

        return $this;
    }
}
