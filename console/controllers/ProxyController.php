<?php
namespace console\controllers;

use Yii;

/**
 * proxy-server服务端
 * Class ProxyController
 * @package console\controllers
 */
class ProxyController extends BaseController
{
    // proxy主进程pid
    private $masterPid = null;

    // proxy全局配置
    private $setting = [];

    // worker实例
    private $worker;

    /**
     * init初始化
     */
    public function init()
    {
        $this->setting = [
            'ip' => '0.0.0.0',
            'port' => Yii::$app->params['proxy']['port'],
            'config' => Yii::$app->params['proxy_config'],
        ];

        $this->masterPid = file_exists($this->setting['config']['pid_file'])
            ? file_get_contents($this->setting['config']['pid_file'])
            : null;
    }

    /**
     * proxy服务端启动
     */
    public function actionStart()
    {
        if ($this->masterPid > 0) {
            print_r('Proxy is already running. Please stop it first.' ."\r\n");
            return;
        }

        // server
        $server = new \swoole_server($this->setting['ip'], $this->setting['port']);

        $server->set($this->setting['config']);
        $server->on('Start', [$this, 'onStart']);
        $server->on('ManagerStart', [$this, 'onManagerStart']);
        $server->on('WorkerStart', [$this, 'onWorkerStart']);
        $server->on('Shutdown', [$this, 'onShutdown']);
        $server->on('Task', [$this, 'onTask']);
        $server->on('Finish', [$this, 'onTaskFinish']);
        $server->on('connect', [$this, 'onConnect']);
        $server->on('receive', [$this, 'onReceive']);
        $server->on('close', [$this, 'onClose']);

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
     * 停止proxy-server
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
     * proxy-server启动后在主进程（master）的主线程回调此函数
     * @param \swoole_server $server
     */
    public function onStart(\swoole_server $server)
    {
        swoole_set_process_name("proxy_master_process");

        file_put_contents($this->setting['config']['pid_file'], $server->master_pid);
    }

    /**
     * manager管理进程启动时执行
     * @param \swoole_server $server
     */
    public function onManagerStart(\swoole_server $server)
    {
        swoole_set_process_name("proxy_manager_process");
    }

    /**
     * Worker|Task 进程启动时执行
     * @param \swoole_server $server
     * @param $workerId  进程id
     */
    public function onWorkerStart(\swoole_server $server, $workerId)
    {
        if ($workerId >= $server->setting['worker_num']) {
            swoole_set_process_name("proxy_task_process");
        } else {
            swoole_set_process_name("proxy_work_process");
        }

        $this->worker = new PtaskController();

        echo 'worker_id:' .$workerId ."\r\n";
    }

    /**
     * proxy-server正常结束时执行
     * @param \swoole_server $server
     */
    public function onShutdown(\swoole_server $server)
    {
        unlink($this->setting['config']['pid_file']);
    }

    /**
     * @param \swoole_server $server
     * @param $task_id
     * @param $from_id
     * @param $data
     */
    public function onTask(\swoole_server $server, $task_id, $from_id, $data)
    {
        $this->worker->run($data, $server, $data['fd']);

        $server->finish("success");
    }

    /**
     * @param \swoole_server $server
     * @param $task_id
     * @param $data
     */
    public function onTaskFinish(\swoole_server $server, $task_id, $data)
    {
        Yii::trace($data, __METHOD__);
    }

    /**
     * @param \swoole_server $server
     * @param $fd
     * @param $reactor_id
     */
    public function onConnect(\swoole_server $server, $fd, $reactor_id)
    {

    }

    /**
     * @param \swoole_server $server
     * @param $fd
     * @param $reactor_id
     * @param $data
     */
    public function onReceive(\swoole_server $server, $fd, $reactor_id, $data)
    {
        $data = json_decode($data, true);
        $data['fd'] = $fd;
        $server->task($data);
    }

    /**
     * @param \swoole_server $server
     * @param $fd
     * @param $reactor_id
     */
    public function OnClose(\swoole_server $server, $fd, $reactor_id)
    {
        $this->worker->close($server, $fd);
    }
}