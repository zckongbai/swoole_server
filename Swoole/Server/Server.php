<?php

namespace Swoole\Server;

class Server
{
	protected $sw;
	static $pidFile;
    static $swooleMode = SWOOLE_BASE;
    static $useSwooleHttpServer = true;

    static $swoole;

	function __construct($host, $port, $ssl = false)
	{
        $this->host = $host;
        $this->port = $port;
        $flag = $ssl ? (SWOOLE_SOCK_TCP | SWOOLE_SSL) : SWOOLE_SOCK_TCP;
		$this->sw = new \swoole_http_server($host, $port, self::$swooleMode, $flag);
        
		$this->runtimeSetting = array(
                'daemonize' =>  false,
	            'reactor_num' => 1,      //reactor thread num
	            'worker_num' => 1,       //worker process num
	            'backlog' => 128,        //listen backlog
	            'open_cpu_affinity' => 1,
	            'open_tcp_nodelay' => 1,
	            'log_file' => LOGPATH . '/swoole.log',
			);

	}

    /**
     * 设置PID文件
     * @param $pidFile
     */
    static function setPidFile($pidFile)
    {
        self::$pidFile = $pidFile;
    }

    /**
     * 显示命令行指令
     */
    static function start($startFunction)
    {
        if (empty(self::$pidFile))
        {
            throw new \Exception("require pidFile.");
        }
        $pid_file = self::$pidFile;
        if (is_file($pid_file))
        {
            $server_pid = file_get_contents($pid_file);
        }
        else
        {
            $server_pid = 0;
        }

        global $argv;
        if (empty($argv[1]))
        {
            goto usage;
        }
        elseif ($argv[1] == 'reload')
        {
            if (empty($server_pid))
            {
                exit("Server is not running");
            }
            posix_kill($server_pid, SIGUSR1);
            exit;
            // $this->sw->reload();
            // return ;
        }
        elseif ($argv[1] == 'stop')
        {
            if (empty($server_pid))
            {
                exit("Server is not running\n");
            }
            posix_kill($server_pid, SIGTERM);
            exit;
            // $this->sw->shutdown();
            // return ;
        }
        elseif ($argv[1] == 'start')
        {
            //已存在ServerPID，并且进程存在
            // if (!empty($server_pid) and \Swoole::$php->os->kill($server_pid, 0))
            if (!empty($server_pid) and posix_kill($server_pid, SIGTERM))
            {
                exit("Server is already running.\n");
            }
        }
        else
        {
            usage:
            // $kit->specs->printOptions("php {$argv[0]} start|stop|reload");
            echo "php {$argv[0]} start|stop|reload";
            exit;
        }

        $startFunction();

    }

    /**
     * 自动推断扩展支持
     * 默认使用swoole扩展,其次是libevent,最后是select(支持windows)
     * @param      $host
     * @param      $port
     * @param bool $ssl
     * @return Server
     */
    static function autoCreate($host, $port, $ssl = false)
    {
        if (class_exists('\\swoole_server', false))
        {
            return new self($host, $port, $ssl);
        }
        elseif (function_exists('event_base_new'))
        {
            return new EventTCP($host, $port, $ssl);
        }
        else
        {
            return new SelectTCP($host, $port, $ssl);
        }
    }

    /**
     * @param $protocol
     * @throws \Exception
     */
    function setProtocol($protocol)
    {
        if (self::$useSwooleHttpServer)
        {
            $this->protocol = $protocol;
        }
    }

    function run($setting = array())
    {
        $this->runtimeSetting = array_merge($this->runtimeSetting, $setting);
        if (self::$pidFile)
        {
            $this->runtimeSetting['pid_file'] = self::$pidFile;
        }
        $this->sw->set($this->runtimeSetting);
        $this->sw->on('ManagerStart', array($this, 'onManagerStart'));
        $this->sw->on('Start', array($this, 'onMasterStart'));
        $this->sw->on('Shutdown', array($this, 'onMasterStop'));
        $this->sw->on('ManagerStop', array($this, 'onManagerStop'));
        $this->sw->on('WorkerStart', array($this, 'onWorkerStart'));

        if (is_callable(array($this->protocol, 'onConnect')))
        {
            $this->sw->on('Connect', array($this->protocol, 'onConnect'));
        }
        if (is_callable(array($this->protocol, 'onClose')))
        {
            $this->sw->on('Close', array($this->protocol, 'onClose'));
        }
        if (self::$useSwooleHttpServer)
        {
            $this->sw->on('Request', array($this->protocol, 'onRequest'));
        }
        else
        {
            $this->sw->on('Receive', array($this->protocol, 'onReceive'));
        }
       
        self::$swoole = $this->sw;
        $this->sw->start();
    }
    /**
     * 设置进程名称
     * @param $name
     */
    function setProcessName($name)
    {
        // $this->processName = $name;

        if (function_exists('cli_set_process_title'))
        {
            cli_set_process_title($name);
        }
        else
        {
            if (function_exists('swoole_set_process_name'))
            {
                swoole_set_process_name($name);
            }
            else
            {
                trigger_error(__METHOD__ . " failed. require cli_set_process_title or swoole_set_process_name.");
            }
        }

    }

    /**
     * 杀死所有进程
     * @param $name
     * @param int $signo
     * @return string
     */
    static function killProcessByName($name, $signo = 9)
    {
        $cmd = 'ps -eaf |grep "' . $name . '" | grep -v "grep"| awk "{print $2}"|xargs kill -'.$signo;
        return exec($cmd);
    }

    /**
     * 获取进程名称
     * @return string
     */
    function getProcessName()
    {
        if (empty($this->processName))
        {
            global $argv;
            return "php {$argv[0]}";
        }
        else
        {
            return $this->processName;
        }
    }

    function daemonize()
    {
        $this->runtimeSetting['daemonize'] = 1;
    }

    function onManagerStart($serv)
    {
        // // $this->setProcessName($this->getProcessName() . ': master -host=' . $this->host . ' -port=' . $this->port);
        // if (!empty($this->runtimeSetting['pid_file']))
        // {
        //     file_put_contents(self::$pidFile, $serv->master_pid);
        // }
    }

    function onMasterStart($serv)
    {
        // $this->setProcessName($this->getProcessName() . ': master -host=' . $this->host . ' -port=' . $this->port);
        if (!empty($this->runtimeSetting['pid_file']))
        {
            file_put_contents(self::$pidFile, $serv->master_pid);
        }
    }

    function onMasterStop($serv)
    {
        if (!empty(self::$pidFile))
        {
            unlink(self::$pidFile);
        }
    }

    function onManagerStop()
    {

    }

    function onWorkerStart($serv, $worker_id)
    {
        if ($worker_id >= $serv->setting['worker_num'])
        {
            // $this->setProcessName($this->getProcessName() . ': task');
        }
        else
        {
            // $this->setProcessName($this->getProcessName() . ': worker');
        }
        if (method_exists($this->protocol, 'onStart'))
        {
            $this->protocol->onStart($serv, $worker_id);
        }
        if (method_exists($this->protocol, 'onWorkerStart'))
        {
            $this->protocol->onWorkerStart($serv, $worker_id);
        }
    }


}