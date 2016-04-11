<?php
define('PROXY_LIST_URLS_FILE', 'files/proxy_list_urls.txt');
define('URLSETTINGS_FILE', 'files/urllist.txt');
define('PROXY_FILE', 'files/proxy_list.txt');
define('AGENT_FILE', 'files/useragent_list.txt');
define('SEARCH_URL', 'https://www.google.com/search?gws_rd=ssl&q=');
define('USE_PROXY', true);
define('ADMIN_EMAIL', 'vadim-job-hg@yandex.ru');
global $PROXY_USE;
global $LOGTEXT;
global $URL_ARRAY;
global $parse_list;
global $result;
global $parse_links;
global $parse_links_list;
ini_set('display_errors', 1);
error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE);

require_once('SuperCurl.class.php');
require_once('phpQuery.php');
require_once 'htmltodocx/phpword/PHPWord.php';
require_once 'htmltodocx/simplehtmldom/simple_html_dom.php';
require_once 'htmltodocx/htmltodocx_converter/h2d_htmlconverter.php';
require_once 'htmltodocx/htmltodocx_converter/styles.inc';
if(!flock($fp, LOCK_EX | LOCK_NB)) echo ('Refresh proxy list already running');
else {
    global $proxy_list;
    $proxy_list = array();
    require_once('./SuperCurl.class/SuperCurl.class.php');
	ftruncate($fp,0);
	$str = date("m/d/Y h:i:s")." import daemon started";
	fputs($fp, $str);
	//********************************************************************
	ini_set('max_execution_time','0');
	//********************************************************************
    $SC = new SuperCurl("analiz_parsed_proxy");
    $SC->__set('window_size', 200);
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
        $SC->execute(200);
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


	//********************************************************************

    $PROXY_USE = array();
    $result = array();
    $parse_links = array();
    $parse_links_list = array();
    $searches = array('proxy','proxy anonimus', 'proxy list', 'blogspot proxy', 'proxy '.date('Y-m-d'), '+:8080 +:3127 +:80', 'elite proxy', 'proxy list', 'proxy heaven', 'http proxy');
    $k=0;
	for ($is = 0; $is < count($searches); $is++)
	{
		$phrs = trim($searches[$is]);
		if (!empty($phrs))
		{            
            for($i=0; $i<10; $i+=10) {
                $url = SEARCH_URL.urlencode($phrs);
                if ($i >= 10) $url .= '&start='.$i;
                $parse_list[] = $url;
                $k++;
                usleep(1700);
            }                
			
        }
    }
    if (USE_PROXY)
    {        
        assignProxy();
    }
    if( !empty($parse_list)){
        $SC = new SuperCurl("search_from_google");
        $SC->__set('window_size', 100);
        $SC->init_console();
        $SC->__set("use_useragent_list", true);
        $SC->load_useragent_list('files/useragent_list.txt');
        $SC->__set("use_proxy_list", USE_PROXY);        
        $failed_count = 0;
        $count_live = 0;
        while($failed_count<2 && !empty($parse_list)){
            $SC->clear_the_parse_list();
            if(USE_PROXY){                
                $k = 0;
                while($count_live < 10&&count($PROXY_USE)>100&&$k<10){
                    shuffle($PROXY_USE);
                    $proxy_temp = array_slice($PROXY_USE, 0, 1000);
                    $SC->clear_proxy_notchecked();
                    $SC->load_proxy_list($proxy_temp, 10, 'http', 'http://www.google.com.ua/'/*, 'sfibbbc', '193.105.7.55'*/);                
                    $count_live = $SC->count_alive_proxy();
                    $k++;
                }
                if($count_live<10) $SC->__set("use_proxy_list", false);
                unset($PROXY_USE);
            }   
            $proxy_list = array_unique($proxy_list);
            foreach($parse_list as $key=>$prsr_url){        
                $params=array(CURLOPT_REFERER=>"http://".parse_url($prsr_url, PHP_URL_HOST), CURLOPT_COOKIE=>"referrer=http://".parse_url($prsr_url, PHP_URL_HOST));//refferer must be the same as site
                $SC->request($prsr_url, "POST", null, null, $params, $key);        
               
            }
            $SC->execute(10);
            $failed_count++;  
            $parse_list = $parse_links;
            $parse_links = array();
        }        
    }
    flock($fp, LOCK_UN);
    $proxy_list = array_unique($proxy_list);
	echo 'Proxy list count:<br/>'.count($proxy_list).'<br>';
	echo 'Finish: '.date('Y-m-d H:i:s').'<br>';
}
fclose($fp);
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
function makeurlarray(){
   global $URL_ARRAY;
   $str = file_get_contents(URLSETTINGS_FILE);
   $array = explode("\n", $str);
   foreach ($array as $cur){
      $cur = str_replace(" ", "", $cur);
      $cur = trim($cur);
      if (strpos('https', $cur)===false){
         $cur = str_replace("http://", "", $cur);
         $cur = "http://".$cur;
      }
         $cur = str_replace("http://", "", $cur);
         $cur = str_replace("https://", "", $cur);
      if ($cur!=''){   
      $URL_ARRAY[] = $cur;
      }
   }
}
function assignProxy()
{   global $PROXY_USE;
    flush();
    $list = file_get_contents(PROXY_FILE);
    if (trim($list) != '') $list = explode(PHP_EOL, $list);
    if (is_array($list) && !empty($list) && count($list)>100) { //We have enought proxy
        shuffle($list);
		foreach ($list as $v){
			$s = explode(':', $v);
			if (!empty($s[0]) && !empty($s[1]))	{
				$PROXY_USE[] = $v;
			}
		}
    }    
}
function search_from_google($response, $info, $request){ 
    if($info['http_code']<200 || $info['http_code']>300){
        SuperCurl::add_debug_msg("->t\tFAILED\t http_code:".$info['http_code']."\t".$info['total_time']."\t".$info['url']."\t PROXY:".$request->options['10004']);    
    }
    else{  
        if(strpos($info['url'], 'ipv4')!==false || strpos($response, "getElementById('captcha')")!==false){
           //SuperCurl::add_debug_msg("->t\tFAILED\t WE GOT CAPCHA \t".$info['total_time']."\t".$info['url']."\t PROXY:".$request->options['10004']);
           return 0;
        }
        $mime = strtolower(substr($info['content_type'], 0, strpos($info['content_type'].';', ';')));
        if ($mime == 'text/html' || true){
			//if (stripos($curl_info['content_type'], 'utf') && strpos($url, 'google.com') === false)
			if (stripos($info['content_type'], 'utf') === false)
			{
				/*$res = array();
				if (preg_match('/charset=([a-zA-A0-9_-]+)/i', $info['content_type'], $res))
				{
					$response = iconv($res[1], 'UTF-8', $response);
				}
				elseif (preg_match('/<meta[^>]+content="[^"]*charset=([a-zA-Z0-9_-]+)"[^>]*>/i', $data, $res))
				{
					$response = iconv($res[1], 'UTF-8', $response);
				}
				else $response = false;*/
				//$response = mb_convert_encoding($response , 'utf-8', mb_detect_encoding($response));
                if ($response === false) {
                    SuperCurl::add_debug_msg("->t\tFAILED\t content unknown charset \t".$info['total_time']."\t".$info['url']."\t PROXY:".$request->options['10004']);
                return 0;}
			}
            SuperCurl::add_debug_msg("->t\tSUCCESS\t".$info['http_code']."\t".$info['total_time']."\t".$info['url']."\t PROXY:".$request->options['10004']);
            analiz_parsed_proxy($response, $info, $request);
            get_the_links($request->id, $response);
		}
        else{
            SuperCurl::add_debug_msg("->t\tFAILED\t not text/html \t".$info['total_time']."\t".$info['url']."\t PROXY:".$request->options['10004']);
            
        }
       
    } 
}
function get_the_links($id, $response){
    global $parse_links;
    global $parse_list;   
    $htmlObj = phpQuery::newDocument($response);
    foreach($htmlObj['a'] as $element) {
        $link = pq($element)->attr('href');         
        $parse_links[] = $link;      
        SuperCurl::add_debug_msg("->FOUND LINK");              
    }
    
    //var_dump($parse_links[$id]['links']);
    //echo htmlspecialchars($response);
    
}