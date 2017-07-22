<?php
namespace Swoole\Http;

class HttpServer
{
    /**
     * @var \swoole_http_request
     */
    public $request;

    /**
     * @var \swoole_http_response
     */
    public $response;

    public $charest = 'utf-8';
    public $expire_time = 86400;
    const DATE_FORMAT_HTTP = 'D, d-M-Y H:i:s T';

    protected $config;

    static $gzip_extname = array('js' => true, 'css' => true, 'html' => true, 'txt' => true);

    function __construct($config='')
    {
        if (!empty($config['charset']))
        {
            $this->charset = trim($config['charset']);
        }
        $this->config = $config;
    }

    /**
     * 设置Logger
     * @param $log
     */
    function setLogger($log)
    {
        $this->log = $log;
    }

    function header($k, $v)
    {
        $k = ucwords($k);
        $this->response->header($k, $v);
    }

    function status($code)
    {
        $this->response->status($code);
    }

    function response($content)
    {
        $this->finish($content);
    }

    function redirect($url, $mode = 302)
    {
        $this->response->status($mode);
        $this->response->header('Location', $url);
    }

    function finish($content = '')
    {
        // throw new Swoole\Exception\Response($content);
    }

    function getRequestBody()
    {
        return $this->request->rawContent();
    }

    function setcookie($name, $value = null, $expire = null, $path = '/', $domain = null, $secure = null, $httponly = null)
    {
        $this->response->cookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }

    /**
     * 将swoole扩展产生的请求对象数据赋值给框架的Request对象
     * @param Swoole\Request $request
     */
    function assign(\Swoole\Request $request)
    {
        if (isset($this->request->get))
        {
            $request->get = $this->request->get;
        }
        if (isset($this->request->post))
        {
            $request->post = $this->request->post;
        }
        if (isset($this->request->files))
        {
            $request->files = $this->request->files;
        }
        if (isset($this->request->cookie))
        {
            $request->cookie = $this->request->cookie;
        }
        if (isset($this->request->server))
        {
            foreach($this->request->server as $key => $value)
            {
                $request->server[strtoupper($key)] = $value;
            }
            $request->remote_ip = $this->request->server['remote_addr'];
        }
        $request->header = $this->request->header;
        // $request->setGlobal();
    }

    /**
     * 从ini文件中加载配置
     * @param $ini_file
     */
    function loadSetting($ini_file)
    {
        if (!is_file($ini_file)) exit("Swoole AppServer配置文件错误($ini_file)\n");
        $config = parse_ini_file($ini_file, true);
        /*--------------Server------------------*/
        //开启http keepalive
        if (!empty($config['server']['keepalive']))
        {
            $this->keepalive = true;
        }
        //是否压缩
        if (!empty($config['server']['gzip_open']) and function_exists('gzdeflate'))
        {
            $this->gzip = true;
            //default level
            if (empty($config['server']['gzip_level']))
            {
                $config['server']['gzip_level'] = 1;
            }
            //level [1, 9]
            elseif ($config['server']['gzip_level'] > 9)
            {
                $config['server']['gzip_level'] = 9;
            }
        }
        //过期控制
        if (!empty($config['server']['expire_open']))
        {
            $this->expire = true;
            if (empty($config['server']['expire_time']))
            {
                $config['server']['expire_time'] = 1800;
            }
        }
        /*--------------Session------------------*/
        if (empty($config['session']['cookie_life'])) $config['session']['cookie_life'] = 86400; //保存SESSION_ID的cookie存活时间
        if (empty($config['session']['session_life'])) $config['session']['session_life'] = 1800; //Session在Cache中的存活时间
        if (empty($config['session']['cache_url'])) $config['session']['cache_url'] = 'file://localhost#sess'; //Session在Cache中的存活时间
        /*--------------Apps------------------*/
        if (empty($config['apps']['url_route'])) $config['apps']['url_route'] = 'url_route_default';
        if (empty($config['apps']['auto_reload'])) $config['apps']['auto_reload'] = 0;
        if (empty($config['apps']['charset'])) $config['apps']['charset'] = 'utf-8';

        if (!empty($config['access']['post_maxsize']))
        {
            $this->config['server']['post_maxsize'] = $config['access']['post_maxsize'];
        }
        if (empty($config['server']['post_maxsize']))
        {
            $config['server']['post_maxsize'] = self::POST_MAXSIZE;
        }
        /*--------------Access------------------*/
        $this->deny_dir = array_flip(explode(',', $config['access']['deny_dir']));
        $this->static_dir = array_flip(explode(',', $config['access']['static_dir']));
        $this->static_ext = array_flip(explode(',', $config['access']['static_ext']));
        $this->dynamic_ext = array_flip(explode(',', $config['access']['dynamic_ext']));
        /*--------------document_root------------*/
        if (empty($this->document_root) and !empty($config['server']['document_root']))
        {
            $this->document_root = $config['server']['document_root'];
        }
        /*-----merge----*/
        if (!is_array($this->config))
        {
            $this->config = array();
        }
        $this->config = array_merge($this->config, $config);
    }

    function doStatic(\swoole_http_request $req, \swoole_http_response $resp)
    {
        $file = $this->document_root . $req->server['request_uri'];
        $read_file = true;
        $fstat = stat($file);

        //过期控制信息
        if (isset($req->header['if-modified-since']))
        {
            $lastModifiedSince = strtotime($req->header['if-modified-since']);
            if ($lastModifiedSince and $fstat['mtime'] <= $lastModifiedSince)
            {
                //不需要读文件了
                $read_file = false;
                $resp->status(304);
            }
        }
        else
        {
            $resp->header('Cache-Control', "max-age={$this->expire_time}");
            $resp->header('Pragma', "max-age={$this->expire_time}");
            $resp->header('Last-Modified', date(self::DATE_FORMAT_HTTP, $fstat['mtime']));
            $resp->header('Expires',  "max-age={$this->expire_time}");
        }

        if ($read_file)
        {
            $extname = Swoole\Upload::getFileExt($file);
            if (empty($this->types[$extname]))
            {
                $mime_type = 'text/html; charset='.$this->charest;
            }
            else
            {
                $mime_type = $this->types[$extname];
            }
            $resp->header('Content-Type', $mime_type);
            $resp->sendfile($file);
        }
        else
        {
            $resp->end();
        }
        return true;
    }

    // function onRequest(\swoole_http_request $request, \swoole_http_response $response)
    // {
        
    // }

}