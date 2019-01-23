<?php
namespace common\widgets;

/**
 * Websocket client base class
 */
class WSClient
{
    const hashstring = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";

    public $host;
    public $port;
    public $uri;
    public $secure;
    public $origin = 'null';

    public $errno = 0;
    public $errstr = "";

    private $async = false;
    private $socket = false;
    private $curframe;
    private $recvframes;

    function __construct($host, $uri = '/', $secure = false, $port = false)
    {
        $this->host = $host;
        $this->uri = $uri;
        if ($port === false) {
            $this->port = $secure ? 443 : 80;
        } else {
            $this->port = $port;
        }
        $this->secure = $secure;
        $this->recvframes = array();
    }

    function __destruct()
    {
        $this->disconnect();
    }

    /*
     * Connect to websocket server, switch protocols
     * name: WSClient::connect
     * @param boolean $async
     * @return boolean
     *
     */
    function connect($async = false)
    {
        $this->async = $async;

        $remote = ($this->secure ? 'tls' : 'tcp') . '://' . $this->host . ':' . $this->port;
        $this->socket = @stream_socket_client($remote, $this->errno, $this->errstr);
        if ($this->socket === false) return false;

        /*
            Create and send HTTP request
        */
        $seckey = base64_encode($this->makeKey());
        $request = "GET $this->uri HTTP/1.1\r\n".
            "Connection: Upgrade\r\n".
            "Upgrade: websocket\r\n".
            "Pragma: no-cache\r\n".
            "Cache-Control: no-cache\r\n".
            "Host: $this->host\r\n".
            "Origin: $this->origin\r\n".
            "Sec-WebSocket-Version: 13\r\n".
            "Sec-WebSocket-Key: $seckey\r\n".
            "\r\n";

        if (!$this->fwrite_stream($request)) {
            $this->errstr = "Error sending request";
            $this->disconnect();
            return false;
        }

        /*
            Read and check HTTP response
        */
        $response = array();
        do {
            $line = fgets($this->socket, 1024);
            if ($line === false) {
                $this->errstr = "Error reading response";
                $this->disconnect();
                return false;
            }
            $response[] = trim($line);
        } while (strlen(trim($line)) > 0);

//		var_dump($response);
        $parsed = $this->parseResponse($response);
//		var_dump($parsed);

        if ($parsed['responseCode'] != 101) {
            $this->errstr = "Invalid response code";
            $this->disconnect();
            return false;
        }

        if (!isset($parsed['headers']['Upgrade']) || strcasecmp($parsed['headers']['Upgrade'], 'websocket') ||
            !isset($parsed['headers']['Connection']) || strcasecmp($parsed['headers']['Connection'], 'Upgrade')) {
            $this->errstr = "Invalid response headers";
            $this->disconnect();
            return false;
        }

//		echo base64_decode($parsed['headers']['Sec-WebSocket-Accept'])."\n";
//		echo sha1($seckey.self::hashstring, true)."\n";

        if (!isset($parsed['headers']['Sec-WebSocket-Accept']) || strcmp(base64_decode($parsed['headers']['Sec-WebSocket-Accept']), sha1($seckey.self::hashstring, true))) {
            $this->errstr = "Invalid security key";
            $this->disconnect();
            return false;
        }

        $this->curframe = new WSFrame();

        return true;
    }

    /*
     * Disconnect from server
     * name: WSClient::disconnect
     *
     */
    function disconnect()
    {
        if ($this->socket) @fclose($this->socket);
        $this->socket = false;
    }

    /*
     * Get information about connection
     * name: WSClient::getMetadata
     * @return array
     *
     */
    function getMetadata()
    {
        if ($this->socket) {
            return stream_get_meta_data($this->socket);
        } else {
            return array();
        }
    }

    /*
     * Make 16-byte random string
     * @return string
     */
    function makeKey()
    {
        $key = "";
        mt_srand();
        for ($i = 0; $i < 16; $i++) $key .= chr(mt_rand(1, 255));
        return $key;
    }

    /*
     * Send string to stream
     * @param string $string
     * @return mixed
     */
    function fwrite_stream($string)
    {
        $count = 0;

        for ($written = 0; $written < strlen($string); $written += $fwrite)
        {
            $fwrite = @fwrite($this->socket, substr($string, $written));
            if ($fwrite === false) return false;

            if ($fwrite === 0) {
                $count ++;
                if ($count >= 10) {
                    return false;
                } else {
                    continue;
                }
            } else {
                $count = 0;
            }
        }

        return $written;
    }

