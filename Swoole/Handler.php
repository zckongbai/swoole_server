<?php
namespace Swoole;

class Handler extends \Swoole\Http\HttpServer
{
    public $http_protocol = 'HTTP/1.1';
    public $http_status = 200;

    public $head;
    public $cookie;
    public $body;

    public $request;
    public $response;

    static $HTTP_HEADERS = array(
        100 => "100 Continue",
        101 => "101 Switching Protocols",
        200 => "200 OK",
        201 => "201 Created",
        204 => "204 No Content",
        206 => "206 Partial Content",
        300 => "300 Multiple Choices",
        301 => "301 Moved Permanently",
        302 => "302 Found",
        303 => "303 See Other",
        304 => "304 Not Modified",
        307 => "307 Temporary Redirect",
        400 => "400 Bad Request",
        401 => "401 Unauthorized",
        403 => "403 Forbidden",
        404 => "404 Not Found",
        405 => "405 Method Not Allowed",
        406 => "406 Not Acceptable",
        408 => "408 Request Timeout",
        410 => "410 Gone",
        413 => "413 Request Entity Too Large",
        414 => "414 Request URI Too Long",
        415 => "415 Unsupported Media Type",
        416 => "416 Requested Range Not Satisfiable",
        417 => "417 Expectation Failed",
        500 => "500 Internal Server Error",
        501 => "501 Method Not Implemented",
        503 => "503 Service Unavailable",
        506 => "506 Variant Also Negotiates",
    );

    function __construct($config)
    {
    	parent::__construct($config['server']);
    	$this->redis = new \Swoole\Cache\Redis($config['redis']);
    }

    function onRequest(\swoole_http_request $request, \swoole_http_response $response)
    {
    	$this->request = $request;
    	$this->response = $response;
    	// $this->log->put(var_export($request,true), 'INFO');
    	switch ($request->server['request_uri']) {
    		case '/goods/buy':
    			$get = $request->get;
    			$this->log->put(json_encode($get));
 				$good_exc = $this->redis->hget('seckill_goods_id', $get['id']);
 				if (!$good_exc)
 				{
 					$this->setHttpStatus(404);
 					$this->redirect('www.baidu.com');
 					return ;
 				}
				$this->setHttpStatus(302);
 				$good = $this->redis->hGetAll('goods:'.$get['id']);
 				$queue_size = $good['sell_number'] * 50; 

 				$queue_key = "site_queue_goods_id:" . $get['id'];
 				$queue = $this->redis->get($queue_key);
 				if(!$queue || $queue <= $queue_size) 
 				{
 					$this->log->put("allow");
 					$this->redis->incr($queue_key);
 					$this->response->end('allow');
 					// $this->redirect('放行');
 				}
 				else
 				{
 					$this->log->put("deny");
 					// $this->redirect('卖完了');	
 					$this->response->end('deny');
 				}

    			break;
    		
    		default:
    			# code...
    			break;
    	}

    }

    /**
     * 设置Http状态
     * @param $code
     */
    function setHttpStatus($code)
    {
        $this->head[0] = $this->http_protocol.' '.self::$HTTP_HEADERS[$code];
        $this->http_status = $code;
        $this->status($code);
    }

    /**
     * 设置Http头信息
     * @param $key
     * @param $value
     */
    function setHeader($key,$value)
    {
        $this->head[$key] = $value;
    }

    /**
     * 跳转网址
     * @param $url
     */
    public function redirect($url, $mode = 302)
    {
        // \Swoole::$php->http->redirect($url, $mode);
    }


  	/**
     * 获取客户端IP
     * @return string
     */
    function getClientIP()
    {
        if (isset($this->server["HTTP_X_REAL_IP"]) and strcasecmp($this->server["HTTP_X_REAL_IP"], "unknown"))
        {
            return $this->server["HTTP_X_REAL_IP"];
        }
        if (isset($this->server["HTTP_CLIENT_IP"]) and strcasecmp($this->server["HTTP_CLIENT_IP"], "unknown"))
        {
            return $this->server["HTTP_CLIENT_IP"];
        }
        if (isset($this->server["HTTP_X_FORWARDED_FOR"]) and strcasecmp($this->server["HTTP_X_FORWARDED_FOR"], "unknown"))
        {
            return $this->server["HTTP_X_FORWARDED_FOR"];
        }
        if (isset($this->server["REMOTE_ADDR"]))
        {
            return $this->server["REMOTE_ADDR"];
        }
        return "";
    }

}