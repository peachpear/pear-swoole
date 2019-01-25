<?php
namespace common\widgets;

/**
 * 客户端格式化库
 * Class Format
 * @package common\widgets
 */
class Format
{
    /**
     * 生成ClientId
     * @param $fd
     * @param $port
     * @return string
     */
    public static function encodeClientId($fd, $port)
    {
        $ipInfo = swoole_get_local_ip();

        return base64_encode($ipInfo['eth0'] .'_' .$port .'_' .$fd);
    }

    /**
     * 解码ClientId
     * @param $clientId
     * @return array
     */
    public static function decodeClientId($clientId)
    {
        return explode('_', base64_decode($clientId));
    }

    /**
     * 获取客户端fd
     * @param $clientId
     * @return mixed
     */
    public static function getClientFd($clientId)
    {
        $conInfo = self::decodeClientId($clientId);

        return $conInfo[2];
    }

    /**
     * 检查是否为本服务端连接
     * @param $clientId
     * @param $port
     * @return bool|mixed
     */
    public static function checkIsLocal($clientId, $port )
    {
        $conInfo = self::decodeClientId($clientId);

        $ipInfo = swoole_get_local_ip();
        if ($ipInfo['eth0'] == $conInfo[0] && $port == $conInfo[1]) {
            // 是本机，返回连接fd
            return $conInfo[2];
        } else {
            // 不是本机
            return false;
        }
    }

    /**
     * 格式化返回客户端的event数据
     * @param $event
     * @param array $data
     * @return array
     */
    public static function getFormatEvent($event, $data = [])
    {
        return [
            'event' => $event,
            'data' => $data,
        ];
    }
}