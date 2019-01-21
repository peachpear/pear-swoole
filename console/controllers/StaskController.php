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
        Yii::trace($data);
        switch($data['event'])
        {
            case 'CONNECT' :
                Yii::$app->demoDB->close();
                break;
            case 'HEART_BEAT' :
                break;
        }
    }

    /**
     * @param $fd
     */
    public function close($fd)
    {
        Yii::$app->demoDB->close();
    }
}