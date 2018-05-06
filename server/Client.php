<?php
namespace server;

class Client {
    private $helper;

    public function __construct(Helper $helper)
    {
        $this->helper = $helper;
    }

    public function send($msg) {
        $socket = stream_socket_client('tcp://0.0.0.0:10000', $errorNumber, $errorString, 1);

        if (!$socket) {
            echo "{$errorString} ({$errorNumber})<br />\n";
        } else {
            $data = "$msg";
            $head = "GET / HTTP/1.1\r\n" .
                "Host: localhost\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Key: tQXaRIOk4sOhqwe7SBs43g==\r\n" .
                "Sec-WebSocket-Version: 13\r\n" .
                "Content-Length: " . strlen($data) . "\r\n" . "\r\n";

            fwrite($socket, $head);
            $headers = fread($socket, 2000);
            echo $headers;
            fwrite($socket, $this->helper->hybi10Encode($msg));
            $wsdata = fread($socket, 2000);
            if ($wsdata) {
                return $this->helper->hybi10Decode($wsdata);
            }

            fclose($socket);
        }
    }
}