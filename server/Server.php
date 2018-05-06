<?php
namespace server;

class Server
{
    private $resources = [];

    public function __construct(Helper $helper)
    {
        $this->encodeHelper = $helper;
    }

    public function start()
    {
        $socket = stream_socket_server("tcp://0.0.0.0:10000", $errno, $errstr);

        if (!$socket) {
            die("$errstr ($errno)\n");
        }

        $connects = [];
        while (true) {
            $read = $connects;
            $read [] = $socket;
            $write = $except = null;

            if (!stream_select($read, $write, $except, null)) {
                break;
            }

            if (in_array($socket, $read)) {
                if (($connect = stream_socket_accept($socket, -1)) && $info = $this->handshake($connect)) {
                    $connects[] = $connect;
                    //$this->onOpen($connect, $info);//вызываем пользовательский сценарий
                }
                unset($read[array_search($socket, $read)]);
            }

            foreach ($read as $connect) {
                $data = fread($connect, 100000);
                if (!$data) {
                    fclose($connect);
                    unset($connects[array_search($connect, $connects)]);
                    $this->onClose($connect);
                    continue;
                }
                $data = $this->encodeHelper->hybi10Decode($data);

                $this->onMessage($connect, $data);//вызываем пользовательский сценарий

            }
            print_r($this->resources);
        }
    }

    /**
     * @param resource
     * @return void
     */
    private function handshake($connect) {
        $info = [];

        $line = fgets($connect);
        $header = explode(' ', $line);
        $info['method'] = $header[0];
        $info['uri'] = $header[1];

        //считываем заголовки из соединения
        while ($line = rtrim(fgets($connect))) {
            if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
                $info[$matches[1]] = $matches[2];
            } else {
                break;
            }
        }

        $address = explode(':', stream_socket_get_name($connect, true)); //получаем адрес клиента
        $info['ip'] = $address[0];
        $info['port'] = $address[1];

        if (empty($info['Sec-WebSocket-Key'])) {
            return false;
        }

        //отправляем заголовок согласно протоколу вебсокета
        $SecWebSocketAccept = base64_encode(pack('H*', sha1($info['Sec-WebSocket-Key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Accept:$SecWebSocketAccept\r\n\r\n";
        fwrite($connect, $upgrade);

        return $info;
    }

    /**
     * @param resource $connect
     * @param string $data
     * @return array
     */
    private function onMessage($connect, $data)
    {
        parse_str($data, $params);
        $response = [];

        if (isset($params['clientId']) && isset($params['taskId'])) {
            $resourceId = intval($connect);
            $this->resources[$resourceId] = ['taskId' => $params['taskId'],  'clientId' => $params['clientId'], 'connect' => $connect];
            return;
        }

        if (isset($params['get-all-users']) || array_key_exists('get-all-users', $params)) {
            array_walk($this->resources , function($val,$key) use (&$response) {
                $response[] =  $val['clientId'];
            });

            fwrite($connect, $this->encodeHelper->hybi10Encode(implode(',', $response)));
            return;
        }

        if (isset($params['get-all-user-task'])) {
            array_walk($this->resources , function($val,$key) use (&$response, $params) {
                if ($val['clientId'] == $params['get-all-user-task']) {
                    $response[] =  $val['taskId'];
                }
            });

            fwrite($connect, $this->encodeHelper->hybi10Encode(implode(',', $response)));
            return;
        }

        if (isset($params['send-message']) && strtolower($params['send-message']) == 'all' && isset($params['message'])) {
            foreach ($this->resources as $resource) {
                fwrite($resource['connect'], $this->encodeHelper->encode($params['message']));
            }

            fwrite($connect, $this->encodeHelper->hybi10Encode("OK"));
            return;
        }

        if (isset($params['send-message']) &&  isset($params['message']) && isset($params['task'])) {
            foreach ($this->resources as $resource) {
                if ($resource['clientId'] == $params['send-message'] && $resource['taskId'] == $params['task']) {
                    fwrite($resource['connect'], $this->encodeHelper->encode($params['message']));
                }
            }

            fwrite($connect, $this->encodeHelper->hybi10Encode("OK"));
            return;
        }

        if (isset($params['send-message']) &&  isset($params['message'])) {
            foreach ($this->resources as $resource) {
                if ($resource['clientId'] == $params['send-message']) {
                    fwrite($resource['connect'], $this->encodeHelper->encode($params['message']));
                }
            }

            fwrite($connect, $this->encodeHelper->hybi10Encode("OK"));
            return;
        }

        fwrite($connect, $this->encodeHelper->hybi10Encode('BAD REQUEST!'));
    }

    private function onClose($connect) {
        $resourceId = intval($connect);
        unset($this->resources[$resourceId]);
    }
}