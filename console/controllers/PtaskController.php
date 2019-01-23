<?php
namespace console\controllers;

use common\widgets\WSClient;
use Yii;

class PtaskController
{
    public function run($data, $server, $fd)
    {
        if (!isset($data['event'])) {
            // 客户端未传入event参数，则返回错误
            $returnData = [
                'code' => 10101,
                'msg' => 'event is null',
                'data' => [],
            ];
            $server->push($fd, json_encode($returnData, JSON_UNESCAPED_SLASHES));
        } else {
            switch($data['event'])
            {
                case 'TO_WEBSOCKET_SERVER' :
                    $server_info = $data['data']['server_info'];
                    $server_data = $data['data']['server_data'];

                    $cli = new WSClient($server_info['ip'], '/', false, $server_info['port']);
                    if ($cli->connect(true)) {
                        if (!$cli->send(WS_FRAME_TEXT, json_encode($server_data), 1)) {
                            echo $cli->errstr . "\n";
                        }
                    }
                    sleep(1);
                    $cli->disconnect();
                    break;
                case 'HEART_BEAT' :
                    break;
            }
        }
    }

    public function close($server, $fd)
    {
        return true;
    }
}