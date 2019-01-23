<?php
namespace console\controllers;

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
                    // 通过传递过来的client_token去向API获取客户端详细信息（客户端所在应用、所在应用角色、客户端标识id等）
                    // 通过客户端所在应用和所在应用角色，获取接入应用配置$chat_config
                    $chat_config = [];
                    // 写入swoole内存表
                    $server->table->set($fd, [
                        'app_token' => $chat_config['app_token'],
                        'role' => $chat_config['role'],
                        'client_token' => $data['data']['client_token'],
                        'appid' => $chat_config['appid'],
                        'secret' => $chat_config['secret'],
                    ]);
                    // 写入mysql数据表client_online_list，$chat_config['app_token']、$chat_config['role']、client_token、client_id
                    $ipInfo = swoole_get_local_ip();
                    $client_id = base64_encode($ipInfo['eth0'] . '_' . $server->port . '_' . $fd);

                    // 推送信息到客户端
                    $sendMsg = [
                        'event' => 'ONLINE',
                        'data' => [],
                    ];
                    $server->push($fd, json_encode($sendMsg, JSON_UNESCAPED_SLASHES));
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

                    /* 如果客户端存在多个服务端连接，则要通过协程发送到多个服务端分别推送 */

                    break;
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
        /* 方法1 */
        // 获取该客户端信息
        $row = $server->table->get($fd);
        // 通过$row['client_token']去mysql数据表client_online_list，查找此client_token的多条信息，然后循环断开是本服务端本fd的连接

        /* 方法2 */
        // 生成此连接的client_id，确定mysql中的位置
        $ipInfo = swoole_get_local_ip();
        $client_id = base64_encode($ipInfo['eth0'] . '_' . $server->port . '_' . $fd);

        /* 最后 */
        // 从swoole内存表删除记录
        $server->table->del($fd);
        // 从mysql数据表client_online_list删除本条记录
        // ...
        // 关闭客户端连接
        $server->close($fd);
        // 关闭这一进程的数据库连接
        Yii::$app->demoDB->close();
    }
}