<?php
defined('YII_DEBUG') or define("YII_DEBUG", true);

$initConfig = [
    "components"  =>  [
        'errorHandler'  =>  [
            "sendTo"   =>  ["xxx1@demo.com","xxx2@demo.com"],
            "sendCC"    =>  [
                "xxxx@demo.com"=>"xxxx",
            ],
        ],
    ],
    "params"   =>  [
        'socket' => [
            'ip' => '0.0.0.0',
            'port' => '9501',
        ],
        'socket_config' => [
            'reactor_num'      => 4,
            'worker_num'       => 4,
            'task_worker_num'  => 8,
            'max_request'      => 500,
            'task_max_request' => 500,
            'dispatch_mode'    => 2,
            'daemonize'        => 1,
            'log_file'         => '/var/www/log/swoole/swoole_socket_test.log',
            'pid_file'         => '/var/www/log/swoole/swoole_socket_test_pid.log',
            'log_size'         => 204800000,
            'log_dir'          => '/var/www/log/swoole',
            'heartbeat_idle_time' => 100,
            'heartbeat_check_interval' => 60,
        ],
        'proxy' => [
            'ip' => '192.168.0.110',
            'port' => '9502',
        ],
        'proxy_config' => [
            'reactor_num'      => 2,
            'worker_num'       => 2,
            'task_worker_num'  => 4,
            'max_request'      => 1000,
            'task_max_request' => 1000,
            'dispatch_mode'    => 2,
            'daemonize'        => 1,
            'log_file'         => '/var/www/log/swoole/swoole_proxy_test.log',
            'pid_file'         => '/var/www/log/swoole/swoole_proxy_test_pid.log',
            'log_size'         => 204800000,
            'log_dir'          => '/var/www/log/swoole',
            'open_eof_split'   => true,
            'package_eof'      => "\r\n\r\n",
            'package_max_length' => 1024 *1024 *2,
        ],
        'elkIndexName' => [
            "error" =>  "error_demo_logs_dev",
            "warning" =>  "demo_logs_dev",
            "info" =>  "demo_logs_dev",
        ],
    ]
];

list($commonBaseConfig, $commonConfig) = include(__DIR__ . '/../../common/config/dev.php');
$baseConfig = include('base.php');

return [$commonBaseConfig, $commonConfig, $baseConfig, $initConfig];