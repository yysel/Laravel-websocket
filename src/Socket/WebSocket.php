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

    public $except = [];
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
        $this->console = $config['console'];
        $this->master = $this->connect($config['address'], $config['port']);
        $this->sockets = array('s' => $this->master);
    }

    public function connect($address, $port)
    {
        $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($server, $address, $port);
        socket_listen($server);
        $this->console('开始监听: ' . $address . ' : ' . $port);
        return $server;
    }

    public function read()
    {
        $changes = $this->sockets;
        @socket_select($changes, $write = NULL, $except = NULL, NULL);
        foreach ($changes as $k => $sign) {
            if ($sign == $this->master) {
                $client = socket_accept($this->master);
                var_dump($client);
                if (!($client_ip = $this->checkSocket($client))) {

                    $this->close($client);
                }
                $this->sockets[] = $client;
                $user = array(
                    'key' => $this->count,
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
                if ($len < 7 && $this->users[$k]['type']=='user') {
                    $this->close($sign);
                    return new Frame('out', $k, $sign);
                }
                if (!$this->users[$k]['hand']) {//没有握手进行握手
                    if ($res = $this->isAdmin($buffer)) {
                        $this->users[$k]['ip'] = '0.0.0.0';
                        $this->users[$k]['type'] = 'admin';
                        $this->users[$k]['hand'] = true;
                        $this->sendToAdmin($sign, 'OK');
                    } else $this->handshake($k, $buffer);
                    return new Frame('in', $k, $sign);
                } else {
                    if ($this->users[$k]['type'] == 'admin') {
                        return $this->manager('admin', $k, $sign, $buffer);
                    }
                    $buffer = $this->decode($buffer);
                    return new Frame('msg', $k, $sign, $buffer);
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

    public function search($sign)
    {//通过标示遍历获取id
        foreach ($this->users as $k => $v) {
            //var_dump($this->users);
            if ($sign == $v['socket'])
                return $k;
        }
        return false;
    }

    public function close($sign)
    {
        if (is_resource($sign)) {
            $socket = $sign;
            $key = array_search($sign, $this->sockets);
        } else {
            $socket = $this->sockets[$sign]['socket'];
            $key = $sign;
        }
        socket_close($socket);
        if ($this->sockets[$key]) unset($this->sockets[$key]);
        if ($this->users[$key]) unset($this->users[$key]);
        return true;
    }

    public function closeByIp($ip)
    {
        $users = collect($this->users)->where('ip', '=', $ip)->toArray();
        foreach ($users as $user) {
            $this->close($user['key']);
        }
        return true;
    }

    protected function isAdmin($buffer)
    {
        $key = env('APP_KEY', 'base64:4buUjgZDzAwk7y6vJPV6FLpihNOuqDJLocKdRRDHS38=');
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
        socket_write($this->users[$k]['socket'], $new_message, strlen($new_message));
        $this->users[$k]['hand'] = true;
        return true;
    }

    public function user($key)
    {
        return $this->users[$key];
    }

    public function getAllUser()
    {
        return $this->users;
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
    public function sendBykey($id, $msg)
    {
        if (!$this->users[$id]['socket']) {
            return false;
        }
        $msg = $this->encode($msg);
        return socket_write($this->users[$id]['socket'], $msg, strlen($msg));
    }

    public function send($socket, $msg)
    {
        $msg = $this->encode($msg);
        return socket_write($socket, $msg, strlen($msg));
    }

    public function sendToAdmin($socket, $buffer)
    {
        return socket_write($socket, $buffer, strlen($buffer));
    }

    public function broadcast($msg)
    {
        foreach ($this->users as $user) {
            if (@$user['socket'] && $user['type'] !== 'admin') {
                $this->send(@$user['socket'], $msg);
            }
        }
    }

    protected function manager($type, $key, $socket, $buffer)
    {
        $order = $this->enOrder($buffer);
        $method = reset($order);
        $param = next($order);
        $param_two = next($order);
        $data = '';
        switch ($method) {
            case 'broadcast':
                $this->broadcast($param);
                break;
            case 'show':
                $data = $this->show();
                break;
            case 'close':
                if($param=='-ip') $this->closeByIp($param_two);
                else $this->close($param);
                break;
            default :
                $method = 'null';
                break;
        }
        $msg = [
            'type' => $method,
            'data' => $data,
        ];
        $this->sendToAdmin($socket, json_encode($msg));
        return false;
    }

    protected function enOrder($buffer)
    {
        $buffer = explode(' ', $buffer);
        foreach ($buffer as $key => $item) if (empty($item)) unset($buffer[$key]);
        return $buffer;
    }

    public function show()
    {
        return collect($this->users)->map(function ($it) {
            return collect($it)->only(['key', 'ip', 'type']);
        })->values()->toArray();

    }

    public function console($msg)
    {
        if ($this->console) {
            $msg = $msg . "\r\n";
            fwrite(STDOUT, iconv('utf-8', 'gbk//IGNORE', $msg));
        }
    }
}


