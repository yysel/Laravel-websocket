<?php

namespace Kitty\WebSocket\Socket;


class WebSocket
{
    public $console;
    public $event;
    public $signets;
    public $users = [];
    public $count = 0;
    public $master;
    public $timers = [];
    public $code_key;
    public $except = [];
    public $current_users;
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
        $this->code_key = env('APP_KEY', 'base64:4buUjgZDzAwk7y6vJPV6FLpihNOuqDJLocKdRRDHS38=');
        $this->master = $this->connect($config['address'], $config['port']);
        $this->sockets = array('s' => $this->master);
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
                $user = array(
                    'id' => $this->count,
                    'uuid' => guid(),
                    'socket' => $client,
                    'hand' => false,
                    'ip' => $client_ip['ip'],
                    'port' => $client_ip['port'],
                    'type' => 'user',
                );
                $this->users[$this->count] = $user;
                $this->count++;
            } else {
                $len = socket_recv($sign, $buffer, 2048, 0);
                $k = $this->search($sign);
                if ($len < 7) {
                    $user = $this->users[$k];
                    $this->closeByIdOrSocket($sign);
                    return new Frame('out', $user);
                }
                if (!$this->users[$k]['hand']) {//没有握手进行握手
                    $is_admin = $this->isAdmin($buffer);

                    if ($is_admin) {
                        $this->users[$k]['ip'] = '0.0.0.0';
                        $this->users[$k]['type'] = 'admin';
                        $this->users[$k]['hand'] = true;
                        $this->sendToAdmin($sign, 'OK');
                    } else {
                        if ($this->count >= $this->max_conn) $this->closeByIdOrSocket($sign);
                        else $this->handshake($k, $buffer);
                    }
                    return new Frame('in', $this->users[$k]);
                } else {
                    if ($this->users[$k]['type'] == 'admin') {
                        $buffer = $this->admin_decode($buffer);
                        if (is_array($buffer)) {
                            foreach ($buffer as $buf) $this->manager('admin', $k, $sign, $buf);
                            return false;
                        } else return $this->manager('admin', $k, $sign, $buffer);
                    }
                    $buffer = $this->decode($buffer);
                    return new Frame('msg', $this->users[$k], $buffer);
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
        foreach ($this->users as $key => $user) {
            if ($socket == $user['socket'])
                return $user['id'];
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
        socket_close($socket);
        if ($this->sockets[$id]) unset($this->sockets[$id]);
        if ($this->users[$id]) unset($this->users[$id]);
        return true;
    }


    public function close()
    {
        $this->current_users->map(function ($user) {
            $this->closeByIdOrSocket($user['socket']);
        });
        return $this;
    }

    public function closeByIp($ip)
    {
        $users = collect($this->users)->where('ip', '=', $ip)->toArray();
        foreach ($users as $user) {
            $this->closeByIdOrSocket($user['id']);
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
        socket_send($this->users[$k]['socket'], $new_message, strlen($new_message), 0);
        $this->users[$k]['hand'] = true;
        return true;
    }

    public function user($id)
    {
        return $this->users[$id];
    }

    public function getAllUsers()
    {
        return $this->users;
    }

    public function getCurrentUsers()
    {
        return $this->current_users;
    }

    public function addAttributeToUser($sign, $attr)
    {
        $k = $this->search($sign);
        if ($k) {
            $this->users[$k][$attr[0]] = $attr[1];
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
        if (!$this->users[$id]['socket']) return false;
        $msg = $this->encode($msg);
        return socket_send($this->users[$id]['socket'], $msg, strlen($msg), 0);
    }

    /**
     *发送消息至
     */
    public function send($msg)
    {
        $this->current_users->map(function ($user) use ($msg) {
            $this->sendBySocket($user['socket'], $msg);
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
        if ($value_two) $this->current_users = collect($this->users)->where($key, $value_one, $value_two);
        else $this->current_users = collect($this->users)->where($key, $value_one);
        return $this;
    }

    public function sendToAdmin($socket, $buffer)
    {
        $buffer = $this->admin_encode($buffer);
        return socket_send($socket, $buffer, strlen($buffer), 0);
    }

    public function broadcast($msg)
    {
        foreach ($this->users as $user) {

            if (@$user['socket'] && $user['type'] !== 'admin') {
                $this->sendBySocket(@$user['socket'], $msg);
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
        return collect($this->users)->map(function ($it) {
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
            };
        }
        return $num;
    }

    public function console($msg)
    {
        if ($this->console) {
            $msg = $msg . "\r\n";
            fwrite(STDOUT, iconv('utf-8', 'gbk//IGNORE', $msg));
        }
    }
}


