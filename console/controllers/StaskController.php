<?php
namespace console\controllers;

use common\service\ClientService;
use Yii;

/**
 * Class StaskController
 * @package console\controllers
 */
class StaskController
{
    /**
     * @param $data
     * @param $server
     * @param $fd
     */
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
                case 'CONNECT' :
                    ClientService::openClientFd($server, $fd, $data['data']['client_token']);

                    Yii::$app->demoDB->close();
                    
                    break;
                case 'NEW_MSG' :
                    // 通过传递过来的$data['data']['client_token']去mysql数据表client_online_list，查找此client_token的多条信息，然后循环推送
                    $mysqlData = [];
                    $client_info = explode('_', base64_decode($mysqlData['client_id']));

                    /* 如果客户端只存在单一服务端连接且就在本服务端上，则可以直接通过fd推送 */
                    $client_fd = $client_info[2];
                    // 推送信息到客户端
                    $sendMsg = [
                        'event' => 'NEW_MSG',
                        'data' => [
                            'msg' => $data['data']['msg'],
                        ],
                    ];
                    $server->push($client_fd, json_encode($sendMsg, JSON_UNESCAPED_SLASHES));

                    /* 如果客户端存在多个服务端连接，则要通过代理服务发送到多个服务端分别推送 */
                    $proxyData = [
                        'event' => 'TO_WEBSOCKET_SERVER',
                        'data' => [
                            'server_info' => [
                                'ip' => $client_info[0],
                                'port' => $client_info[1],
                            ],
                            'server_data' => [
                                'event' => 'NEW_MSG_TO_FD',
                                'data' => [
                                    'client_id' => $mysqlData['client_id'],
                                    'fd' => $client_info[2],
                                    'msg' => $data['data']['msg'],
                                ]
                            ],
                        ],
                    ];
                    $server->proxy->send(json_encode($proxyData) ."\r\n\r\n");

                    Yii::$app->demoDB->close();

                    break;
                case 'NEW_MSG_TO_FD' :
                    // 收到代理服务器推送过来的数据，要求直接推送新信息给该服务端连接的fd
                    $client_fd = $data['data']['fd'];
                    $sendMsg = [
                        'event' => 'NEW_MSG',
                        'data' => [
                            'msg' => $data['data']['msg'],
                        ],
                    ];
                    $server->push($client_fd, json_encode($sendMsg, JSON_UNESCAPED_SLASHES));
                case 'HEART_BEAT' :
                    break;
            }
        }
    }

    /**
     * @param $server
     * @param $fd
     */
    public function close($server, $fd)
    {
        ClientService::closeClientFd($server, $fd);
    }
}