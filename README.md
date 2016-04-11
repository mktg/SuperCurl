# SuperCurl Parser
Based, on AngryCurl class
https://github.com/2naive/AngryCurl
The principle of work is the same.
Differences:

* AngryCurl and RollingCurl joined into one class
* 
* Added new methods:
get_alive_proxy() - return array of alive proxy, after proxy check.
clear_proxy() - clear proxy list.
count_alive_proxy() - return number of alive proxy.
clear_proxy_notchecked() - clear base proxy list.
clear_the_parse_list() - clear the queue.

* New Param in method load_proxy_list($input, $window_size = 5, $proxy_type = 'http', $proxy_test_url = 'http://google.com', $proxy_valid_regexp = null, $proxy_invalid_regexp = null). $proxy_invalid_regexp - make the proxy check failed if regxp value exist in html code.
* 
Example: We need to parse some data from sites, matching regular expression

```php
  require_once( AC_DIR . DIRECTORY_SEPARATOR . 'SuperCurl' . DIRECTORY_SEPARATOR . 'SuperCurl.php');
  $server_ip_adress = '127.0.0.1'; //change to yours
  $SC = new SuperCurl("search_from_google");
  $SC->__set('window_size', 200);
  $SC->init_console();
  $SC->__set("use_useragent_list", true);
  $SC->load_useragent_list('/files/useragent_list.txt');
  $SC->__set("use_proxy_list", true);        
  $SC->init_console();
  $SC->load_proxy_list("/files/proxy_list.txt", 1023, 'http', 'http://2ip.ru/', '2ip.ru', $server_ip_adress);
  //check work and anonimity       
  //there must be title "2ip.ru", but not the 'server ip adress'
  foreach($parse_list as $key=>$prsr_url){        
          $params=array(CURLOPT_REFERER=>"http://".parse_url($prsr_url, PHP_URL_HOST), CURLOPT_COOKIE=>"referrer=http://".parse_url($prsr_url, PHP_URL_HOST));//refferer must be the same as site
          for($t=0; $t<100; $t++){
              $SC->request($prsr_url, "POST", null, null, $params, $key);        
          }
      }
      $SC->execute(100);
      $failed_count++;  
      
  }        
}
```
