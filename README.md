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
