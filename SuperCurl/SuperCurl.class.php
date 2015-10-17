<?php
class SuperCurlRequest {
    public $url = false;
    public $method = 'GET';
    public $post_data = null;
    public $headers = null;
    public $options = null;
    public $id      = null;
    /**
     * @param string $url
     * @param string $method
     * @param  $post_data
     * @param  $headers
     * @param  $options
     * @return void
     */
    function __construct($url, $method = "GET", $post_data = null, $headers = null, $options = null, $id=null) {
        $this->url = $url;
        $this->method = $method;
        $this->post_data = $post_data;
        $this->headers = $headers;
        $this->options = $options;
	$this->id = $id;
    }

    /**
     * @return void
     */
    public function __destruct() {
        unset($this->url, $this->method, $this->post_data, $this->headers, $this->options,$this->id);
    }   
}

class SuperCurl{ 
/**
     * @var int
     *
     * Window size is the max number of simultaneous connections allowed.
     *
     * REMEMBER TO RESPECT THE SERVERS:
     * Sending too many requests at one time can easily be perceived
     * as a DOS attack. Increase this window_size if you are making requests
     * to multiple servers or have permission from the receving server admins.
     */
 public static $debug_info       =   array();
    public static $debug_log        =   false;
    protected static $console_mode  =   false;
    
    
    protected static $array_alive_proxy=array();
    protected $array_proxy          =   array();
    protected $array_url            =   array();
    protected $array_useragent      =   array();
    
    protected $error_limit          =   0; // not implemented yet
    protected $array_valid_http_code=   array(200); // not implemented yet
    
    protected $n_proxy              =   0;
    protected $n_useragent          =   0;
    protected $n_url                =   0;
    
    protected $proxy_test_url       =   'http://google.com';
    protected static $proxy_valid_regexp   =   '';
    protected static $proxy_invalid_regexp   =   '';
    
    private $use_proxy_list       =   false;
    private $use_useragent_list   =   false;
    private $window_size = 5;

    /**
     * @var float
     *
     * Timeout is the timeout used for curl_multi_select.
     */
    private $timeout = 10;

