<?php 
	require_once('hhb_.inc.php');
	require_once('config.inc.php');
	hhb_init();
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
	$list_stuff=array('#foo'=>'foo','#bar'=>'bar','#baz'=>'baz');
	
	foreach($list_stuff as $key=>$thang){
		echo '<li><a href="'.$key.'">'.hhb_tohtml($thang).'</a></li>'.PHP_EOL;
		}
	?>
	</ul>
	</div>
		</body>
</html>