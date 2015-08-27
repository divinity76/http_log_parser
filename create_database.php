<?php 
	require_once('hhb_.inc.php');
	hhb_init();
	require_once('config.inc.php');
	$db->beginTransaction();
	prepare_table($db);
	$log_dir_opt=getopt("",array('log_dir:'));
	if(false!==$log_dir_opt && !empty($log_dir_opt['log_dir'])){
		$_REQUEST['log_dir']=$log_dir_opt['log_dir'];
	}	
	unset($log_dir_opt);
	if(empty($_REQUEST['log_dir'])){
		$_REQUEST['log_dir']='/path/to/log';
		echo "warning: no log_dir specified. default to: ".hhb_tohtml($_REQUEST['log_dir']);
	}
	$log_dir=$_REQUEST['log_dir'];
	if(!is_dir($log_dir))
	{
		die('error: log_dir is not a directory!: '.hhb_tohtml($log_dir));
	}
	if(!is_readable($log_dir)){
		die('error: can not read log_dir directory: '.hhb_tohtml($log_dir));
	}
	$logfiles=get_logfiles_from_dir($log_dir);
	if(count($logfiles)<=0){
		die('error: no logfiles found in log_dir: '.hhb_tohtml($log_dir));
	}
	echo "logfiles found: ".count($logfiles).". printing filenames:";
	echo '<pre>'.PHP_EOL;
	echo hhb_tohtml(print_r($logfiles,true));
	echo "\n\n<pre>\n\n";
	$totalRequestsProcessed=0;
	$toalRequestsIgnored=0;
	foreach($logfiles as $current_logfile){
		echo "processing logfile: ".$current_logfile." now...\n";
		$lines=@gzfile($current_logfile);
		if(!is_array($lines) || count($lines)<=1){
			$lines=@file($current_logfile);
		}
		if(!is_array($lines) || count($lines)<=1){
			echo "Error: cannot read the format of logfile ".hhb_tohtml($log_dir).". ignoring this logfile.";
			continue;
		}
		trimlines($lines);
		
		$stm=$db->prepare('INSERT INTO `http_accesslogs` '.
		'(`time`,`ip`,`request_type`,`http_version`,`http_status`,`body_bytes_sent`,`url`,`query`,`referer`,`user_agent`) '.
		'VALUES(:time,:ip,:request_type,:http_version,:http_status,:body_bytes_sent,:url,:query,:referer,:user_agent)');
		$stm->bindParam(':time', $ins_time, PDO::PARAM_STR);
		$stm->bindParam(':ip', $ins_ip, PDO::PARAM_STR);
		$stm->bindParam(':request_type', $ins_request_type, PDO::PARAM_STR);
		$stm->bindParam(':http_version', $ins_http_version, PDO::PARAM_STR);
		$stm->bindParam(':http_status', $ins_http_status, PDO::PARAM_STR);
		$stm->bindParam(':body_bytes_sent', $ins_body_bytes_sent, PDO::PARAM_INT);
		$stm->bindParam(':url', $ins_url, PDO::PARAM_STR);
		$stm->bindParam(':query', $ins_query, PDO::PARAM_STR);
		$stm->bindParam(':referer', $ins_referer, PDO::PARAM_STR);
		$stm->bindParam(':user_agent', $ins_user_agent, PDO::PARAM_STR);
		
		
		foreach($lines as $key=>$line){
			//108.38.43.108 - - [24/Aug/2015:07:56:15 +0200] "GET /prostatus/ HTTP/1.1" 200 785 "-" "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.36"
			//192.168.1.1 - username [25/Aug/2015:05:49:04 +0200] "GET /prostatus/fdas.php?password=abc HTTP/1.1" 200 12 "-" "Wget/1.15 (linux-gnu)"
			//190.205.4.210 - - [25/Aug/2015:09:45:51 +0200] "GET /prostatus/ HTTP/1.1" 200 768 "https://www.facebook.com/" "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.36"
			$remote_addr_raw="";//IP from which request was made
			$remote_user_raw="";// HTTP Authenticated User. This will be - for most apps as modern apps do not use HTTP-based authentication.
			$time_local_raw=""; //timestamp as per server timezone
			$request_type_raw="";// ? HTTP request type GET, POST, etc + requested path without args + HTTP protocol version
			$status_raw="";// HTTP response code from server
			$body_bytes_sent_raw="";// size of server response in bytes
			$http_referer_raw="";// Referral URL (if present)
			$http_user_agent_raw="";// User agent as seen by server
			//in short: /^([^\ ]+)\ \-\ ([^\ ]*)\ \[([^\]]+)\]\ \"([^\"]+)\"\ ([0-9]+)\ ([0-9]+)\ \"([^\"]*)\"\ \"([^\"]*)\"\s*$/
			$rex='/^'.//must start with
			'([^\\ ]+)'.//$remote_addr_raw
			'\\ \\-\\ './/separator
			'([^\\ ]*)'.//$remote_user_raw
			'\\ '.
			'\\[([^\\]]+)\\]'.//$time_local_raw
			'\\ '.
			'\\"([^\\"]+)\\"'.//$request_type_raw
			'\\ '.
			'([0-9]+)'.//$status_raw
			'\\ '.
			'([0-9]+)'.//$body_bytes_sent_raw
			'\\ '.
			'\\"([^\\"]*)\\"'.//$http_referer_raw
			'\\ '.
			'\\"([^\\"]*)\\"'.//$http_user_agent_raw
			'\\s*$/';//the end.
			$matches=array();
			$ret=preg_match($rex,$line,$matches,0,0);
			if($ret!==1){
				echo "Warning: did not understand the format of line ".($key+1)." because the main regex failed, so ignoring this line text: ".hhb_tohtml($line)."\n\n";
				++$toalRequestsIgnored;
				continue;
			}
			unset($ret);
			//var_dump($matches);die("dieds");
			//unset($matches[0]);var_dump($matches);die("DIEDSS");
			$remote_addr_raw=$matches[1];
			$remote_addr=$remote_addr_raw;
			$remote_user_raw=$matches[2];
			if(strlen($remote_user_raw)<2){
				$remote_user="";
				} else {
				$remote_user=$remote_user_raw;
			}
			$time_local_raw=$matches[3];
			//25/Aug/2015:09:45:51 +0200
			$dt=DateTime::createFromFormat("d/M/Y:H:i:s T",$time_local_raw);
			if($dt===false){
				echo "Warning: did not understand the format of line ".($key+1)." because the DateTime format failed with error: ".hhb_tohtml(var_export(DateTime::getLastErrors(),true)).", so ignoring this line text: ".hhb_tohtml($line)."\n\n";
				++$toalRequestsIgnored;
				continue;
			}
			$time_local=$dt->format("Y-m-d H:i:s");// the MySQL DATETIME format
			unset($dt);
			$request_type_raw=$matches[4];//GET /prostatus/asd.php?password=abc HTTP/1.1
			//echo ($request_type_raw).PHP_EOL;
			$parsed_url=array('request_type'=>'?','path'=>'?','query'=>'?','http_version'=>'?');
			unset($tmparr,$ret);
			$tmparr=array();
			$ret=preg_match('/^([^\\ ]*)\\ (.+)\\ (\\S*)\\s*$/',$request_type_raw,$tmparr,0,0);
			if($ret!==1){
				if(strlen($request_type_raw) <5+4 || 0!=preg_match_all('/(?:\\\\x..\\\\x..)|(?:\\\\x...\\\\x...)|(?:\\\\x...\\\\x..)/i',$request_type_raw,$tmparr,0,0)){
					//echo 'stupid hacker buffer overflow attempt ...';
					++$toalRequestsIgnored;
					continue;
				}
  	   			//var_dump($request_type_raw,$ret,$tmparr);continue;
				echo "Warning: did not understand the format of line ".($key+1)." because the request_type_raw regex failed, so ignoring this line text: ".hhb_tohtml($line)."\n\n";
				++$toalRequestsIgnored;
				continue;
			}
			$parsed_url=parse_url($tmparr[2]);
			if(false===$parsed_url || !array_key_exists('path',$parsed_url)){
				//var_dump($parsed_url,$tmparr[2]);die("DEIDS");
				$parsed_url=parse_url(substr($tmparr[2],1));//GET //prostatus ....
				if(false===$parsed_url || !array_key_exists('path',$parsed_url)){
					//var_dump($parsed_url,$tmparr[2]);die("DEIDS");
					if(array_key_exists('host',$parsed_url)){
						$parsed_url['path']=$parsed_url['host'];//141.212.122.122 - - [26/Aug/2015:19:08:41 +0200] "CONNECT proxytest.zmap.io:80 HTTP/1.1" 400 181 "-" "-"
						//not a great solution...
						} else {
						echo "Warning: did not understand the format of line ".($key+1)." because the parse_url failed to parse the path, so ignoring this line text: ".hhb_tohtml($line)."\n\n";			
						++$toalRequestsIgnored;
						continue;
					}
				}
			}
			if(!array_key_exists('query',$parsed_url)){
				$parsed_url['query']='';
			}
			$parsed_url['request_type']=trim($tmparr[1]);
			$parsed_url['http_version']=trim($tmparr[3]);
			/*array(4) {
				["path"]=>
				string(11) "/prostatus/"
				["query"]=>
				string(0) ""
				["request_type"]=>
				string(3) "GET"
				["http_version"]=>
				string(8) "HTTP/1.1"
			}*/
			//var_dump($parsed_url);continue;
			unset($tmparr,$ret);
			$status_raw=$matches[5];
			$status=(double)$status_raw;
			$body_bytes_sent_raw=$matches[6];
			$body_bytes_sent=(double)$body_bytes_sent_raw;
			$http_referer_raw=$matches[7];			
			if(strlen($http_referer_raw)<2 || NULL===($http_referer=parse_url($http_referer_raw,PHP_URL_HOST))){
				$http_referer="";
				//var_dump('gag',$http_referer_raw);continue;
			}
			$http_user_agent_raw=$matches[8];
			if(strlen($http_user_agent_raw)<2){
				$http_user_agent="";
				} else {
				$http_user_agent=$http_user_agent_raw;
			}
			$ins_ip=$remote_addr;
			$ins_request_type=$parsed_url['request_type'];
			$ins_http_version=$parsed_url['http_version'];
			$ins_http_status=$status;
			$ins_body_bytes_sent=$body_bytes_sent;
			$ins_time=$time_local;
			$ins_url=$parsed_url['path'];
			$ins_query=$parsed_url['query'];
			$ins_referer=$http_referer;
			$ins_user_agent=$http_user_agent;
			$stm->execute();
		    ++$totalRequestsProcessed;
			//echo $line."\n\n";
		}
	}
	echo "committing to db...";
	$db->commit();
	echo "\ndone! \ntotal requests processed: ".$totalRequestsProcessed."\ntotal requests ignored/unparsable: ".$toalRequestsIgnored;
	
	
	
	
	
	
	function prepare_table($db){
		$sql='CREATE TABLE `http_accesslogs` (
		`id` INTEGER PRIMARY KEY AUTOINCREMENT,
		`time` DATETIME NULL,
		`ip` VARCHAR(255) NULL,
		`request_type` VARCHAR(255) NULL,
		`http_version` VARCHAR(255) NULL,
		`http_status` VARCHAR(255) NULL,
		`body_bytes_sent` BIGINT NULL,
		`url` MEDIUMTEXT NULL,
		`query` MEDIUMTEXT NULL,
		`referer` MEDIUMTEXT NULL,
		`user_agent` MEDIUMTEXT NULL);';
		//ENGINE = InnoDB;';
		$db->query("DROP TABLE IF EXISTS `http_accesslogs`");
		$db->query($sql);
		
	}
	
	function trimlines(&$lines_arr){
		foreach($lines_arr as $key=>&$line){
			$line=trim($line);
			if(0===strlen($line)){
				//unset($lines_arr[$key]);//can i unset stuff in arrays im currently forEach'ing, safely? ...
			}
		}
	}
	function get_logfiles_from_dir($dir){
		$files=glob("access.log*");
		$ret=array();
		foreach($files as $file){
			if(!is_file($file)){
				echo "Warning: ignoring logfile ".hhb_tohtml($logfile)." because it is not a file.\n";
				continue;
			}
			if(!is_readable($file)){
				echo "Warning: ignoring logfile ".hhb_tohtml($logfile)." because it is not readable.\n";
				continue;
			}
			$ret[]=$file;
		}
		return $ret;
	}
