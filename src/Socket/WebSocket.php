<?php

namespace Kitty\WebSocket\Socket;


class WebSocket
{
    public $console;
    public $clients = [[]];
    public $count = 1;
    public $sockets = [[]];
    public $master;
    public $timers = [];
    public $code_key;
    public $manager;
    public $except = [];
    public $current_clients;
    protected $max_conn;
    public $method_array = [
        'send', 'broadcast', 'close'
    ];

    public function __construct($config)
    {
        if (substr(php_sapi_name(), 0, 3) !== 'cli') {
            die("请通过命令行模式运行!");
        }
        error_reporting(0);
        set_time_limit(0);
        ob_implicit_flush();
        // $timer = new Timer;
        //$this->timers = $timer->getTimer();
        $this->max_conn = $config['max_conn'];
        $this->console = $config['console'];
        $this->manager = $config['manager'];
        $this->code_key = env('APP_KEY', 'base64:4buUjgZDzAwk7y6vJPV6FLpihNOuqDJLocKdRRDHS38=');
        $this->master = $this->connect($config['address'], $config['port']);
        $this->sockets['s'] = $this->master;
    }

    public function connect($address, $port)
    {
        $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($server, $address, $port);
        socket_listen($server);
        socket_set_nonblock($server);
        $this->console('开始监听: ' . $address . ' : ' . $port);
        return $server;
    }


    public function read()
    {

//        if ($this->timers) {
//            if($this->checkTimer()>0) return new Frame('timer');;
//        };
        $changes = $this->sockets;
        @socket_select($changes, $write = NULL, $except = NULL, NULL);
        foreach ($changes as $k => $sign) {
            if ($sign == $this->master) {
                $client = socket_accept($this->master);
                if (!($client_ip = $this->checkSocket($client))) {

                    $this->closeByIdOrSocket($client);
                }
                $this->sockets[] = $client;
                $client = array(
                    'id' => $this->count,
                    'uuid' => guid(),
                    'socket' => $client,
                    'hand' => false,
                    'ip' => $client_ip['ip'],
                    'port' => $client_ip['port'],
                    'type' => 'client',
                );
                $this->clients[$this->count] = $client;
                $this->count++;
            } else {
                $len = socket_recv($sign, $buffer, 2048, 0);
                $k = $this->search($sign);
                if ($len < 7) {
                    $client = $this->clients[$k];
                    $this->closeByIdOrSocket($sign);
                    return new Frame('out', $client);
                }
                if (!$this->clients[$k]['hand']) {//没有握手进行握手
                    $is_admin = $this->isAdmin($buffer);

                    if ($is_admin) {
                        $this->clients[$k]['ip'] = '0.0.0.0';
                        $this->clients[$k]['type'] = 'admin';
                        $this->clients[$k]['hand'] = true;
                        $this->sendToAdmin($sign, 'OK');
                    } else {
                        if ($this->count >= $this->max_conn) $this->closeByIdOrSocket($sign);
                        else $this->handshake($k, $buffer);
                    }
                    return new Frame('in', $this->clients[$k]);
                } else {
                    if ($this->clients[$k]['type'] == 'admin') {
                        $buffer = $this->admin_decode($buffer);
                        if($this->manager) return new Frame('manager',$this->clients[$k],$buffer);
                        if (is_array($buffer)) {
                            foreach ($buffer as $buf) $this->manager('manager', $k, $sign, $buf);
                            return false;
                        } else return $this->manager('manager', $k, $sign, $buffer);
                    }
                    $buffer = $this->decode($buffer);
                    return new Frame('msg', $this->clients[$k], $buffer);
                }

            }
        }
    }

    public function checkSocket($client)
    {
        socket_getpeername($client, $ip, $port);
        if (in_array($ip, $this->except)) return false;
        return [
            'ip' => $ip,
            'port' => $port,
        ];
    }

    /**
     *通过socket遍历获取id
     */
    public function search($socket)
    {
        foreach ($this->clients as $key => $client) {
            if ($socket == $client['socket'])
                return $client['id'];
        }
        return false;
    }

