<?php
namespace console\controllers;

use Yii;

/**
 * swoole-server服务端
 * Class SwooleController
 * @package console\controllers
 */
class SwooleController extends BaseController
{
    // swoole主进程pid
    private $masterPid = null;

    // swoole全局配置
    private $setting = [];

    // worker实例
    private $worker;

    /**
     * init初始化
     */
    public function init()
    {
        $this->setting = [
            'ip' => Yii::$app->params['socket']['ip'],
            'port' => Yii::$app->params['socket']['port'],
            'config' => Yii::$app->params['socket_config'],
        ];

        $this->masterPid = file_exists($this->setting['config']['pid_file'])
            ? file_get_contents($this->setting['config']['pid_file'])
            : null;
    }

    /**
     * swoole服务端启动
     */
    public function actionStart()
    {
        if ($this->masterPid > 0) {
            print_r('Server is already running. Please stop it first.' ."\r\n");
            return;
        }

        // swoole_table
        $table = new \swoole_table(8192);
        $table->column('app_token', \swoole_table::TYPE_STRING, 32);
        $table->column('role', \swoole_table::TYPE_STRING, 16);
        $table->column('client_token', \swoole_table::TYPE_STRING, 64);
        $table->column('appid', \swoole_table::TYPE_STRING, 64);
        $table->column('secret', \swoole_table::TYPE_STRING, 64);
        $table->create();

        // proxy_client
        $proxy = new \swoole_client(SWOOLE_TCP | SWOOLE_KEEP);
        $proxy->connect(
            $this->setting['proxy']['ip'],
            $this->setting['proxy']['port']
        );

        // websocket_server
        $server = new \swoole_websocket_server($this->setting['ip'], $this->setting['port']);
        $server->set($this->setting['config']);
        $server->on('Start', [$this, 'onStart']);
        $server->on('ManagerStart', [$this, 'onManagerStart']);
        $server->on('WorkerStart', [$this, 'onWorkerStart']);
        $server->on('Shutdown', [$this, 'onShutdown']);
        $server->on('Task', [$this, 'onTask']);
        $server->on('Finish', [$this, 'onTaskFinish']);
        $server->on('open', [$this, 'onOpen']);
        $server->on('message', [$this, 'onMessage']);
        $server->on('close', [$this, 'onClose']);
        $server->port = $this->setting['port'];
        $server->table = $table;
        $server->proxy = $proxy;

        $server->start();
    }

    /**
     * 重新启动worker/task进程
     */
    public function actionReload()
    {
        if (!empty($this->masterPid)) {
            \posix_kill($this->masterPid, SIGUSR1); // reload all worker
//            \posix_kill($this->masterPid, SIGUSR2); // reload all task
        } else {
            print_r('master pid is null, maybe you delete the pid file we created. you can manually kill the master process with signal SIGUSR1.' . "\r\n");
        }
    }

    /**
     * 停止swoole-server
     */
    public function actionStop()
    {
        if (!empty($this->masterPid)) {
            posix_kill($this->masterPid, SIGTERM);
        } else {
            print_r('master pid is null, maybe you delete the pid file we created. you can manually kill the master process with signal SIGTERM.' . "\r\n");
        }
    }

    /**
     * swoole-server启动后在主进程（master）的主线程回调此函数
     * @param \swoole_websocket_server $server
     */
    public function onStart(\swoole_websocket_server $server)
    {
        swoole_set_process_name("swoole_socket_master_process");

        file_put_contents($this->setting['config']['pid_file'], $server->master_pid);
    }

    /**
     * manager管理进程启动时执行
     * @param \swoole_websocket_server $server
     */
    public function onManagerStart(\swoole_websocket_server $server)
    {
        swoole_set_process_name("swoole_socket_manager_process");
    }

    /**
     * Worker|Task 进程启动时执行
     * @param \swoole_websocket_server $server
     * @param $workerId  进程id
     */
    public function onWorkerStart(\swoole_websocket_server $server, $workerId)
    {
        if ($workerId >= $server->setting['worker_num']) {
            swoole_set_process_name("swoole_socket_task_process");
        } else {
            swoole_set_process_name("swoole_socket_work_process");
        }

        $this->worker = new StaskController();

        echo 'worker_id:' .$workerId ."\r\n";
    }

    /**
     * swoole-server正常结束时执行
     * @param \swoole_websocket_server $server
     */
    public function onShutdown(\swoole_websocket_server $server)
    {
        unlink($this->setting['config']['pid_file']);
    }

    /**
     * @param \swoole_websocket_server $server
     * @param $task_id
     * @param $from_id
     * @param $data
     */
    public function onTask(\swoole_websocket_server $server, $task_id, $from_id, $data)
    {
        $this->worker->run($data, $server, $data['fd']);

        $server->finish("OK");
    }

    /**
     * @param \swoole_websocket_server $server
     * @param $task_id
     * @param $data
     */
    public function onTaskFinish(\swoole_websocket_server $server, $task_id, $data)
    {
        Yii::trace($data, __METHOD__);
    }

    /**
     *
     * @param \swoole_websocket_server $server
     * @param $request
     */
    public function onOpen(\swoole_websocket_server $server, $request)
    {
        $data = [
            'event' => 'CONNECT',
            'data' => []
        ];

        $server->push($request->fd, json_encode($data, JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param \swoole_websocket_server $server
     * @param $frame
     */
    public function onMessage(\swoole_websocket_server $server, $frame)
    {
        $data = json_decode($frame->data, true);
        $data['fd'] = $frame->fd;
        $server->task($data);
    }

    /**
     * @param \swoole_websocket_server $server
     * @param $fd
     */
    public function OnClose(\swoole_websocket_server $server, $fd)
    {
        $this->worker->close($server, $fd);
    }
}