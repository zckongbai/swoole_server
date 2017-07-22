<?php
// define('DEBUG', 'on');
define('SERVERTOOT', realpath(__DIR__));
define('CONFIGPATH', SERVERTOOT . '/Config');
define('LOGPATH', SERVERTOOT . '/Log');

require_once SERVERTOOT . '/Loader.php';

$config = require_once CONFIGPATH .'/server.php';

//设置PID文件的存储路径
Swoole\Server\Server::setPidFile(__DIR__ . '/http_server.pid');
/**
 * 显示Usage界面
 * php http.php start|stop|reload
 */
Swoole\Server\Server::start(function () use($config)
{
    // $AppSvr = new Swoole\Http\HttpServer();
    $AppSvr = new Swoole\Handler($config);
    // $AppSvr->loadSetting(__DIR__.'/swoole.ini'); //加载配置文件
    // $AppSvr->setDocumentRoot(__DIR__.'/webroot');
    $AppSvr->setLogger(new Swoole\Log\FileLog(LOGPATH . '/http.log')); //Logger

    $server = Swoole\Server\Server::autoCreate('127.0.0.1', 8888);
    $server->setProtocol($AppSvr);
    // $server->daemonize(); //作为守护进程
    $server->run(array('worker_num' => 0, 'max_request' => 2000, 'log_file' => LOGPATH . '/server.log'));
});