    public function closeByIdOrSocket($sign)
    {
        if (is_resource($sign)) {
            $socket = $sign;
            $id = array_search($sign, $this->sockets);
        } else {
            $socket = $this->sockets[$sign]['socket'];
            $id = $sign;
        }
        var_dump($socket);
        var_dump($id);
        socket_close($socket);
        if ($this->sockets[$id]) unset($this->sockets[$id]);
        if ($this->clients[$id]) unset($this->clients[$id]);
        return true;
    }


    public function close()
    {
        $this->current_clients->map(function ($client) {
            $this->closeByIdOrSocket($client['socket']);
        });
        return $this;
    }

    public function closeByIp($ip)
    {
        $clients = collect($this->clients)->where('ip', '=', $ip)->toArray();
        foreach ($clients as $client) {
            $this->closeByIdOrSocket($client['id']);
        }
        return true;
    }

    protected function isAdmin($buffer)
    {
        $key = $this->code_key;
        $buffer = $this->admin_decode($buffer);
        if ($buffer == "Order-Master-Key_Handing-|" . $key) return true;
        return false;
    }

    public function handshake($k, $buffer)
    {
        $buf = substr($buffer, strpos($buffer, 'Sec-WebSocket-Key:') + 18);
        $key = trim(substr($buf, 0, strpos($buf, "\r\n")));
        $new_key = base64_encode(sha1($key . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));
        $new_message = "HTTP/1.1 101 Switching Protocols\r\n";
        $new_message .= "Upgrade: websocket\r\n";
        $new_message .= "Sec-WebSocket-Version: 13\r\n";
        $new_message .= "Connection: Upgrade\r\n";
        $new_message .= "Sec-WebSocket-Accept: " . $new_key . "\r\n\r\n";
        socket_send($this->clients[$k]['socket'], $new_message, strlen($new_message), 0);
        $this->clients[$k]['hand'] = true;
        return true;
    }

    public function client($id)
    {
        return $this->clients[$id];
    }

    public function getAllclients()
    {
        return $this->clients;
    }

    public function getCurrentClients()
    {
        return $this->current_clients;
    }

    public function addAttributeToClient($id, $attr)
    {
        if ($this->clients[$id]) {
            $this->clients[$id] = array_merge($this->clients[$id], $attr);
        }
    }

    public function decode($str)
    {
        $mask = array();
        $data = '';
        $msg = unpack('H*', $str);
        $head = substr($msg[1], 0, 2);
        if (hexdec($head{1}) === 8) {
            $data = false;
        } else if (hexdec($head{1}) === 1) {
            $mask[] = hexdec(substr($msg[1], 4, 2));
            $mask[] = hexdec(substr($msg[1], 6, 2));
            $mask[] = hexdec(substr($msg[1], 8, 2));
            $mask[] = hexdec(substr($msg[1], 10, 2));
            $s = 12;
            $e = strlen($msg[1]) - 2;
            $n = 0;
            for ($i = $s; $i <= $e; $i += 2) {
                $data .= chr($mask[$n % 4] ^ hexdec(substr($msg[1], $i, 2)));
                $n++;
            }
        }
        return $data;
    }

    public function encode($msg)
    {
        $msg = preg_replace(array('/\r$/', '/\n$/', '/\r\n$/',), '', $msg);
        $frame = array();
        $frame[0] = '81';
        $len = strlen($msg);
        $frame[1] = $len < 16 ? '0' . dechex($len) : dechex($len);
        $frame[2] = $this->ord_hex($msg);
        $data = implode('', $frame);
        return pack("H*", $data);
    }

    public function ord_hex($data)
    {
        $msg = '';
        $l = strlen($data);
        for ($i = 0; $i < $l; $i++) {
            $msg .= dechex(ord($data{$i}));
        }
        return $msg;
    }

    //通过id推送信息
    public function sendById($id, $msg)
    {
        if (!$this->clients[$id]['socket']) return false;
        $msg = $this->encode($msg);
        return socket_send($this->clients[$id]['socket'], $msg, strlen($msg), 0);
    }

    /**
     *发送消息至
     */
    public function send($msg)
    {
        $this->current_clients->map(function ($client) use ($msg) {
            $this->sendBySocket($client['socket'], $msg);
        });
        return $this;
    }

    public function sendBySocket($socket, $msg)
    {
        $msg = $this->encode($msg);
        return @socket_send($socket, $msg, strlen($msg), 0);
    }

