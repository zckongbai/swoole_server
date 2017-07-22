<?php
namespace Swoole\Cache;

class Redis
{
    protected $config;
    protected $_redis;

    function __construct($config)
    {
        if (empty($config['redis_id']))
        {
            $config['redis_id'] = 'master';
        }
        $this->config = $config;
        $this->log = new \Swoole\Log\FileLog($this->config['error_log']);
        $this->connect();
    }

    function connect()
    {
        try
        {
            if ($this->_redis)
            {
                unset($this->_redis);
            }
            $this->_redis = new \Redis();
            if ($this->config['pconnect'])
            {
                $this->_redis->pconnect($this->config['host'], $this->config['port'], $this->config['timeout']);
            }
            else
            {
                $this->_redis->connect($this->config['host'], $this->config['port'], $this->config['timeout']);
            }
            
            if (!empty($this->config['password']))
            {
                $this->_redis->auth($this->config['password']);
            }
            if (!empty($this->config['database']))
            {
                $this->_redis->select($this->config['database']);
            }
        }
        catch (\RedisException $e)
        {
            $this->log->put(__CLASS__ . " Swoole Redis Exception" . var_export($e, 1));
            return false;
        }
    }


    function __call($method, $args = array())
    {
        $reConnect = false;
        $reTry = false;
        while (1)
        {
            try
            {
                $result = call_user_func_array(array($this->_redis, $method), $args);
            }
            catch (\RedisException $e)
            {
                // 先重试 再重连
                
                if (!$reTry)
                {
                    $result = call_user_func_array(array($this->_redis, $method), $args);
                    $reTry = true;
                    if ($result)
                        return $result;
                }
                $this->log->put(__CLASS__ . " Swoole Redis Exception:[retry]" . var_export($e, 1));

                //已重连过，仍然报错
                if ($reConnect)
                {
                    throw $e;
                }

                $this->log->put(__CLASS__ . " [" . posix_getpid() . "] Swoole Redis[{$this->config['host']}:{$this->config['port']}]
                //  Exception(Msg=" . $e->getMessage() . ", Code=" . $e->getCode() . "), Redis->{$method}, Params=" . var_export($args, 1));

                if ($this->_redis->isConnected())
                {
                    $this->_redis->close();
                }
                $this->connect();
                $reConnect = true;
                continue;
            }
            return $result;
        }
        //不可能到这里
        return false;
    }
}