    /*
     * Parse HTTP response
     * name: WSClient::parseResponse
     * @param array $response
     * @return array
     *
     */
    function parseResponse($response)
    {
        $res = array();

        if (!preg_match('/^HTTP\/([0-9\.]+)\s+([0-9]+)\s+(.*)/', $response[0], $parts)) return false;
        $res['httpVersion'] = $parts[1];
        $res['responseCode'] = $parts[2];
        $res['responseText'] = $parts[3];
        $res['headers'] = array();

        foreach ($response as $num=>$line)
        {
            if (!$num) continue;
            $parts = preg_split('/:\s+/', $line, 2);
            if (count($parts) != 2) continue;
            if (array_key_exists($parts[0], $res['headers'])) {
                if (!is_array($res['headers'][$parts[0]])) {
                    $res['headers'][$parts[0]] = array($res['headers'][$parts[0]]);
                }
                $res['headers'][$parts[0]][] = $parts[1];
            } else {
                $res['headers'][$parts[0]] = $parts[1];
            }
        }

        return $res;
    }

    /*
     * Prepare and send frame
     * name: WSClient::send
     * @param int $opcode
     * @param string $payload
     * @param int $masked
     * @param array $masking_key
     * @return boolean
     *
     */
    function send($opcode, $payload, $masked = 1, $masking_key = array())
    {
        $frame = new WSFrame();
        $frame->set($opcode, $payload, $masked, $masking_key);

        return $this->sendFrame($frame);
    }

    /*
     * Send prepared frame
     * name: WSClient::sendFrame
     * @param WSFrame $frame
     * @return boolean
     *
     */
    function sendFrame($frame)
    {
        if ($this->fwrite_stream($frame->encode()) === false) {
            $this->errstr = "Write error";
            $this->disconnect();
            return false;
        }

        return true;
    }

    /*
     * Read frame from websocket
     * name: WSClient::read
     * @return mixed
     *
     */
    function read()
    {
        if ($this->async) {
            $read   = array($this->socket);
            $write  = null;
            $except = null;
            $num = @stream_select($read, $write, $accept, 0, 200000);
            if ($num === false) {
                $this->errstr = "Select error";
                $this->disconnect();
                return false;
            }

            if ($num) {
                $recvbuf = @fread($this->socket, 8192);
                while (strlen($recvbuf) > 0) {
                    $decoded = $this->curframe->decode($recvbuf);
                    if ($this->curframe->framestate == FRAME_STATE_ERROR) {
                        $this->errstr = "Frame decoding error " . $this->curframe->errcode;
                        $this->disconnect();
                        return false;
                    } elseif ($this->curframe->framestate == FRAME_STATE_COMPLETED) {
                        if ($this->processFrame($this->curframe)) {
                            $recvbuf = substr($recvbuf, $decoded);
                            $this->curframe = new WSFrame();
                        } else {
                            $this->disconnect();
                            return false;
                        }
                    }
                }
            }
        } else {
            for (; ; ) {
                $recvbuf = @fread($this->socket, 1);
                $decoded = $this->curframe->decode($recvbuf);
                if ($this->curframe->framestate == FRAME_STATE_ERROR) {
                    $this->errstr = "Frame decoding error " . $this->curframe->errcode;
                    $this->disconnect();
                    return false;
                } elseif ($this->curframe->framestate == FRAME_STATE_COMPLETED) {
                    if ($this->processFrame($this->curframe)) {
                        $this->curframe = new WSFrame();
                        break;
                    } else {
                        $this->disconnect();
                        return false;
                    }
                }
            }
        }

        return count($this->recvframes);
    }

    /*
     * Process frame
     * name: WSClient::processFrame
     * @param WSFrame $frame
     * @return boolean;
     *
     */
    private function processFrame($frame)
    {
        switch ($frame->opcode)
        {
            case WS_FRAME_CLOSE:
                $this->errstr = "Disconnect requested by server";
                if ($frame->payload) {
                    $this->errno = unpack("n", substr($frame->payload, 0, 2));
                    $this->errstr .= " (" . $this->errno . ") " . substr($frame->payload, 2);
                }
                return false;
                break;

            case WS_FRAME_PING:
                $pong = new WSFrame();
                $pong->set(WS_FRAME_PONG, $frame->payload, 1);
                if (!$this->sendFrame($pong)) {
                    $this->errstr = "Error sending pong";
                    return false;
                }
                break;

            case WS_FRAME_PONG:
                break;

            default:
                array_push($this->recvframes, $frame);
        }
        return true;
    }

    /*
     * Get frame from receive buffer
     * name: WSClient::getFrame
     * @return WSFrame
     *
     */
    public function getFrame()
    {
        return array_shift($this->recvframes);
    }

}
