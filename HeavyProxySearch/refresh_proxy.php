<?php
    global $proxy_list;
    $proxy_list = array();
    require_once('../SuperCurl/SuperCurl.class.php');
	//********************************************************************
	ini_set('max_execution_time','0');
	//********************************************************************
    $SC = new SuperCurl("analiz_parsed_proxy");
    $SC->__set('window_size', 5);
    $SC->init_console(true);
    $SC->__set("use_useragent_list", true);
    $SC->load_useragent_list('files/useragent_list.txt');
    $SC->__set("use_proxy_list", false);    
    $proxy_list_urls_file = file_get_contents(PROXY_LIST_URLS_FILE);  
    $proxy_list_urls = explode("\n", $proxy_list_urls_file);
    if(!empty($proxy_list_urls)){
        foreach($proxy_list_urls as $key=>$url){
            $params=array(CURLOPT_REFERER=>$url, CURLOPT_COOKIE=>"referrer=".$url);//refferer must be the same as site
            $SC->request($url, "POST", null, null, null, $params, $key);
        }
        $SC->execute(5);
    }
    $SC->clear_proxy();
    $SC->clear_the_parse_list();
    unset($SC);
    if(!empty($proxy_list)) {
    //NOW WE NEED TO CHECK PROXY IS WORKS
        $SC = new SuperCurl();
        $SC->__set('window_size', 200);
        $SC->init_console(true);
        $SC->__set("use_useragent_list", true);
        $SC->load_useragent_list('files/useragent_list.txt');
        $SC->__set("use_proxy_list", true);
        $SC->load_proxy_list($proxy_list, 200, 'http', 'http://ip-check.info/?lang=en', '<title>IP');        
        $SC->execute(200);
        $proxy_list = $SC->get_alive_proxy();
        $SC->clear_proxy();
        $SC->clear_the_parse_list();
        unset($SC);
    }
    if(!empty($proxy_list)) {
        $fname= 'files/proxy_list.txt';
        $fp2=fopen($fname,'w');
        ftruncate($fp2, 0);
        $proxystring = implode("\n", $proxy_list);
        fwrite($fp2, $proxystring);
        fclose($fp2);
    }


	echo 'Proxy list count:<br/>'.count($proxy_list).'<br>';
	echo 'Finish: '.date('Y-m-d H:i:s').'<br>';

function analiz_parsed_proxy($response, $info, $request){
    global $proxy_list;
    if($info['http_code']<200 || $info['http_code']>300)
        SuperCurl::add_debug_msg("->t\tFAILED\t".$info['http_code']."\t".$info['total_time']."\t".$info['url']);
    else{        
        $temp = get_proxy_from_html($response);
        SuperCurl::add_debug_msg("->t\tSUCCESS\t".$info['http_code']."\t".$info['total_time']."\t".$info['url']);
        if(is_array($temp)){
            //SuperCurl::add_debug_msg("->t\tWE GOT\t".count($temp)." PROXY");     
            $proxy_list = array_merge($proxy_list, $temp);
        }
    } 
}
function get_proxy_from_html($txt){
    $regexpipport="/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\:\d{1,5}/";	
    preg_match_all($regexpipport, $txt, $proxys);    
    $proxy = $proxys[0];
    return check_array_proxy($proxy);
}

function check_array_proxy($arr){	
    $temp = array();
    if(is_array($arr)){
        foreach($arr as $a){	
            if(is_array($a)){
                $temp = array_merge($temp, check_array_proxy($a));
            }
            else {
                $temp[] = $a;
            }
        }
    }
    else {
        $temp[] = $arr;
    }
    return $temp;
}