    /**
     *通过条件过滤当前链接用户并存放至发送站
     */
    public function where($key, $value_one, $value_two = null)
    {
        if ($value_two) $this->current_clients = collect($this->clients)->where($key, $value_one, $value_two);
        else $this->current_clients = collect($this->clients)->where($key, $value_one);
        return $this;
    }

    public function sendToAdmin($socket, $buffer)
    {
        $buffer = $this->admin_encode($buffer);
        return socket_send($socket, $buffer, strlen($buffer), 0);
    }

    public function broadcast($msg)
    {
        foreach ($this->clients as $client) {

            if (@$client['socket'] && $client['type'] !== 'admin') {
                $this->sendBySocket(@$client['socket'], $msg);
            }
        }
    }

    protected function manager($type, $id, $socket, $buffer)
    {
        $orderArray = $this->enOrder($buffer);
        $order = $orderArray['order'];
        $param = $orderArray['param'];
        $extra = $orderArray['extra'];
        $option = $orderArray['option'];
        $data = '';
        switch ($order) {
            case 'broadcast':
                $this->broadcast($param[0]);
                break;
            case 'show':
                $data = $this->show();
                break;
            case 'close':
                if (array_key_exists('--ip', $extra)) $this->closeByIp($extra['--ip']);
                else $this->closeByIdOrSocket($param[0]);
                break;
            case 'exit':
                $this->closeByIdOrSocket($socket);
                break;
            case 'send';
                if (array_key_exists('--id', $extra)) $this->sendById($extra['--id'], $param[0]);
                elseif (array_key_exists('--uuid', $extra)) $this->where('uuid', $extra['--uuid'])->send($param[0]);
                elseif (array_key_exists('--ip', $extra)) $this->where('ip', $extra['--ip'])->send($param[0]);
                elseif (array_key_exists('--t', $extra)) $this->where('type', $extra['--t'])->send($param[0]);
                else $this->broadcast($param[0]);
                break;
            default :
                $order = 'null';
                break;
        }
        $msg = [
            'type' => $order,
            'data' => $data,
        ];
        if ($order == 'exit' || array_key_exists('-s', $option)) return false;
        else $this->sendToAdmin($socket, json_encode($msg));
    }


    /**
     *解码指令
     * order=[
     *      'order' //指令
     *      'param' //指令参数
     *      'extra'=>[额外指令码参数] //额外指令码
     *      'option'   //选项
     *  ]
     */
    protected function enOrder($buffer)
    {
        $buffer = explode(' ', $buffer);
        $is_first = true;
        $order = [];
        foreach ($buffer as $key => $item) {
            if (empty($item)) continue;
            else if ($is_first) {
                $order['order'] = $item;
                $is_first = false;
            } elseif (substr($item, 0, 1) === '-') {
                if (substr($item, 0, 2) === '--') $order['extra'][$item] = [];
                else $order['option'][] = $item;
            } elseif (substr($buffer[$key - 1], 0, 2) === '--') $order['extra'][$buffer[$key - 1]] = $item;
            else $order['param'][] = $item;
        }
        return $order;
    }

    public function admin_encode($buffer)
    {
        return json_encode(['code' => $this->code_key, 'order' => $buffer]);
    }

    public function admin_decode($buffer)
    {
        $buffers = explode("\r\n", $buffer);
        $order_list = [];
        foreach ($buffers as $buffer) {
            if ($buffer) {
                $buffer = json_decode($buffer);
                $order_list[] = $buffer->order;
            }
        }
        if (count($order_list) < 2) return $order_list[0];
        else return $order_list;
    }

    public function show()
    {
        return collect($this->clients)->map(function ($it) {
            return collect($it)->only(['id', 'uuid', 'ip', 'type']);
        })->values()->toArray();

    }

    protected function checkTimer()
    {
        $num = 0;
        foreach ($this->timers as $timer) {
            if ($timer['time'] <= time()) {
                $func = $timer['func'];
                $func($this);
                $num++;
            }
        }
        return $num;
    }

    public function console($msg)
    {
        if ($this->console) {
            $msg = $msg . "\r\n";
            fwrite(STDOUT, out($msg));
        }
    }
}


