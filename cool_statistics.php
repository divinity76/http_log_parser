<?php 
	require_once('hhb_.inc.php');
	hhb_init();
	require_once('config.inc.php');
	function ipv4touint($ipv4){
		return sprintf('%u',ip2long($ipv4));
	}
    function ipv4_to_country_v2($ipv4){
		static $ip=false;
		$ipv4=filter_var($ipv4,FILTER_VALIDATE_IP,array('flags'=>FILTER_FLAG_IPV4,'options'=>array('default'=>false)));
		if($ipv4===false){
			throw new InvalidArgumentException('input is NOT a valid ipv4 address.. (sorry, no ipv6 support yet.)');
		}
		//$ip=ip2long($ipv4);
		$ip=ipv4touint($ipv4);
		if($ip===false){
			throw new UnexpectedValueException('input passed FILTER_FLAG_IPV4, but ip2long could not convert it! should never happen...');
		}
		static $ipdb=false;
		static $stm=false;
		if($ipdb===false){
			$ipdb=call_user_func(function(){
				$ipdb=new PDO('sqlite::memory:','','',array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
				$ipdb->query('CREATE TABLE `ipranges` (`iprange_start` INTEGER UNIQUE,`iprange_end` INTEGER UNIQUE,`country` VARCHAR(255));');
				assert(is_readable('Ipv4ToCountry.csv'));
				$iplist_raw=file_get_contents('Ipv4ToCountry.csv');
				$matches=array();
				$rex_ret=preg_match_all('/\\"([^\\"]*)\\"\\,\\"([^\\"]*)\\"\\,\\"([^\\"]*)\\"\\,\\"([^\\"]*)\\"\\,\\"([^\\"]*)\\"\\,\\"([^\\"]*)\\"\\,\\"([^\\"]*)\\"/',$iplist_raw,$matches);
				assert($rex_ret>9001);
				unset($matches[0],$iplist_raw);
				$iplist=array();
				//var_dump($rex_ret,$matches);
				$ipdb->beginTransaction();
				$stm=$ipdb->prepare('INSERT OR IGNORE INTO `ipranges` (`iprange_start`,`iprange_end`,`country`) VALUES(:iprange_start,:iprange_end,:country);');
				$stm->bindParam(':iprange_start', $ins_iprange_start, PDO::PARAM_INT);
				$stm->bindParam(':iprange_end', $ins_iprange_end, PDO::PARAM_INT);
				$stm->bindParam(':country', $ins_country, PDO::PARAM_STR);
				for($i=0;$i<$rex_ret;++$i){
					//$tmparr=array();
					# IP FROM      IP TO        REGISTRY  ASSIGNED   CTRY CNTRY COUNTRY
					# "1346797568","1346801663","ripencc","20010601","il","isr","Israel"
					//$tmparr['HHB_IPRANGE_START']=(int)$matches[1][$i];
					//$tmparr['HHB_IPRANGE_END']=(int)$matches[2][$i];
					//$tmparr['registry']=$matches[3][$i];
					//$tmparr['assigned']=$matches[4][$i];
					//$tmparr['ctry']=$matches[5][$i];
					//$tmparr['cntry']=$matches[6][$i];
					//$tmparr['HHB_COUNTRY']=$matches[7][$i];
					//$iplist[]=$tmparr;
					$ins_iprange_start=$matches[1][$i];
					$ins_iprange_end=$matches[2][$i];
					$ins_country=$matches[7][$i];
					//var_dump('adding: ',$ins_iprange_start,$ins_iprange_end,$ins_country);
					$stm->execute();
				}
				$ipdb->commit();
				return $ipdb;
			});
			$stm=$ipdb->prepare('SELECT `country` FROM `ipranges` WHERE :ip >= `iprange_start` AND :ip <= `iprange_end`');
			//$stm->bindParam(':ip',$ip,PDO::PARAM_INT);
			$stm->bindParam(':ip',$ip,PDO::PARAM_STR);
		}
		$stm->execute();
		if(false!==($row=$stm->fetch(PDO::FETCH_ASSOC))){
			return $row['country'];
		}
		return 'unknown_country';
	}
	function ipv4_to_country($ipv4){
		//USE ipv4_to_country_v2  INSTEAD! (its muuuuch faster)
		static $iplist=false;
		$ipv4=filter_var($ipv4,FILTER_VALIDATE_IP,array('flags'=>FILTER_FLAG_IPV4,'options'=>array('default'=>false)));
		if($ipv4===false){
			throw new InvalidArgumentException('input is NOT a valid ipv4 address.. (sorry, no ipv6 support yet.)');
		}
		$ip=ip2long($ipv4);
		if($ip===false){
			throw new UnexpectedValueException('input passed FILTER_FLAG_IPV4, but ip2long could not convert it! should never happen...');
		}
		/*		$myip2long=function($ipv4){
		    $ret=0;
			$rex='/^([0-9]+)\\.([0-9]+)\\.([0-9]+)\\.([0-9]+)$/';
			$matches=array();
			assert(1===preg_match($rex,$ipv4,$matches));
			unset($matches[0]);
			//1.2.3.4 = 4 + (3 * 256) + (2 * 256 * 256) + (1 * 256 * 256 * 256)
			
			$ret+=((int)$matches[4]);
			$ret+=((int)$matches[3])*256;
			$ret+=((int)$matches[2])*256*256;
			$ret+=((int)$matches[1])*256*256*256;
			var_dump($ret,ip2long($ipv4),$matches);die("DIEDS");
			return $ret;
			};
			$ip=$myip2long($ipv4);
		*/
		if($iplist===false){
			define("HHB_IPRANGE_START",0,true);
			define("HHB_IPRANGE_END",1,true);
			define("HHB_COUNTRY",3,true);
			$iplist=call_user_func(function(){
				//the file from http://software77.net/geo-ip/?DL=7
				assert(is_readable('Ipv4ToCountry.csv'));
				$iplist_raw=file_get_contents('Ipv4ToCountry.csv');
				$matches=array();
				$rex_ret=preg_match_all('/\"([^\"]*)\"\,\"([^\"]*)\"\,\"([^\"]*)\"\,\"([^\"]*)\"\,\"([^\"]*)\"\,\"([^\"]*)\"\,\"([^\"]*)\"/',$iplist_raw,$matches);
				assert($rex_ret>9001);
				unset($matches[0],$iplist_raw);
				$iplist=array();
				//var_dump($rex_ret,$matches);
				for($i=0;$i<$rex_ret;++$i){
					$tmparr=array();
					# IP FROM      IP TO        REGISTRY  ASSIGNED   CTRY CNTRY COUNTRY
					# "1346797568","1346801663","ripencc","20010601","il","isr","Israel"
					$tmparr[HHB_IPRANGE_START]=(int)$matches[1][$i];
					$tmparr[HHB_IPRANGE_END]=(int)$matches[2][$i];
					//$tmparr['registry']=$matches[3][$i];
					//$tmparr['assigned']=$matches[4][$i];
					//$tmparr['ctry']=$matches[5][$i];
					//$tmparr['cntry']=$matches[6][$i];
					$tmparr[HHB_COUNTRY]=$matches[7][$i];
					$iplist[]=$tmparr;
				}
				return $iplist;
			});
		}
		//var_dump($iplist);
		/*foreach($iplist as &$range){
			continue;
			if($ip>=$range['iprange_start'] && $ip<=$range['iprange_end'])
			{
			return $range['country'];
			}
			}
		*/	
		
		
		//believe it or not, foreach is too slow here x.x
		$max=count($iplist);
		$row=null;
		for($i=0;$i<$max;++$i){
			//continue;
			$row=$iplist[$i];
			if($ip>=$row[HHB_IPRANGE_START] && $ip <= $row[HHB_IPRANGE_END]){
				return $row[HHB_COUNTRY];
			}
		}
		return 'unknown_country';
	}
	//var_dump(ipv4_to_country("89.11.245.58"));die("DIEDS");
?>
<!DOCTYPE HTML>
<html><head><title>cool statistics</title>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
	<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css">
	<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>
</head>
<body>
	<div id="contents_overview" style="background-color:grey;">
		<ul>
			<?php
				$list_stuff=array('#most_popular_pages'=>'most popular pages','#distinct_visitors_by_country'=>'distinct visitors by country','#browsers_by_popularity'=>'browsers by popularity');
				foreach($list_stuff as $key=>$thang){
					echo '<li><a href="'.$key.'">'.hhb_tohtml($thang).'</a></li>'.PHP_EOL;
				}
			?>
		</ul>
	</div>
	<div id="most_popular_pages" style="background-color:antiquewhite">
		<center>most popular pages</center>
		<?php 
			$starttime=microtime(true);
			call_user_func(function() use($db){
				$total_views=0;
				$total_ips=0;
				$sql='SELECT       `url`,
				COUNT(`url`) AS `value_occurrence`,
				COUNT(distinct `ip`) AS `distinct_ips`
				FROM     `http_accesslogs`
				GROUP BY `url`
				ORDER BY `value_occurrence` DESC
				';
				
				$urls=$db->query($sql);
				//$urls=$db->query('SELECT * FROM `http_accesslogs` WHERE 1');
				echo '<ol type="1">';
				while(false!==($url=$urls->fetch(PDO::FETCH_ASSOC))){
					//					var_dump($url);die('xDIEDS');
					echo '<li>';
					echo '<span name="url" style="color:green">';
					echo hhb_tohtml($url['url']);
					echo '</span> views: <span name="views">'.hhb_tohtml($url['value_occurrence']).'</span>. ';
					echo 'distinct ips: <span name="distinct_ips">'.hhb_tohtml($url['distinct_ips']).'</span>';
					echo '</span></li>';
					$total_views+=((int)$url['value_occurrence']);
				}
				echo '</ol>';
				unset($sql);
				$sql='SELECT COUNT(distinct `ip` ) AS `ips` FROM `http_accesslogs`';
				$res=$db->query($sql);
				$row=$res->fetch(PDO::FETCH_ASSOC);
				$total_ips=(int)$row['ips'];
				echo "Total pageviews: ".$total_views.". total total distinct ips: ".$total_ips.". ";
			});
			$endtime=microtime(true);
			echo "seconds used counting most popular pages: ".($endtime-$starttime);
			unset($starttime,$endtime);
		?>
	</div>
	<div id="distinct_visitors_by_country" style="background-color:aliceblue;">
		<center>Distinct visitors by country</center>
		<br/>
		<ol>
			<?php 
				$starttime=microtime(true);
				call_user_func(function()use($db){
					$sql='SELECT DISTINCT `ip` AS `ip`,COUNT(`ip`) AS `total_requests` FROM `http_accesslogs` WHERE 1 GROUP BY `ip`';
					$ips=$db->query($sql);
					$ip="";
					$counteries_distinct_ips=array();
					$counteries_total_requests=array();
					$counter=0;
					//$allIPs=$ips->fetchAll();var_dump($allIPs);die("rDIEDS");
					
					
					while(false!==($ip=$ips->fetch(PDO::FETCH_ASSOC))){
						//$starttime=microtime(true);
						$country=ipv4_to_country_v2($ip['ip']);
						//$endtime=microtime(true);
						//++$counter;echo "now".$counter."...";
						//echo "used ".($endtime-$starttime)." seconds.. still going...";flush();continue;			
						if(array_key_exists($country,$counteries_distinct_ips)){//a performance thing. this is much faster than @
							++$counteries_distinct_ips[$country];
							$counteries_total_requests[$country]+=((int)$ip['total_requests']);
							} else {
							$counteries_distinct_ips[$country]=1;
							$counteries_total_requests[$country]=((int)$ip['total_requests']);							
							
						}
					}
					//var_dump($counteries);die("241DIEDS");
					//$starttime=microtime(true);
					assert(true===natsort($counteries_distinct_ips));
					$counteries_distinct_ips=array_reverse($counteries_distinct_ips,true);
					//$endtime=microtime(true);echo "sort used ".($endtime-$starttime)." seconds..";var_dump($counteries);die("DIEDS");
					foreach($counteries_distinct_ips as $country=>$distinct_ips){
						echo '<li>'.hhb_tohtml($country).': distinct IPs: '.hhb_tohtml($distinct_ips).'. total requests:'.hhb_tohtml($counteries_total_requests[$country]).'</li>'.PHP_EOL;
					}
					
				});
				$endtime=microtime(true);
			?>
		</ol>
		<?php echo "seconds used counting counteries:".($endtime-$starttime);unset($starttime,$endtime);?>
	</div>
	<div id="browsers_by_popularity" style="background-color:antiquewhite;">
		<center>Browsers by popularity (warning: detection is not easy...)</center>
		<ol>
		<?php
		$starttime=microtime(true);
		call_user_func(function()use($db){
			$oldcap=ini_get("browscap");
			$newcap=hhb_combine_filepaths(__DIR__,'/php_browscap.ini');
			assert(is_readable($newcap));
			ini_set("browscap",$newcap);
			//is this correct?
			$sql='
		SELECT DISTINCT `user_agent` AS `user_agent`, COUNT(distinct `ip`) as `distinct_ips`,
		COUNT(`user_agent`) AS `occurences` FROM `http_accesslogs` GROUP BY `user_agent` ORDER BY `distinct_ips` DESC
		';
		$res=$db->query($sql);
		//$all=$res->fetchAll(PDO::FETCH_ASSOC);var_dump($all);die("DIEDS");
		$browsers=array();
		
		while(false!==($row=$res->fetch(PDO::FETCH_ASSOC))){
			$browserinfo=get_browser($row['user_agent'],true);
			if($browserinfo['browser']=='Default Browser'){
				$browserinfo['browser']='Failed to detect browser';
			}
			if(!array_key_exists($browserinfo['browser'],$browsers)){
				$browsers[$browserinfo['browser']]=array('occurences'=>((int)$row['occurences']),'distinct_ips'=>((int)$row['distinct_ips']));
				} else {
				$browsers[$browserinfo['browser']]['occurences']+=((int)$row['occurences']);
				$browsers[$browserinfo['browser']]['distinct_ips']+=((int)$row['distinct_ips']);
			}
			//var_dump($browserinfo);
		}
		//var_dump($browsers);
		foreach($browsers as $browser=>$info){
			echo '<li>'.hhb_tohtml($browser).': '.hhb_tohtml($info['distinct_ips']).' distinct IPs. '.hhb_tohtml($info['occurences']).' total requests.'.'</li>';
		}
		ini_set("browscap",$oldcap);
		});
		$endtime=microtime(true);
		
	?>
	</ol>
	<?php 
		echo "seconds used counting browsers: ".($endtime-$starttime);
		unset($endtime,$starttime);
	?>
	</div>
</body>
</html>