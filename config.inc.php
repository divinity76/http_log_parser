<?php
	//$db = new PDO('mysql:host=localhost;dbname=testdb;charset=utf8', 'username', 'password', array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
	$log_dir='';
	$sqlite3=true;
	if($sqlite3){
		$dbfile=hhb_combine_filepaths(__DIR__,'accessdb.sqlite3');
		$db=new PDO('sqlite:'.$dbfile,'','',array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
		unset($dbfile);
	}

	
	
	$log_dir_opt=getopt("",array('log_dir:'));
	if(false!==$log_dir_opt && !empty($log_dir_opt['log_dir'])){
		$_REQUEST['log_dir']=$log_dir_opt['log_dir'];
		$log_dir=$_REQUEST['log_dir'];
	} else if(!empty($_REQUEST['log_dir'])){
	$log_dir=$_REQUEST['log_dir'];
	}
	if(!is_dir($log_dir)) 
	{
		die('error: log_dir is not a directory!: '.hhb_tohtml($log_dir));
	}
	if(!is_readable($log_dir)){
		die('error: can not read log_dir directory: '.hhb_tohtml($log_dir));
	}
	