    /**
     * @var string|array
     *
     * Callback function to be applied to each result.
     */
     private $callback;
    /**
     * @var array
     *
     * Set your base options that you want to be used with EVERY request.
     */
    protected $options = array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT => 30
    );

    /**
     * @var array
     */
    private $headers = array();

    /**
     * @var Request[]
     *
     * The request queue
     */
    private $requests = array();

    /**
     * @var RequestMap[]
     *
     * Maps handles to request indexes
     */
    private $requestMap = array();
 /**
     * @param  $callback
     * Callback function to be applied to each result.
     *
     * Can be specified as 'my_callback_function'
     * or array($object, 'my_callback_method').
     *
     * Function should take three parameters: $response, $info, $request.
     * $response is response body, $info is additional curl info.
     * $request is the original request
     *
     * @return void
     */
    function __construct($callback = null, $debug_log = false)
    {
        self::$debug_log = $debug_log;
        
        # writing debug
      //  self::add_debug_msg("# Building"); #1
        
        # checking if cURL enabled
       /* if(!function_exists('curl_init')) #2
        {
            throw new SuperCurlException("(!) cURL is not enabled");
        }*/
        
        $this->callback = $callback;
	
    } 
  /**
     * Initializing console mode
     *
     * @return void
     */   
    public function init_console()
    {
        self::$console_mode = true;
        
        echo "<pre>";
        
        # Internal Server Error fix in case no apache_setenv() function exists
        if (function_exists('apache_setenv'))
        {
            @apache_setenv('no-gzip', 1);
        }
        @ini_set('zlib.output_compression', 0);
        @ini_set('implicit_flush', 1);
        for ($i = 0; $i < ob_get_level(); $i++)
            ob_end_flush();
        ob_implicit_flush(1);
        
        # writing debug
        self::add_debug_msg("# Console mode activated");
    }
    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        return (isset($this->{$name})) ? $this->{$name} : null;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return bool
     */
    public function __set($name, $value) {
        // append the base options & headers
        if ($name == "options" || $name == "headers") {
            $this->{$name} = $value + $this->{$name};
        } else {
            $this->{$name} = $value;
        }
        return true;
    }

    /**
     * Add a request to the request queue
     *
     * @param Request $request
     * @return bool
     */
    public function add($request) {
        $this->requests[] = $request;
        return true;
    }
 /**
     * Request execution overload
     *
     * @access public
     *
     * @throws SuperCurlException
     * 
     * @param string $url Request URL
     * @param enum(GET/POST) $method
     * @param array $post_data
     * @param array $headers
     * @param array $options
     * 
     * @return bool
     */
 /**
     * Create new Request and add it to the request queue
     *
     * @param string $url
     * @param string $method
     * @param  $post_data
     * @param  $headers
     * @param  $options
     * @return bool
     */
    public function request($url, $method = "GET", $post_data = null, $headers = null, $options = null, $id=null)
    {
        if($this->n_proxy > 0 && $this->use_proxy_list)
        {
            $options[CURLOPT_PROXY]=$this->array_proxy[ mt_rand(0, $this->n_proxy-1) ];
        //    self::add_debug_msg("Using PROXY({$this->n_proxy}): ".$options[CURLOPT_PROXY]);
        }
        elseif($this->n_proxy < 1 && $this->use_proxy_list)
        {
            throw new SuperCurlException("(!) Option 'use_proxy_list' is set, but no alive proxy available");
        }
        
        if($this->n_useragent > 0 && $this->use_useragent_list)
        {
            $options[CURLOPT_USERAGENT]=$this->array_useragent[ mt_rand(0, $this->n_useragent-1) ];
        //    self::add_debug_msg("Using USERAGENT: ".$options[CURLOPT_USERAGENT]);
        }
        elseif($this->n_useragent < 1 && $this->use_useragent_list)
        {
            throw new SuperCurlException("(!) Option 'use_useragent_list' is set, but no useragents available");
        }
        $this->requests[] = new SuperCurlRequest($url, $method, $post_data, $headers, $options, $id);
        return true;
    }

    /**
     * Perform GET request
     *
     * @param string $url
     * @param  $headers
     * @param  $options
     * @return bool
     */
    public function get($url, $headers = null, $options = null) {
        return $this->request($url, "GET", null, $headers, $options);
    }

    /**
     * Perform POST request
     *
     * @param string $url
     * @param  $post_data
     * @param  $headers
     * @param  $options
     * @return bool
     */
    public function post($url, $post_data = null, $headers = null, $options = null) {
        return $this->request($url, "POST", $post_data, $headers, $options);
    }

    /**
     * Execute processing
     *
     * @param int $window_size Max number of simultaneous connections
     * @return string|bool
     */
 /**
     * Starting connections function execution overload
     *
     * @access public
     *
     * @throws SuperCurlException
     *
     * @param int $window_size Max number of simultaneous connections
     *
     * @return string|bool
     */
    public function execute($window_size = null)
    {
        # checking $window_size var
        if($window_size == null)
        {
            self::add_debug_msg(" (!) Default threads amount value (5) is used");
        }
        elseif($window_size > 0 && is_int($window_size))
        {
            self::add_debug_msg(" * Threads set to:\t$window_size");
        }
        else
        {
            throw new SuperCurlException(" (!) Wrong threads amount in execute():\t$window_size");
        }
        
        # writing debug
        self::add_debug_msg(" * Starting connections");
        //var_dump($this->__get('requests'));
        
        $time_start = microtime(1);
        // rolling curl window must always be greater than 1
        if (sizeof($this->requests) == 1) {
           $result = $this->single_curl();
        } else {
            // start the rolling curl. window_size is the max number of simultaneous connections
           $result = $this->rolling_curl($window_size);
        }
        $time_end = microtime(1);
        
        # writing debug
        self::add_debug_msg(" * Finished in ".round($time_end-$time_start,2)."s");
        
        return $result;
    }
     /**
     * Flushing requests map for re-using purposes
     *
     * @return void
     */
    public function flush_requests()
    {
        $this->__set('requests', array());
    }
  /**
     * Useragent list loading method
     *
     * @access public
     * 
     * @param string/array $input Input proxy data, could be an array or filename
     * @return integer Amount of useragents loaded
     */
    public function load_useragent_list($input)
    {
        # writing debug
        self::add_debug_msg("# Start loading useragent list");
        
        # defining proxiess
        if(is_array($input))
        {
            $this->array_useragent = $input;
        }
        else
        {        
            $this->array_useragent = $this->load_from_file($input);
        }
        
        # setting amount
        $this->n_useragent = count($this->array_useragent);
        
        # writing debug
        if($this->n_useragent > 0)
        {
            self::add_debug_msg("# Loaded useragents:\t{$this->n_useragent}");
        }
        else
        {
            throw new SuperCurlException("# (!) No useragents loaded");
        }
        
        # Setting flag to prevent using SuperCurl without useragents
        $this->use_useragent_list = true;
        
        return $this->n_useragent;
    }
    /**
     * Performs a single curl request
     *
     * @access private
     * @return string
     */
    private function single_curl() {
        $ch = curl_init();
        $request = array_shift($this->requests);
        $options = $this->get_options($request);
        curl_setopt_array($ch, $options);
        $output = curl_exec($ch);
        $info = curl_getinfo($ch);

        // it's not neccesary to set a callback for one-off requests
        if ($this->callback) {
            $callback = $this->callback;	    
            if (is_callable($this->callback)) {
                call_user_func($callback, $output, $info, $request);
            }
        }
        else
            return $output;
        return true;
    }

    /**
     * Performs multiple curl requests
     *
     * @access private
     * @throws SuperCurlException
     * @param int $window_size Max number of simultaneous connections
     * @return bool
     */
    private function rolling_curl($window_size = null) {
        if ($window_size)
            $this->window_size = $window_size;

        // make sure the rolling window isn't greater than the # of urls
        if (sizeof($this->requests) < $this->window_size)
            $this->window_size = sizeof($this->requests);

        if ($this->window_size < 2) {
            throw new SuperCurlException("Window size must be greater than 1");
        }

        $master = curl_multi_init();

        // start the first batch of requests
        for ($i = 0; $i < $this->window_size; $i++) {
            $ch = curl_init();

            $options = $this->get_options($this->requests[$i]);

            curl_setopt_array($ch, $options);
            curl_multi_add_handle($master, $ch);

            // Add to our request Maps
            $key = (string) $ch;
            $this->requestMap[$key] = $i;
        }

        do {
            while (($execrun = curl_multi_exec($master, $running)) == CURLM_CALL_MULTI_PERFORM) ;
            if ($execrun != CURLM_OK)
                break;
            // a request was just completed -- find out which one
            while ($done = curl_multi_info_read($master)) {

                // get the info and content returned on the request
                $info = curl_getinfo($done['handle']);
                $output = curl_multi_getcontent($done['handle']);

                // send the return values to the callback function.
                $callback = $this->callback;
                if (is_callable($callback)) {
                    $key = (string) $done['handle'];
                    $request = $this->requests[$this->requestMap[$key]];
                    unset($this->requestMap[$key]);
                    call_user_func($callback, $output, $info, $request);
                }

                // start a new request (it's important to do this before removing the old one)
                if ($i < sizeof($this->requests) && isset($this->requests[$i]) && $i < count($this->requests)) {
                    $ch = curl_init();
                    $options = $this->get_options($this->requests[$i]);
                    curl_setopt_array($ch, $options);
                    curl_multi_add_handle($master, $ch);

                    // Add to our request Maps
                    $key = (string) $ch;
                    $this->requestMap[$key] = $i;
                    $i++;
                }

                // remove the curl handle that just completed
                curl_multi_remove_handle($master, $done['handle']);

            }

            // Block for data in / output; error handling is done by curl_multi_exec
            if ($running)
                curl_multi_select($master, $this->timeout);

        } while ($running);
        curl_multi_close($master);
        return true;
    }


    /**
     * Helper function to set up a new request by setting the appropriate options
     *
     * @access private
     * @param Request $request
     * @return array
     */
    private function get_options($request) {
        // options for this entire curl object
        $options = $this->__get('options');
        if (ini_get('safe_mode') == 'Off' || !ini_get('safe_mode')) {
            $options[CURLOPT_FOLLOWLOCATION] = 1;
            $options[CURLOPT_MAXREDIRS] = 5;
        }
        $headers = $this->__get('headers');

        // append custom options for this specific request
        if ($request->options) {
            $options = $request->options + $options;
        }

        // set the request URL
        $options[CURLOPT_URL] = $request->url;

        // posting data w/ this request?
        if ($request->post_data) {
            $options[CURLOPT_POST] = 1;
            $options[CURLOPT_POSTFIELDS] = $request->post_data;
        }
        if ($headers) {
            $options[CURLOPT_HEADER] = 0;
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        return $options;
    }
   /**
     * Proxy list loading and filtering method
     *
     * @access public
     *
     * @throws SuperCurlException
     * 
     * @param string/array $input Input proxy data, could be an array or filename
     * @param integer $window_size Max number of simultaneous connections when testing
     * @param enum(http/socks5) $proxy_type
     * @param string $proxy_test_url URL needed for proxy test requests
     * @param regexp $proxy_valid_regexp Regexp needed to be shure that response hasn`t been modified by proxy
     * 
     * @return bool
     */
    public function load_proxy_list($input, $window_size = 5, $proxy_type = 'http', $proxy_test_url = 'http://google.com', $proxy_valid_regexp = null, $proxy_invalid_regexp = null)
    {
        # writing debug
        self::add_debug_msg("# Start loading proxies");
        
        # defining proxiess
        if(is_array($input))
        {
            $this->array_proxy = $input;
        }
        else
        {
            $this->array_proxy = $this->load_from_file($input);
        }        
        
        # checking $window_size var
        if( intval($window_size) < 1 || !is_int($window_size) )
        {
            throw new SuperCurlException(" (!) Wrong threads amount in load_proxy_list():\t$window_size");
        }

        
        # setting proxy type
        if($proxy_type == 'socks5')
        {
            self::add_debug_msg(" * Proxy type set to:\tSOCKS5");
            $this->__set('options', array(CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5));
        }
        else
        {
            self::add_debug_msg(" * Proxy type set to:\tHTTP");
        }
            
        # setting amount
        $this->n_proxy = count($this->array_proxy);
        self::add_debug_msg(" * Loaded proxies:\t{$this->n_proxy}");
        
        # filtering alive proxies
        if($this->n_proxy>0)
        {
            # removing duplicates
            $n_dup = count($this->array_proxy);
            # by array_values bug was fixed in random array indexes using mt_rand in request()
            $this->array_proxy = array_values( array_unique( $this->array_proxy) );
            $n_dup -= count($this->array_proxy);
            
            self::add_debug_msg(" * Removed duplicates:\t{$n_dup}");
            unset($n_dup);
            
            # updating amount
            $this->n_proxy = count($this->array_proxy);
            self::add_debug_msg(" * Unique proxies:\t{$this->n_proxy}");
            
            # setting url for testing proxies
            $this->proxy_test_url = $proxy_test_url;
            self::add_debug_msg(" * Proxy test URL:\t{$this->proxy_test_url}");
            
            # setting regexp for testing proxies
            if( !empty($proxy_valid_regexp) )
            {
                self::$proxy_valid_regexp = $proxy_valid_regexp;
                self::add_debug_msg(" * Proxy test RegExp:\t".self::$proxy_valid_regexp);
            }
            if( !empty($proxy_invalid_regexp) )
            {
                self::$proxy_invalid_regexp = $proxy_invalid_regexp;
                self::add_debug_msg(" * Proxy test NOT HAVE RegExp:\t".self::$proxy_invalid_regexp);
            }
            
            $this->filter_alive_proxy($window_size); 
        }
        else
        {
            throw new SuperCurlException(" (!) Proxies amount < 0 in load_proxy_list():\t{$this->n_proxy}");
        }
        
        # Setting flag to prevent using SuperCurl without proxies
        $this->use_proxy_list = true;   
    }
    /**
     * Filtering proxy array method, choosing alive proxy only
     *
     * @return void
     */
    public static function callback_proxy_check($response, $info, $request)
    {
        static $rid = 0;
        $rid++;
    
        if($info['http_code']!==200)
        {
            self::add_debug_msg("   $rid->\t".$request->options[CURLOPT_PROXY]."\tFAILED\t".$info['http_code']."\t".$info['total_time']."\t".$info['url']);
            return;
        }

        if(!empty(self::$proxy_valid_regexp) && !@preg_match('#'.self::$proxy_valid_regexp.'#', $response) )
        {
            self::add_debug_msg("   $rid->\t".$request->options[CURLOPT_PROXY]."\tFAILED\tRegExp match:\t".self::$proxy_valid_regexp."\t".$info['url']);
            return;
        }
        if(!empty(self::$proxy_invalid_regexp) && @preg_match('#'.self::$proxy_invalid_regexp.'#', $response) )
        {
            self::add_debug_msg("   $rid->\t".$request->options[CURLOPT_PROXY]."\tFAILED\tRegExp match:\t".self::$proxy_invalid_regexp."\t".$info['url']);
            return;
        }
            self::add_debug_msg("   $rid->\t".$request->options[CURLOPT_PROXY]."\tOK\t".$info['http_code']."\t".$info['total_time']."\t".$info['url']);
            self::$array_alive_proxy[] = $request->options[CURLOPT_PROXY];
    }
   /**
     * Filtering proxy array, choosing alive proxy only
     *
     * @throws SuperCurlException
     *
     * @param integer $window_size Max number of simultaneous connections when testing
     *
     * @return void
     */
    protected function filter_alive_proxy($window_size = 5)
    {
        # writing debug
        self::add_debug_msg("# Start testing proxies");
        
        # checking $window_size var
        if( intval($window_size) < 1 || !is_int($window_size) )
        {
            throw new SuperCurlException(" (!) Wrong threads amount in filter_alive_proxy():\t$window_size");
        }
        
        $buff_callback_func = $this->__get('callback');
        $this->__set('callback',array('SuperCurl', 'callback_proxy_check'));

        # adding requests to stack
        foreach($this->array_proxy as $id => $proxy)
        {
            # there won't be any regexp checks, just this :)
            if( strlen($proxy) > 4)
                $this->request($this->proxy_test_url, $method = "GET", null, null, array(CURLOPT_PROXY => $proxy) );
        }

        # run
        $this->execute($window_size);
        
        #flushing requests
        $this->__set('requests', array());

        # writing debug
        self::add_debug_msg("# Alive proxies:\t".count(self::$array_alive_proxy)."/".$this->n_proxy);
        
        # updating params
        $this->n_proxy = count(self::$array_alive_proxy);
        $this->array_proxy = self::$array_alive_proxy;
        $this->__set('callback', $buff_callback_func);
    }
  /**
     * Loading info from external files
     *
     * @access private
     * @param string $filename
     * @param string $delim
     * @return array
     */
    protected function load_from_file($filename, $delim = "\n")
    {
        $fp = @fopen($filename, "r");
        
        if(!$fp)
        {
            self::add_debug_msg("(!) Failed to open file: $filename");
            return array();
        }
        
        $data = @fread($fp, filesize($filename) );
        fclose($fp);
        
        if(strlen($data)<1)
        {
            self::add_debug_msg("(!) Empty file: $filename");
            return array();
        }
        
        $array = explode($delim, $data);
        
        if(is_array($array) && count($array)>0)
        {
            foreach($array as $k => $v)
            {
                if(strlen( trim($v) ) > 0)
                    $array[$k] = trim($v);
            }
            return $array;
        }
        else
        {
            self::add_debug_msg("(!) Empty data array in file: $filename");
            return array();
        }
    }
    
    /**
     * Printing debug information method
     *
     * @access public
     * @return void
     */
    public static function print_debug()
    {
        echo "<pre>";
        echo htmlspecialchars( implode("\n", self::$debug_info) );
        echo "</pre>";
    }
     /**
     * Logging method
     *
     * @access public
     * @param string $msg message
     * @return void
     */
    public static function add_debug_msg($msg)
    {
        if(self::$debug_log)
        {
            self::$debug_info[] = $msg;
        }
        
        if(self::$console_mode)
        {
            echo htmlspecialchars($msg)."\r\n";
        }
    }
	
	public function get_alive_proxy()
    {
		return self::$array_alive_proxy;
	}
	public function clear_proxy()
    {
		self::$array_alive_proxy = array(null);
        $this->array_proxy = array();
        $this->n_proxy=0;
	}
    public function count_alive_proxy()
    {
		return count(self::$array_alive_proxy);        
	}
    public function clear_proxy_notchecked()
    {
        $this->array_proxy = array();
	}
	public function clear_the_parse_list()
    {	
		$this->requests = array();		
		$this->n_url=0;
		
	}
    /**
     * @return void
     */
    public function __destruct() {
	 self::add_debug_msg("# Finishing ...");
        unset($this->window_size, $this->callback, $this->options, $this->headers, $this->requests);
    }

}
/**
 * SuperCurl custom exception
 */
class SuperCurlException extends Exception
{
    public function __construct($message = "", $code = 0 /*For PHP < 5.3 compatibility omitted: , Exception $previous = null*/)
    {
        SuperCurl::add_debug_msg($message);
        parent::__construct($message, $code);
    }
}
