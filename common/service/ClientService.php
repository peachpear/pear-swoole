<?php
namespace common\service;

use common\widgets\Format;
use Yii;

/**
 * 客户端相关服务类
 * Class ClientService
 * @package common\service
 */
class ClientService
{
    /**
     * 建立客户端连接
     * @param $server
     * @param $fd
     * @param $client_token
     * @return bool
     */
    public static function openClientFd($server, $fd, $client_token)
    {
        // 通过传递过来的client_token去向API获取客户端详细信息（客户端所在应用、所在应用角色、客户端标识id等）
        // 通过客户端所在应用和所在应用角色，获取接入应用配置$chat_config
        $chat_config = [];
        // 写入swoole内存表
        $server->table->set($fd, [
            'app_token' => $chat_config['app_token'],
            'role' => $chat_config['role'],
            'client_token' => $client_token,
            'appid' => $chat_config['appid'],
            'secret' => $chat_config['secret'],
        ]);
        // 写入mysql数据表client_online_list，$chat_config['app_token']、$chat_config['role']、client_token、client_id
        $client_id = Format::encodeClientId($fd, $server->port);

        // 推送信息到客户端
        $sendMsg = [
            'event' => 'ONLINE',
            'data' => [],
        ];
        self::pushDataFromLocal($server, $fd, $sendMsg);

        return true;
    }

    /**
     * 给clientId推送数据
     * @param $server
     * @param $clientId
     * @param $data
     * @return bool
     */
    public static function pushData($server, $clientId, $data)
    {
        $fd = Format::checkIsLocal($clientId, $server->port);
        if (!$fd) {
            // 如果客户端连接不在本服务端
            $client_info = Format::decodeClientId($clientId);
            // 组装数据
            $serverData = [
                'client_id' => $clientId,
                'fd' => $client_info[2],
                'data' => $data,
            ];
            $serverData = Format::getFormatEvent('FROM_OTHER_SERVER_TO_FD', $serverData);
            $proxyData = [
                'server_info' => [
                    'ip' => $client_info[0],
                    'port' => $client_info[1],
                ],
                'server_data' => $serverData,
            ];
            $proxyData = Format::getFormatEvent('TO_WEBSOCKET_SERVER', $proxyData);
            $server->proxy->send(json_encode($proxyData) ."\r\n\r\n");

            return ture;
        } else {
            if (self::pushDataFromLocal($server, $fd, $data)) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * 从本机推送数据
     * @param $server
     * @param $fd
     * @param $data
     * @return bool
     */
    public static function pushDataFromLocal($server, $fd, $data)
    {
        // 最多断开连接时间间隔
        $time = time() - 300;  // 5分钟
        // 客户端连接信息
        $connection_info = $server->getClientInfo($fd);

        // 如果未获取到客户端连接信息
        if (!$connection_info) {
            self::closeClientFd($server, $fd);
            return false;
        }

        // 如果最后一次连接在几分钟前，则断开连接
        if ($connection_info['last_time'] < $time) {
            self::closeClientFd($server, $fd);
            return false;
        }

        // 推送数据给客户端
        if (self::pushToFd($server, $fd, $data)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 推送数据给客户端
     * @param $server
     * @param $fd
     * @param $data
     * @return bool
     */
    public static function pushToFd($server, $fd, $data)
    {
        if ($server->push($fd, json_encode($data, JSON_UNESCAPED_SLASHES))) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 关闭客户端连接
     * @param $server
     * @param $fd
     * @return bool
     */
    public static function closeClientFd($server, $fd)
    {
        /* 方法1 */
        // 获取该客户端信息
        $row = $server->table->get($fd);
        // 通过$row['client_token']去mysql数据表client_online_list，查找此client_token的多条信息，然后循环断开是本服务端本fd的连接

        /* 方法2 */
        // 生成此连接的client_id，确定mysql中的位置
        $client_id = Format::encodeClientId($fd, $server->port);

        /* 最后 */
        // 从swoole内存表删除记录
        $server->table->del($fd);
        // 从mysql数据表client_online_list删除本条记录
        // ...
        // 关闭客户端连接
//        $server->close($fd);  // 这是close回调，所以连接已断开，无须再次断开
        // 关闭这一进程的数据库连接
        Yii::$app->demoDB->close();

        return true;
    }
}