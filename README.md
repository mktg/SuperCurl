# SuperCurl Parser
Based, on AngryCurl class
https://github.com/2naive/AngryCurl
The principle of work is the same.
Differences:
* AngryCurl and RollingCurl joined into one class
* Added new methods:
get_alive_proxy() - return array of alive proxy, after proxy check.
clear_proxy() - clear proxy list.
count_alive_proxy() - return number of alive proxy.
clear_proxy_notchecked() - clear base proxy list.
clear_the_parse_list() - clear the queue.
* New Param in method load_proxy_list($input, $window_size = 5, $proxy_type = 'http', $proxy_test_url = 'http://google.com', $proxy_valid_regexp = null, $proxy_invalid_regexp = null). $proxy_invalid_regexp - make the proxy check failed if regxp value exist in html code.
Example: We need to parse some data from sites, matching regular expression
```php
  require_once( AC_DIR . DIRECTORY_SEPARATOR . 'SuperCurl' . DIRECTORY_SEPARATOR . 'SuperCurl.php');
  $server_ip_adress = '127.0.0.1'; //change to yours
  $SC = new SuperCurl("search_from_google");
  $SC->__set('window_size', 200);
  $SC->init_console();
  $SC->__set("use_useragent_list", true);
  $SC->load_useragent_list('/files/useragent_list.txt');
  $SC->__set("use_proxy_list", "/files/proxy_list.txt");        
  $SC->init_console();
  
```
