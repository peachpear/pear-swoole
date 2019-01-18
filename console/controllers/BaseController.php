<?php
namespace console\controllers;

use Yii;
use yii\console\Controller;

class BaseController extends Controller
{
    public function beforeAction($action)
    {
        return parent::beforeAction($action);
    }

    public function afterAction($action, $result)
    {
        return parent::afterAction($action, $result);
    }
}