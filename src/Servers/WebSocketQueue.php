<?php


namespace Kitty\WebSocket\Servers;

use Illuminate\Contracts\Queue\Queue as QueueContract;
use Kitty\WebSocket\Job\WSJob;
use Kitty\WebSocket\Socket\Frame;
use Kitty\WebSocket\Socket\WebSocket;

class WebSocketQueue implements QueueContract
{

    /**
     * The Stomp instance.
     *`
     * @var Socket
     */
    protected $socket;
    /**
     * The name of the default queue.
     *
     * @var string
     */
    protected $default;
    /**
     * The system name.
     *
     * @var string
     */
    protected $system;
    /**
     * The Stomp credentials for connection.
     *
     * @var array
     */
    protected $credentials;

    protected $container;

    /**
     * Create a new ActiveMQ queue instance.
     *
     * @param WebSocket $socket
     * @param string $default
     * @param string|null $system
     * @param array $credentials [username=string, password=string]
     */
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

    /**
     * Get the size of the queue.
     *
     * @param  string $queue
     * @return int
     */
    public function size($queue = null)
    {
        return (int)$this->sqs->getQueueAttributes([
            'QueueUrl' => $this->getQueue($queue),
        ])->get('ApproximateNumberOfMessages');
    }


    /**
     * Push a new job onto the queue.
     *
     * @param  string $job
     * @param  mixed $data
     * @param  string $queue
     * @return mixed
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue);
    }


    /**
     * Push a new job onto the queue.
     *
     * @param  string $queue
     * @param  string $job
     * @param  mixed $data
     * @return mixed
     */
    public function pushOn($queue, $job, $data = '')
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue);
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  string $queue
     * @param  \DateTime|int $delay
     * @param  string $job
     * @param  mixed $data
     * @return mixed
     */
    public function laterOn($queue, $delay, $job, $data = '')
    {
        $payload = $this->createPayload($job, $data, $queue);
        return $this->pushRaw($payload, $queue, $this->makeDelayHeader($delay));
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string $payload
     * @param  string $queue
     * @param  array $options
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {

    }

    /**
     * Push a raw payload onto the queue after encrypting the payload.
     *
     * @param  string $payload
     * @param  string $queue
     * @param  int $delay
     * @return mixed
     */
    public function recreate($payload, $queue = null, $delay)
    {
        return $this->pushRaw($payload, $queue, $this->makeDelayHeader($delay));
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  \DateTime|int $delay
     * @param  string $job
     * @param  mixed $data
     * @param  string $queue
     * @return mixed
     */
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
           return new  WSJob($this->socket, $job);
        }
    }

    /**
     * Delete a message from the Stomp queue.
     *
     * @param  string $queue
     * @param  string|Frame $message
     * @return void
     */
    public function deleteMessage($queue, Frame $message)
    {
        $this->getStomp()->ack($message);
    }

    /**
     * Get the queue or return the default.
     *
     * @param  string|null $queue
     * @return string
     */
    public function getQueue($queue)
    {
        return $queue ?: $this->default;
    }

    /**
     * @return Stomp
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * @param int $delay
     * @return array
     */
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

    /**
     * Set the connection name for the queue.
     *
     * @param  string $name
     * @return $this
     */
    public function setConnectionName($name)
    {

        return $this;
    }
}
